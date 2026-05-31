<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Infrastructure\Persistence;

use BlackParadise\CoreAdmin\Domain\Contracts\Entity\EntityRecordContract;
use BlackParadise\CoreAdmin\Domain\Contracts\EntityDefinition\EntityDefinitionContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Fields\FieldContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Files\FileStorageProviderContract;
use BlackParadise\CoreAdmin\Domain\Contracts\TransactionContract;
use BlackParadise\CoreAdmin\Domain\Contracts\ValueHasherContract;
use BlackParadise\CoreAdmin\Domain\Entity\EntityRecord;
use BlackParadise\CoreAdmin\Domain\Fields\FileField;
use BlackParadise\CoreAdmin\Domain\Fields\ImageField;
use BlackParadise\CoreAdmin\Domain\Fields\RelationFieldTypes;
use BlackParadise\CoreAdmin\Domain\Fields\TranslatableField;
use BlackParadise\CoreAdmin\Domain\Mutators\EntityMutatorInterface;
use BlackParadise\CoreAdmin\Domain\ValueObjects\EntityKey;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Casts\AsEncryptedArrayObject;
use Illuminate\Database\Eloquent\Casts\AsEncryptedCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Throwable;

/**
 * Eloquent implementation of {@see EntityMutatorInterface}.
 *
 * Acts as a thin coordinator: filters incoming attributes against the
 * EntityDefinition (column/file/hashed/translatable transforms), performs the
 * host create/update/delete inside a transaction managed by the injected
 * {@see TransactionContract}, and delegates side-effect concerns to focused
 * collaborators:
 *
 *  - {@see RelationWriter} — belongsToMany / hasOne / hasMany / morphMany writes.
 *  - {@see MorphFilePersister} — morph_file record + file lifecycle.
 *
 * Cleanup of replaced/associated column files (file/image fields) happens
 * here because it is coupled to the host model's column attributes — see
 * {@see cleanupReplacedFiles()} and {@see collectAssociatedFilePaths()}.
 */
