<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Infrastructure\Persistence;

use BlackParadise\CoreAdmin\Domain\Contracts\Entity\EntityRecordContract;
use BlackParadise\CoreAdmin\Domain\Contracts\EntityDefinition\EntityDefinitionContract;
use BlackParadise\CoreAdmin\Domain\Contracts\TransactionContract;
use BlackParadise\CoreAdmin\Domain\Entity\EntityRecord;
use BlackParadise\CoreAdmin\Domain\Exceptions\EntityNotFoundException;
use BlackParadise\CoreAdmin\Domain\Exceptions\ValidationException;
use BlackParadise\CoreAdmin\Domain\Mutators\EntityMutatorInterface;
use BlackParadise\CoreAdmin\Domain\ValueObjects\EntityKey;
use BlackParadise\LaravelAdmin\Exceptions\ModelLessDefinitionException;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\FieldPersisters\FieldPersisterRegistry;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\FieldPersisters\PersistContext;
use BlackParadise\LaravelAdmin\Support\DeferredFileOperations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\QueryException;
use Throwable;

/**
 * Eloquent implementation of {@see EntityMutatorInterface}.
 *
 * Orchestrator only: per-field-type write logic lives in the
 * {@see FieldPersisterRegistry} strategies. A write is
 *   1. every handled field's `toAttributes()` → host columns,
 *   2. host create/update inside a transaction managed by {@see TransactionContract},
 *   3. every handled field's `afterSave()` in the same transaction.
 * Deletes run every field's `beforeDelete()` before the host row is removed.
 *
 * File side-effects are only recorded in {@see DeferredFileOperations} (uploads
 * rolled back on failure, removals applied after commit).
 */
final readonly class EloquentEntityMutator implements EntityMutatorInterface
{
    public function __construct(
        private FieldPersisterRegistry $persisters,
        private TransactionContract $transactions,
        private DeferredFileOperations $deferredFiles,
    ) {}

    public function create(EntityRecordContract $record): EntityRecordContract
    {
        $definition = $record->definition();
        $raw        = $record->attributes();
        $context    = new PersistContext($definition, $raw, $this->deferredFiles);

        try {
            $columns = $this->collectColumns($definition, $context);

            /** @var Model $created */
            $created = $this->transactions->executeInTransaction(function () use ($definition, $columns, $raw, $context): Model {
                /** @var Model $instance */
                $instance = resolve($definition->modelClass() ?? throw ModelLessDefinitionException::forDefinition($definition));
                $host     = $instance->newQuery()->create($columns);
                $this->afterSave($host, $definition, $raw, $context);

                return $host->refresh();
            });
        } catch (QueryException $e) {
            $this->discardOnFailure();
            throw $this->convertQueryException($e);
        } catch (Throwable $e) {
            $this->discardOnFailure();
            throw $e;
        }

        $this->flushIfOwner();

        return new EntityRecord(
            definition: $definition,
            attributes: $created->attributesToArray(),
            relations: $this->serializeRelations($created->getRelations()),
        );
    }

    public function update(EntityKey $key, EntityRecordContract $record): EntityRecordContract
    {
        $definition = $record->definition();
        $raw        = $record->attributes();

        /** @var Model $instance */
        $instance = resolve($definition->modelClass() ?? throw ModelLessDefinitionException::forDefinition($definition));

        /** @var Model|null $existing */
        $existing = $instance->newQuery()->find($key->value);

        if ($existing === null) {
            throw new EntityNotFoundException($definition->name(), $key->value);
        }

        $context = new PersistContext($definition, $raw, $this->deferredFiles, $existing);

        try {
            $columns = $this->collectColumns($definition, $context);

            $updated = $this->transactions->executeInTransaction(function () use ($existing, $definition, $columns, $raw, $context): Model {
                $existing->update($columns);
                $this->afterSave($existing, $definition, $raw, $context);

                return $existing->refresh();
            });
        } catch (QueryException $e) {
            $this->discardOnFailure();
            throw $this->convertQueryException($e);
        } catch (Throwable $e) {
            $this->discardOnFailure();
            throw $e;
        }

        $this->flushIfOwner();

        return new EntityRecord(
            definition: $definition,
            attributes: $updated->attributesToArray(),
            relations: $this->serializeRelations($updated->getRelations()),
        );
    }

    public function delete(EntityKey $key, EntityDefinitionContract $entityDefinition): bool
    {
        /** @var Model $instance */
        $instance = resolve($entityDefinition->modelClass() ?? throw ModelLessDefinitionException::forDefinition($entityDefinition));

        /** @var Model|null $existing */
        $existing = $instance->newQuery()->find($key->value);

        if (!$existing) {
            return false;
        }

        $softDeletable = in_array(SoftDeletes::class, class_uses_recursive($existing), true);
        $force         = $softDeletable
            && method_exists($existing, 'isForceDeleting')
            && $existing->isForceDeleting();
        $context = new PersistContext($entityDefinition, [], $this->deferredFiles, $existing, $force, $softDeletable);

        try {
            $deleted = $this->transactions->executeInTransaction(function () use ($existing, $entityDefinition, $context): bool {
                foreach ($entityDefinition->fields() as $field) {
                    $this->persisters->for($field)->beforeDelete($field, $existing, $context);
                }

                return (bool) $existing->delete();
            });
        } catch (Throwable $e) {
            $this->discardOnFailure();
            throw $e;
        }

        if ($deleted) {
            // Self-flush only when no outer scope owner has registered (CLI / seeder /
            // bulk per-iteration). The HTTP path flushes in the controller's finally.
            $this->flushIfOwner();
        } else {
            $this->discardOnFailure();
        }

        return $deleted;
    }

    /**
     * Host columns for this write: union of every handled field's toAttributes().
     *
     * @return array<string, mixed>
     */
    private function collectColumns(EntityDefinitionContract $definition, PersistContext $context): array
    {
        $columns = [];
        foreach ($definition->fields() as $field) {
            if (!$field->isSideEffect() && !$field->writable() && $field->type() !== 'morph_to') {
                continue;
            }
            $persister = $this->persisters->for($field);
            if (!$persister->handles($field, $context->attributes)) {
                continue;
            }
            $columns = array_merge(
                $columns,
                $persister->toAttributes($field, $context->attributes[$field->name()] ?? null, $context),
            );
        }

        return $columns;
    }

    /**
     * @param array<string, mixed> $raw
     */
    private function afterSave(Model $host, EntityDefinitionContract $definition, array $raw, PersistContext $context): void
    {
        foreach ($definition->fields() as $field) {
            if (!$field->isSideEffect() && !$field->writable()) {
                continue;
            }
            $persister = $this->persisters->for($field);
            if ($persister->handles($field, $raw)) {
                $persister->afterSave($field, $raw[$field->name()] ?? null, $host, $context);
            }
        }
    }

    /**
     * Roll back uploads + drop recorded removals — only when no outer scope owner
     * (e.g. the controller) will do it in its own finally block.
     */
    private function discardOnFailure(): void
    {
        if (!$this->deferredFiles->hasOuterScope()) {
            $this->deferredFiles->rollback();
        }
    }

    private function flushIfOwner(): void
    {
        if (!$this->deferredFiles->hasOuterScope()) {
            $this->deferredFiles->commit();
        }
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
