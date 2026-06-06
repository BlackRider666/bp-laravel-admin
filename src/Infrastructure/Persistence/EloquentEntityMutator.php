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
use BlackParadise\CoreAdmin\Domain\Exceptions\ValidationException;
use BlackParadise\CoreAdmin\Domain\Fields\FileField;
use BlackParadise\CoreAdmin\Domain\Fields\ImageField;
use BlackParadise\CoreAdmin\Domain\Fields\MorphToField;
use BlackParadise\CoreAdmin\Domain\Fields\RelationFieldTypes;
use BlackParadise\CoreAdmin\Domain\Fields\TranslatableField;
use BlackParadise\CoreAdmin\Domain\Mutators\EntityMutatorInterface;
use BlackParadise\CoreAdmin\Domain\ValueObjects\EntityKey;
use BlackParadise\LaravelAdmin\Support\DeferredFileOperations;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Casts\AsEncryptedArrayObject;
use Illuminate\Database\Eloquent\Casts\AsEncryptedCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\QueryException;
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
        private DeferredFileOperations $deferredFiles,
    ) {}

    public function create(EntityRecordContract $record): EntityRecordContract
    {
        $definition    = $record->definition();
        $rawAttributes = $record->attributes();
        $relationData  = $this->relations->extractRelationPayload($definition, $rawAttributes);

        /** @var Model $instance */
        $instance = resolve($definition->modelClass());

        $filteredAttributes = $this->filterAttributes($definition, $rawAttributes);

        try {
            /** @var Model $created */
            $created = $this->transactions->executeInTransaction(function () use ($instance, $filteredAttributes, $relationData, $definition, $rawAttributes): Model {
                $host = $instance->newQuery()->create($filteredAttributes);
                $this->relations->applyAll($host, $definition, $relationData);
                foreach ($this->morphFiles->persistOnCreate($host, $definition, $rawAttributes) as $up) {
                    $this->deferredFiles->recordUpload($up['path'], $up['disk']);
                }
                return $host->refresh();
            });
        } catch (QueryException $e) {
            // On DB failure: roll back any uploaded files (removes orphans).
            // Self-flush only when no outer scope owner (e.g. the controller) has
            // registered itself to handle the final flush. When an outer scope
            // exists the controller's finally-block calls rollback().
            if (!$this->deferredFiles->hasOuterScope()) {
                $this->deferredFiles->rollback();
            }
            throw $this->convertQueryException($e);
        } catch (Throwable $e) {
            if (!$this->deferredFiles->hasOuterScope()) {
                $this->deferredFiles->rollback();
            }
            throw $e;
        }

        // Self-flush only when no outer scope owner has registered. When the
        // controller (or another outer caller) has called beginOuterScope(), it
        // will flush at the true outermost transaction boundary via its finally.
        if (!$this->deferredFiles->hasOuterScope()) {
            $this->deferredFiles->commit();
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

        $oldFilePaths       = $this->getExistingFilePaths($definition, $existing);
        $filteredAttributes = $this->filterAttributes($definition, $rawAttributes);

        try {
            $updated = $this->transactions->executeInTransaction(function () use ($existing, $filteredAttributes, $relationData, $definition, $rawAttributes): Model {
                $existing->update($filteredAttributes);
                $this->relations->applyAll($existing, $definition, $relationData);
                $morphResult = $this->morphFiles->persistOnUpdate($existing, $definition, $rawAttributes);
                foreach ($morphResult['uploaded'] as $up) {
                    $this->deferredFiles->recordUpload($up['path'], $up['disk']);
                }
                foreach ($morphResult['deferredDeletes'] as $d) {
                    $this->deferredFiles->recordDeletion($d['path'], $d['disk']);
                }
                return $existing->refresh();
            });
        } catch (QueryException $e) {
            // On DB failure: roll back any uploaded files (removes orphans).
            // Self-flush only when no outer scope owner has registered.
            if (!$this->deferredFiles->hasOuterScope()) {
                $this->deferredFiles->rollback();
            }
            throw $this->convertQueryException($e);
        } catch (Throwable $e) {
            if (!$this->deferredFiles->hasOuterScope()) {
                $this->deferredFiles->rollback();
            }
            throw $e;
        }

        // Replaced column files — record for deferred deletion.
        $this->recordReplacedFileDeletions($oldFilePaths, $filteredAttributes, $definition);

        // Self-flush only when no outer scope owner has registered. When the
        // controller (or another outer caller) has called beginOuterScope(), it
        // will flush at the true outermost transaction boundary via its finally.
        if (!$this->deferredFiles->hasOuterScope()) {
            $this->deferredFiles->commit();
        }

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

        if ($deleted) {
            $usesSoftDeletes = in_array(
                SoftDeletes::class,
                class_uses_recursive($existing),
                true,
            );
            $isSoftDelete = $usesSoftDeletes
                && method_exists($existing, 'isForceDeleting')
                && !$existing->isForceDeleting();

            if (!$isSoftDelete) {
                foreach ($pathsToDelete as $item) {
                    $this->deferredFiles->recordDeletion($item['path'], $item['disk']);
                }
            }

            // Self-flush only when no outer scope owner has registered. Direct
            // callers (CLI / seeder / bulkDestroy per-iteration) have no outer scope,
            // so the mutator flushes immediately. The HTTP destroy path registers an
            // outer scope via the controller's beginOuterScope(), so flushing is
            // deferred to the controller's finally block.
            if (!$this->deferredFiles->hasOuterScope()) {
                $this->deferredFiles->commit();
            }
        }

        return $deleted;
    }

    /**
     * Map an Illuminate QueryException to a domain ValidationException (HTTP 422)
     * only for SQLSTATE codes that represent *user-correctable* data violations.
     *
     * Mapped SQLSTATE codes:
     *   22001 — string data, right truncation ("Data too long for column …")
     *   23000 → UNIQUE violation only — converted to field-level 422.
     *
     * SQLSTATE 23000 covers UNIQUE, FK, and NOT-NULL violations. FK and NOT-NULL
     * failures are server-side bugs (wrong FK value in code, missing NOT-NULL
     * default) that the end-user cannot fix — re-throwing them as QueryException
     * lets the framework render HTTP 500 and surface the real bug.
     *
     * Driver-specific unique violation codes:
     *   MySQL / MariaDB : errorInfo[1] === 1062
     *   SQLite          : errorInfo[1] === 19 AND message contains "UNIQUE constraint"
     *   PostgreSQL      : errorInfo[0] === '23505' (unique_violation)
     *
     * @throws ValidationException for 22001 or UNIQUE-23000 violations.
     * @throws QueryException for FK / NOT-NULL / all other DB errors.
     */
    private function convertQueryException(QueryException $e): never
    {
        $sqlState  = (string) ($e->errorInfo[0] ?? '');
        $nativeCode = (int) ($e->errorInfo[1] ?? 0);
        $message    = $e->getMessage();

        if ($sqlState === '22001') {
            throw new ValidationException(
                ['_database' => ['The value is too long for one of the fields.']],
                $message,
            );
        }

        // PostgreSQL raises a dedicated SQLSTATE for unique violations.
        if ($sqlState === '23505') {
            throw new ValidationException(
                ['_database' => ['A record with those values already exists.']],
                $message,
            );
        }

        if ($sqlState === '23000') {
            $isUnique = $this->isUniqueViolation($nativeCode, $message);

            if ($isUnique) {
                throw new ValidationException(
                    ['_database' => ['A record with those values already exists.']],
                    $message,
                );
            }

            // NOT NULL violations surface as 422: the operator submitted a null/empty
            // value for a column that has a DB NOT NULL constraint. We try to extract
            // the column name from the constraint message to produce a field-level error.
            $notNullField = $this->extractNotNullField($nativeCode, $message);
            if ($notNullField !== null) {
                throw new ValidationException(
                    [$notNullField => ['The ' . $notNullField . ' field is required.']],
                    $message,
                );
            }

            // FK / other 23000 sub-types → re-throw as server error.
            throw $e;
        }

        throw $e;
    }

    /**
     * Determine whether a SQLSTATE 23000 exception is specifically a UNIQUE
     * constraint violation (as opposed to FK or NOT-NULL).
     *
     * MySQL / MariaDB: native error code 1062.
     * SQLite         : native code 19 (SQLITE_CONSTRAINT) with "UNIQUE constraint"
     *                  in the message. Note: SQLite 3.25+ also raises code 2067
     *                  (SQLITE_CONSTRAINT_UNIQUE) but PDO normalises to 19.
     */
    private function isUniqueViolation(int $nativeCode, string $message): bool
    {
        // MySQL / MariaDB
        if ($nativeCode === 1062) {
            return true;
        }
        // SQLite — native code 19 is the generic SQLITE_CONSTRAINT; we must
        // inspect the message to distinguish UNIQUE from FK / NOT NULL.
        return $nativeCode === 19 && stripos($message, 'UNIQUE constraint') !== false;
    }

    /**
     * Attempt to extract the column name from a NOT NULL constraint violation.
     *
     * Returns the column name (without table prefix) on success, or null when
     * the exception is not a NOT NULL violation or the column cannot be parsed.
     *
     * SQLite message format: "NOT NULL constraint failed: <table>.<column>"
     * MySQL/MariaDB message format: "Column '<column>' cannot be null"
     */
    private function extractNotNullField(int $nativeCode, string $message): ?string
    {
        // MySQL / MariaDB: native code 1048 — "Column 'x' cannot be null"
        if ($nativeCode === 1048) {
            if (preg_match("/Column '([^']+)' cannot be null/i", $message, $m) === 1) {
                return $m[1];
            }
            return '_database';
        }
        // SQLite: native code 19 with "NOT NULL constraint failed: table.column"
        if ($nativeCode === 19 && stripos($message, 'NOT NULL constraint') !== false) {
            if (preg_match('/NOT NULL constraint failed:\s*\w+\.(\w+)/i', $message, $m) === 1) {
                return $m[1];
            }
            return '_database';
        }
        return null;
    }

    /**
     * Filter raw attributes to only the fields declared in the entity definition.
     *
     * Applies field-type-specific transformations:
     * - 'hashed': empty values stripped; already-hashed values stored as-is; plain text hashed.
     * - 'file'/'image': UploadedFile stored to disk, replaced with path string;
     *   upload path recorded in DeferredFileOperations for rollback on outer tx failure.
     * - 'translatable': array JSON-encoded unless model cast or managedByModel flag suppresses it.
     *
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function filterAttributes(
        EntityDefinitionContract $definition,
        array $attributes,
    ): array {
        $columnFields = array_filter(
            $definition->fields(),
            fn(FieldContract $f): bool => !RelationFieldTypes::isSideEffect($f->type())
                && $f->writable(),
        );
        $allowed = array_map(fn(FieldContract $f): string => $f->name(), $columnFields);
        $filtered = array_intersect_key($attributes, array_flip($allowed));

        // Resolved lazily once for the translatable-encoding check; avoids
        // repeated Service Container lookups inside the field loop.
        $modelInstance = null;

        foreach ($columnFields as $field) {
            $name = $field->name();
            if (!array_key_exists($name, $filtered)) {
                continue;
            }

            if ($field->type() === 'hashed') {
                $value = $filtered[$name];
                if (
                    in_array($value, ['', null, false], true)
                    || (is_string($value) && trim($value) === '')
                ) {
                    unset($filtered[$name]);
                } elseif (is_string($value) && $this->hasher->isHashed($value)) {
                    // Already hashed (e.g. round-trip of existing value) — store as-is.
                    $filtered[$name] = $value;
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
                    $this->deferredFiles->recordUpload($storedPath, $disk);
                } elseif ($value === null || $value === '') {
                    unset($filtered[$name]);
                }
            }

            if ($field->type() === 'translatable' && isset($filtered[$name]) && is_array($filtered[$name])) {
                // Resolve host model instance once per filterAttributes() call.
                $modelInstance ??= resolve($definition->modelClass());
                if ($this->shouldEncodeTranslatable($field, $modelInstance)) {
                    $filtered[$name] = json_encode($filtered[$name], JSON_UNESCAPED_UNICODE);
                }
            }
        }

        // morphTo fields persist two columns ({name}_type / {name}_id) keyed off
        // the column names, not the field name. Admit + normalise the type value
        // to its morph-map class (alias when an enforced morph map exists).
        foreach ($definition->fields() as $field) {
            if (!$field instanceof MorphToField) {
                continue;
            }
            $typeCol = $field->typeColumn();
            $idCol   = $field->idColumn();
            if (array_key_exists($typeCol, $attributes) && array_key_exists($idCol, $attributes)) {
                $rawType = (string) $attributes[$typeCol];
                if (class_exists($rawType) && is_subclass_of($rawType, Model::class)) {
                    $instance = new $rawType();
                    $filtered[$typeCol] = $instance->getMorphClass();
                } else {
                    $filtered[$typeCol] = $rawType;
                }
                $filtered[$idCol] = $attributes[$idCol];
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
     * Record old files that were replaced during an update as deferred deletions.
     * The controller flushes the DeferredFileOperations collector at the outermost
     * transaction boundary, ensuring old files are only removed on commit.
     *
     * @param array<string, string|null> $oldPaths
     * @param array<string, mixed> $newAttributes
     */
    private function recordReplacedFileDeletions(
        array $oldPaths,
        array $newAttributes,
        EntityDefinitionContract $definition,
    ): void {
        $diskByField = $this->fileFieldDiskMap($definition);
        foreach ($oldPaths as $name => $oldPath) {
            if ($oldPath !== null && $oldPath !== '' && isset($newAttributes[$name]) && $newAttributes[$name] !== $oldPath) {
                $this->deferredFiles->recordDeletion($oldPath, $diskByField[$name] ?? null);
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