final readonly class EloquentEntityMutator implements EntityMutatorInterface
{
    public function __construct(
        private ValueHasherContract $hasher,
        private FileStorageProviderContract $fileStorage,
        private MorphFilePersister $morphFiles,
        private RelationWriter $relations,
        private TransactionContract $transactions,
    ) {}

    public function create(EntityRecordContract $record): EntityRecordContract
    {
        $definition    = $record->definition();
        $rawAttributes = $record->attributes();
        $relationData  = $this->relations->extractRelationPayload($definition, $rawAttributes);

        /** @var Model $instance */
        $instance = resolve($definition->modelClass());

        // Track files written to disk so we can roll them back if the DB tx fails.
        /** @var list<array{path: string, disk: ?string}> $uploadedPaths */
        $uploadedPaths      = [];
        $filteredAttributes = $this->filterAttributes($definition, $rawAttributes, $uploadedPaths);

        try {
            /** @var Model $created */
            $created = $this->transactions->executeInTransaction(function () use ($instance, $filteredAttributes, $relationData, $definition, $rawAttributes, &$uploadedPaths): Model {
                $host = $instance->newQuery()->create($filteredAttributes);
                $this->relations->applyAll($host, $definition, $relationData);
                foreach ($this->morphFiles->persistOnCreate($host, $definition, $rawAttributes) as $up) {
                    $uploadedPaths[] = $up;
                }
                return $host->refresh();
            });
        } catch (Throwable $e) {
            $this->deleteStoredPaths($uploadedPaths);
            throw $e;
        }

        $serializedRelations = $this->serializeRelations($created->getRelations());

        return new EntityRecord(
            definition: $definition,
            attributes: $created->attributesToArray(),
            relations: $serializedRelations,
        );
    }

    public function update(EntityKey $key, EntityRecordContract $record): EntityRecordContract
    {
        $definition    = $record->definition();
        $rawAttributes = $record->attributes();
        $relationData  = $this->relations->extractRelationPayload($definition, $rawAttributes);

        /** @var Model $instance */
        $instance = resolve($definition->modelClass());

        /** @var Model $existing */
        $existing = $instance->newQuery()->findOrFail($key->value);

        $oldFilePaths = $this->getExistingFilePaths($definition, $existing);

        /** @var list<array{path: string, disk: ?string}> $uploadedPaths */
        $uploadedPaths      = [];
        $filteredAttributes = $this->filterAttributes($definition, $rawAttributes, $uploadedPaths);

        /** @var list<array{path: string, disk: ?string}> $deferredDeletes */
        $deferredDeletes = [];

        try {
            $updated = $this->transactions->executeInTransaction(function () use ($existing, $filteredAttributes, $relationData, $definition, $rawAttributes, &$uploadedPaths, &$deferredDeletes): Model {
                $existing->update($filteredAttributes);
                $this->relations->applyAll($existing, $definition, $relationData);
                $morphResult = $this->morphFiles->persistOnUpdate($existing, $definition, $rawAttributes);
                foreach ($morphResult['uploaded'] as $up) {
                    $uploadedPaths[] = $up;
                }
                foreach ($morphResult['deferredDeletes'] as $d) {
                    $deferredDeletes[] = $d;
                }
                return $existing->refresh();
            });
        } catch (Throwable $e) {
            $this->deleteStoredPaths($uploadedPaths);
            throw $e;
        }

        // Transaction committed — safe to remove old replaced file/image files now.
        $this->cleanupReplacedFiles($oldFilePaths, $filteredAttributes, $definition);
        // And the morph_file old-paths the persister captured.
        $this->deleteStoredPaths($deferredDeletes);

        $serializedRelations = $this->serializeRelations($updated->getRelations());

        return new EntityRecord(
            definition: $definition,
            attributes: $updated->attributesToArray(),
            relations: $serializedRelations,
        );
    }

    public function delete(EntityKey $key, EntityDefinitionContract $entityDefinition): bool
    {
        /** @var Model $instance */
        $instance = resolve($entityDefinition->modelClass());

        /** @var Model|null $existing */
        $existing = $instance->newQuery()->find($key->value);

        if (!$existing) {
            return false;
        }

        /** @var list<array{path: string, disk: ?string}> $pathsToDelete */
        $pathsToDelete = [];

        $deleted = $this->transactions->executeInTransaction(function () use ($existing, $entityDefinition, &$pathsToDelete): bool {
            $this->relations->detachBelongsToManyPivots($existing, $entityDefinition);

            // Collect paths BEFORE deleting DB rows; deleted rows would be unreachable.
            foreach ($this->morphFiles->collectPathsForDelete($existing, $entityDefinition) as $item) {
                $pathsToDelete[] = $item;
            }
            foreach ($this->collectAssociatedFilePaths($entityDefinition, $existing) as $item) {
                $pathsToDelete[] = $item;
            }

            // Delete DB rows only — no disk I/O inside the transaction.
            $this->morphFiles->deleteRecords($existing, $entityDefinition);

            return (bool) $existing->delete();
        });

        // Disk I/O happens only after the transaction commits successfully.
        if ($deleted) {
            $this->deleteStoredPaths($pathsToDelete);
        }

        return $deleted;
    }

    /**
     * Best-effort cleanup of files written to disk before a failed transaction.
     *
     * Exceptions thrown by the storage backend are swallowed: rollback already
     * happened, and bubbling secondary failures would mask the original cause.
     *
     * @param list<array{path: string, disk: ?string}> $items
     */
    private function deleteStoredPaths(array $items): void
    {
        foreach ($items as $item) {
            $path = $item['path'];
            if ($path === '') {
                continue;
            }
            try {
                $this->fileStorage->delete($path, $item['disk']);
            } catch (Throwable) {
                // Swallow — never re-raise a cleanup failure.
            }
        }
    }

    /**
     * Filter raw attributes to only the fields declared in the entity definition.
     *
     * Applies field-type-specific transformations:
     * - 'hashed': empty values stripped, non-empty hashed
     * - 'file'/'image': UploadedFile stored and replaced with path
     * - 'translatable': array JSON-encoded unless model cast or managedByModel flag suppresses it
     */
    /**
     * @param array<string, mixed> $attributes
     * @param list<array{path: string, disk: ?string}> $storedPaths Receives stored file paths, by reference.
     * @return array<string, mixed>
     */
    private function filterAttributes(
        EntityDefinitionContract $definition,
        array $attributes,
        array &$storedPaths = [],
    ): array {
        $columnFields = array_filter(
            $definition->fields(),
            fn(FieldContract $f): bool => !RelationFieldTypes::isSideEffect($f->type())
                && $f->writable() === true,
        );
        $allowed = array_map(fn(FieldContract $f): string => $f->name(), $columnFields);
        $filtered = array_intersect_key($attributes, array_flip($allowed));

        foreach ($columnFields as $field) {
            $name = $field->name();
            if (!array_key_exists($name, $filtered)) {
                continue;
            }

            if ($field->type() === 'hashed') {
                $value = $filtered[$name];
                if (
                    $value === ''
                    || $value === null
                    || $value === false
                    || (is_string($value) && trim($value) === '')
                ) {
                    unset($filtered[$name]);
                } else {
                    $filtered[$name] = $this->hasher->hash((string) $value);
                }
            }

            if (in_array($field->type(), ['file', 'image'], true)) {
                $value = $filtered[$name];
                if ($value instanceof UploadedFile) {
                    $dir = ($field instanceof FileField && $field->getDirectory() !== '')
                        ? $field->getDirectory()
                        : (($field instanceof ImageField && $field->getDirectory() !== '')
                            ? $field->getDirectory()
                            : $definition->name() . '/' . $name);

                    $disk = $this->fieldDisk($field);
                    $storedPath = $this->fileStorage->store($dir, $value, $disk);
                    $filtered[$name] = $storedPath;
                    $storedPaths[] = ['path' => $storedPath, 'disk' => $disk];
                } elseif ($value === null || $value === '') {
                    unset($filtered[$name]);
                }
            }

            if ($field->type() === 'translatable' && isset($filtered[$name]) && is_array($filtered[$name])) {
                // Resolve host model instance once per definition to read casts.
                $model = resolve($definition->modelClass());
                if ($this->shouldEncodeTranslatable($field, $model)) {
                    $filtered[$name] = json_encode($filtered[$name], JSON_UNESCAPED_UNICODE);
                }
            }
        }

        return $filtered;
    }

    /**
     * Resolve the disk for a file/image field, or null when not configured.
     */
    private function fieldDisk(FieldContract $field): ?string
    {
        if ($field instanceof FileField) {
            $disk = $field->getDisk();
            return $disk !== '' ? $disk : null;
        }
        if ($field instanceof ImageField) {
            $disk = $field->getDisk();
            return $disk !== '' ? $disk : null;
        }
        return null;
    }

    /**
     * Cast types that already serialize the attribute value into JSON on save.
     * If a column has one of these casts, we must NOT pre-encode in the mutator
     * or we double-encode.
     *
     * Covers Laravel built-ins and Spatie-translatable (which uses 'array').
     * AsArrayObject/AsCollection classes may include `:` parameters — we strip.
     */
    private const SELF_SERIALIZING_CASTS = [
        'array',
        'json',
        'object',
        'collection',
        'translatable', // Spatie convention in some projects
    ];

    /**
     * Class-string casts that handle their own serialization. We must NOT
     * pre-encode values flowing into a column with one of these casts.
     *
     * `is_subclass_of` covers user-defined casts that extend these classes.
     * `class_exists` guards against missing classes in older Laravel versions.
     */
    private const SELF_SERIALIZING_CAST_CLASSES = [
        AsArrayObject::class,
        AsCollection::class,
        AsEncryptedArrayObject::class,
        AsEncryptedCollection::class,
    ];

    /**
     * Decide whether the mutator should serialize a translatable value into JSON.
     *
     * Rules (highest priority first):
     *   1. `TranslatableField->isManagedByModel()` set → NEVER encode.
     *   2. Host model declares a self-serializing string cast on the column → NEVER encode.
     *   3. Host model declares a self-serializing class-string cast → NEVER encode.
     *   4. Otherwise → encode (legacy behavior preserved).
     */
    private function shouldEncodeTranslatable(
        FieldContract $field,
        Model $model,
    ): bool {
        if ($field instanceof TranslatableField
            && $field->isManagedByModel()
        ) {
            return false;
        }

        $castType = $this->extractCastType($model, $field->name());
        if ($castType !== null) {
            if (in_array($castType, self::SELF_SERIALIZING_CASTS, true)) {
                return false;
            }
            if ($this->isSelfSerializingClassCast($castType)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Return true when $cast is a class-string that handles its own serialization.
     *
     * Covers exact matches and subclasses (e.g. user-defined casts extending
     * AsArrayObject). If the class does not exist in this installation the check
     * returns false gracefully.
     */
    private function isSelfSerializingClassCast(string $cast): bool
    {
        if (!class_exists($cast)) {
            return false;
        }
        foreach (self::SELF_SERIALIZING_CAST_CLASSES as $known) {
            if ($cast === $known || is_subclass_of($cast, $known)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Class-string casts (AsArrayObject, AsCollection, custom) are returned as-is —
     * callers must use {@see isSelfSerializingClassCast()} to inspect them.
     *
     * Built-in scalar casts have any parameter suffix stripped (e.g. `datetime:Y-m-d`
     * → `datetime`).
     */
    private function extractCastType(Model $model, string $attribute): ?string
    {
        $casts = $model->getCasts();
        if (!array_key_exists($attribute, $casts)) {
            return null;
        }
        $raw = (string) $casts[$attribute];
        $colonPos = strpos($raw, ':');
        return $colonPos === false ? $raw : substr($raw, 0, $colonPos);
    }

    /**
     * Capture existing file paths from the model before update.
     *
     * @return array<string, string|null>
     */
    private function getExistingFilePaths(EntityDefinitionContract $definition, Model $model): array
    {
        $paths = [];
        foreach ($definition->fields() as $field) {
            if (in_array($field->type(), ['file', 'image'], true)) {
                $paths[$field->name()] = $model->getAttribute($field->name());
            }
        }

        return $paths;
    }

    /**
     * Delete old files that were replaced during an update.
     *
     * @param array<string, string|null> $oldPaths
     * @param array<string, mixed> $newAttributes
     */
    private function cleanupReplacedFiles(
        array $oldPaths,
        array $newAttributes,
        EntityDefinitionContract $definition,
    ): void {
        $diskByField = $this->fileFieldDiskMap($definition);
        foreach ($oldPaths as $name => $oldPath) {
            if ($oldPath && isset($newAttributes[$name]) && $newAttributes[$name] !== $oldPath) {
                $disk = $diskByField[$name] ?? null;
                try {
                    $this->fileStorage->delete($oldPath, $disk);
                } catch (Throwable) {
                    // Swallow — cleanup of replaced files must not break the response.
                }
            }
        }
    }

    /**
     * Collect all disk paths stored in file/image columns of a model.
     * Does NOT delete any DB rows or disk files.
     *
     * @return list<array{path: string, disk: ?string}>
     */
    private function collectAssociatedFilePaths(EntityDefinitionContract $definition, Model $model): array
    {
        $paths = [];
        foreach ($definition->fields() as $field) {
            if (in_array($field->type(), ['file', 'image'], true)) {
                $path = $model->getAttribute($field->name());
                if (is_string($path) && $path !== '') {
                    $paths[] = ['path' => $path, 'disk' => $this->fieldDisk($field)];
                }
            }
        }
        return $paths;
    }

    /**
     * Build a map of file/image field name → configured disk (or null).
     *
     * @return array<string, ?string>
     */
    private function fileFieldDiskMap(EntityDefinitionContract $definition): array
    {
        $map = [];
        foreach ($definition->fields() as $field) {
            if (in_array($field->type(), ['file', 'image'], true)) {
                $map[$field->name()] = $this->fieldDisk($field);
            }
        }
        return $map;
    }

    /**
     * Serialize Eloquent relation objects into plain arrays.
     *
     * @param array<string, mixed> $relations
     * @return array<string, mixed>
     */
    private function serializeRelations(array $relations): array
    {
        return collect($relations)
            ->map(fn(mixed $rel) => $rel instanceof Model
                ? $rel->attributesToArray()
                : (method_exists($rel, 'toArray') ? $rel->toArray() : []))
            ->all();
    }
}
