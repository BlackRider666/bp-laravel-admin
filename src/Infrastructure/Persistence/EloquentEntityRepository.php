<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Infrastructure\Persistence;

use BlackParadise\CoreAdmin\Domain\Contracts\Entity\EntityRecordContract;
use BlackParadise\CoreAdmin\Domain\Contracts\EntityDefinition\EntityDefinitionContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Fields\FieldContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Fields\RelationFieldContract;
use BlackParadise\CoreAdmin\Domain\Contracts\LocaleProviderContract;
use BlackParadise\CoreAdmin\Domain\Entity\EntityRecord;
use BlackParadise\CoreAdmin\Domain\Fields\MorphFileField;
use BlackParadise\CoreAdmin\Domain\Fields\RelationPathField;
use BlackParadise\CoreAdmin\Domain\Fields\TranslatableField;
use BlackParadise\CoreAdmin\Domain\Query\Criteria;
use BlackParadise\CoreAdmin\Domain\Query\Filter;
use BlackParadise\CoreAdmin\Domain\Query\PaginatedResult;
use BlackParadise\CoreAdmin\Domain\Query\Sort;
use BlackParadise\CoreAdmin\Domain\Repositories\EntityRepositoryInterface;
use BlackParadise\CoreAdmin\Domain\ValueObjects\EntityKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Eloquent implementation of {@see EntityRepositoryInterface}.
 *
 * Translates domain {@see Criteria} (filters, sorts, pagination) into
 * Eloquent query builder calls and wraps the results in {@see EntityRecord}
 * value objects.
 *
 * All filter fields, operators, and sort directions are validated against
 * an allowlist before being passed to the query builder to prevent SQL injection.
 */
final readonly class EloquentEntityRepository implements EntityRepositoryInterface
{
    /**
     * Operators permitted in WHERE clauses.
     *
     * @var array<string>
     */
    private const ALLOWED_OPERATORS = ['=', '!=', '<', '>', '<=', '>=', 'like', 'in', 'not in'];

    /**
     * Field types that are not backed by a column on the host table.
     * These are skipped when building the SELECT projection for list queries.
     *
     * - has_many  : child-table relation, no FK on this table
     * - morph_many: polymorphic one-to-many, no column on host
     * - belongs_to_many: pivot-table relation
     * - has_one   : child-table relation (FK is on the child)
     *
     * Note: belongs_to IS backed by a FK column (name() returns the FK, e.g. city_id)
     * and must NOT appear here — it must be projected so eager-loading can work.
     *
     * @var array<string>
     */
    private const NON_COLUMN_TYPES = ['has_many', 'morph_many', 'belongs_to_many', 'has_one'];

    /**
     * Field types whose backing columns cannot be reliably derived from the field name.
     * When any such type appears in the definition, fall back to SELECT *.
     *
     * - morph_to     : requires two columns (name_id + name_type) that can't be inferred
     * - morph_file   : uses a named morph relation, not a simple column name
     * - relation_path: the value is read from an eager-loaded relation, and the FK that
     *                  drives that eager-load lives on the host model but may not appear
     *                  as any FieldContract in the definition — projecting without it
     *                  would silently break the relation load
     *
     * @var array<string>
     */
    private const PROJECTION_UNSAFE_TYPES = ['morph_to', 'morph_file', 'relation_path'];

    public function __construct(
        private LocaleProviderContract $localeProvider,
    ) {}

    /**
     * Return a paginated result matching the given criteria.
     */
    public function list(EntityDefinitionContract $entityDefinition, Criteria $criteria): PaginatedResult
    {
        /** @var Model $instance */
        $instance  = resolve($entityDefinition->modelClass());
        $query     = $instance->newQuery();

        $eagerLoad = $this->getEagerLoadRelations($entityDefinition);
        if ($eagerLoad !== []) {
            $query->with($eagerLoad);
        }

        // Build field allowlists once; reused by validateField / validateSortableField.
        $fields         = $entityDefinition->fields();
        $allowedFields  = [];
        $sortableFields = [];
        foreach ($fields as $field) {
            $allowedFields[$field->name()] = true;
            if ($field->isSortable()) {
                $sortableFields[$field->name()] = true;
            }
        }

        foreach ($criteria->filters as $filter) {
            $this->applyFilter($query, $filter, $allowedFields);
        }

        // Full-text OR-search across searchFields.
        // % and _ are escaped so user input is treated literally; backslash
        // doubled because it is the default LIKE escape character.
        if ($criteria->search !== null && $criteria->search !== '') {
            $searchFields = $entityDefinition->searchFields();
            if ($searchFields !== []) {
                $escaped = str_replace(
                    ['\\', '%', '_'],
                    ['\\\\', '\\%', '\\_'],
                    $criteria->search,
                );
                $needle = '%' . $escaped . '%';
                $query->where(function ($q) use ($needle, $searchFields, $allowedFields): void {
                    foreach ($searchFields as $fieldName) {
                        $this->validateField($allowedFields, $fieldName);
                        $q->orWhere($fieldName, 'like', $needle);
                    }
                });
            }
        }

        foreach ($criteria->sort as $sort) {
            $this->applySort($query, $sort, $sortableFields, $entityDefinition);
        }

        $page    = max(1, $criteria->page);
        $perPage = max(1, $criteria->perPage);

        $paginator = $query->paginate($perPage, $this->listColumns($entityDefinition), 'page', $page);

        /** @var array<Model> $models */
        $models = $paginator->items();

        return new PaginatedResult(
            items: array_map(fn(Model $m): EntityRecord => $this->toEntityRecord($entityDefinition, $m), $models),
            total: $paginator->total(),
            page: $paginator->currentPage(),
            perPage: $paginator->perPage(),
        );
    }

    /**
     * Find a single record by its primary key.
     * Returns null when not found.
     */
    public function find(EntityDefinitionContract $entityDefinition, EntityKey $key): ?EntityRecordContract
    {
        /** @var Model $instance */
        $instance  = resolve($entityDefinition->modelClass());
        $query     = $instance->newQuery();

        $eagerLoad = $this->getEagerLoadRelations($entityDefinition);
        if ($eagerLoad !== []) {
            $query->with($eagerLoad);
        }

        /** @var Model|null $model */
        $model = $query->find($this->castKey($key));

        if ($model === null) {
            return null;
        }

        return $this->toEntityRecord($entityDefinition, $model);
    }

    /**
     * Check whether a record exists for the given key.
     */
    public function exists(EntityDefinitionContract $entityDefinition, EntityKey $key): bool
    {
        /** @var Model $instance */
        $instance = resolve($entityDefinition->modelClass());

        return $instance->newQuery()
            ->where($entityDefinition->keyField(), $this->castKey($key))
            ->exists();
    }

    /**
     * Column list for list-page SELECT: primary key + every column-backed field.
     *
     * Skips relation types that have no column on the host table (NON_COLUMN_TYPES).
     * Falls back to ['*'] when any field type whose backing columns cannot be derived
     * from the field name alone is present (PROJECTION_UNSAFE_TYPES). This includes
     * relation_path fields — their FK column may not appear as a FieldContract in the
     * definition, so projecting without it would silently break eager-loading; falling
     * back to * ensures all required columns are always selected.
     *
     * @return array<string>
     */
    private function listColumns(EntityDefinitionContract $definition): array
    {
        $columns = [$definition->keyField()];

        foreach ($definition->fields() as $field) {
            $type = $field->type();

            if (in_array($type, self::PROJECTION_UNSAFE_TYPES, true)) {
                return ['*'];
            }

            if (in_array($type, self::NON_COLUMN_TYPES, true)) {
                continue;
            }

            $columns[] = $field->name();
        }

        return array_values(array_unique($columns));
    }

    /**
     * Apply a single {@see Filter} to the query builder after validating
     * the field name and operator against their respective allowlists.
     *
     * @param Builder<Model> $query
     * @param array<string, true> $allowedFields prebuilt hash-set from list()
     * @throws InvalidArgumentException on disallowed field or operator.
     */
    private function applyFilter(
        Builder $query,
        Filter $filter,
        array $allowedFields,
    ): void {
        $this->validateField($allowedFields, $filter->field);
        $this->validateOperator($filter->operator);

        $operator = strtolower($filter->operator);

        if ($operator === 'in') {
            $query->whereIn($filter->field, (array) $filter->value);
            return;
        }

        if ($operator === 'not in') {
            $query->whereNotIn($filter->field, (array) $filter->value);
            return;
        }

        if ($operator === 'like') {
            // Escape user-supplied %, _ and \ so they are treated as literals, not
            // wildcards. The ESCAPE clause declares \ as the escape character.
            // Use the same pattern as the full-text search path above.
            // Field name is already whitelisted via validateField() — safe for whereRaw.
            $escaped = str_replace(
                ['\\', '%', '_'],
                ['\\\\', '\\%', '\\_'],
                (string) $filter->value,
            );
            $wrappedColumn = $query->getGrammar()->wrap($filter->field);
            // Single backslash as the ESCAPE character (SQLite requires single char).
            $query->whereRaw("{$wrappedColumn} LIKE ? ESCAPE ?", [$escaped, '\\']);
            return;
        }

        $query->where($filter->field, $filter->operator, $filter->value);
    }

    /**
     * Apply a single {@see Sort} directive to the query builder after validating
     * the field name and direction.
     *
     * For TranslatableField columns the value is stored as JSON, so sorting on
     * the raw column would order by the entire JSON blob. Instead we extract the
     * locale-specific text using a driver-aware JSON path expression.
     *
     * Driver matrix:
     *   mysql  → JSON_UNQUOTE(JSON_EXTRACT(`col`, '$."locale"'))
     *   sqlite → json_extract("col", '$."locale"')
     *   pgsql  → "col"->>'locale'  (cast to text, NULL-safe)
     *
     * Locale selection (H2):
     *   Use defaultLocale() when it is in availableLocales(), otherwise fall
     *   back to availableLocales()[0]. This prevents silent NULL-sort when
     *   app.locale differs from the locale in which content is stored.
     *
     * Security: column name is whitelisted via isSortable(); direction is
     * whitelisted via validateSortDirection(); locale comes from config (not
     * user input). The locale value is inlined directly into the SQL expression
     * string in all three driver branches. This is safe because the value
     * originates from application config, never from HTTP input.
     *
     * @param Builder<Model> $query
     * @param array<string, true> $sortableFields prebuilt hash-set from list()
     * @throws InvalidArgumentException on disallowed field or direction.
     */
    private function applySort(
        Builder $query,
        Sort $sort,
        array $sortableFields,
        EntityDefinitionContract $definition,
    ): void {
        $this->validateSortableField($sortableFields, $sort->field);
        $this->validateSortDirection($sort->direction);

        // Detect if the sort field maps to a TranslatableField.
        $field = $this->findField($definition, $sort->field);

        if ($field instanceof TranslatableField) {
            $locale    = $this->resolveSortLocale();
            $direction = strtoupper($sort->direction);
            $column    = $sort->field;

            $driver = $this->resolveDriverName($query);

            $expression = $this->translatableSortExpression($driver, $column, $locale, $direction);

            $query->orderByRaw($expression);
            return;
        }

        $query->orderBy($sort->field, $sort->direction);
    }

    /**
     * Build the driver-specific raw SQL fragment used to sort a TranslatableField
     * column by the value at a given locale key.
     *
     * Driver matrix:
     *   mysql   → JSON_UNQUOTE(JSON_EXTRACT(`col`, '$."locale"'))
     *   sqlite  → json_extract("col", '$."locale"')
     *   pgsql   → "col"->>'locale'
     *
     * PostgreSQL uses the standard ->> operator with a plain key name (no $. prefix).
     * The $. prefix is MySQL/SQLite JSON-path syntax and is invalid in PostgreSQL —
     * passing "$.en" would search for a literal key named "$.en", returning NULL for
     * every row and silently breaking the sort.
     *
     * This method is intentionally free of side-effects so it can be unit-tested
     * without a live database connection.
     */
    private function translatableSortExpression(
        string $driver,
        string $column,
        string $locale,
        string $direction,
    ): string {
        return match ($driver) {
            'mysql'  => "JSON_UNQUOTE(JSON_EXTRACT(`{$column}`, '$.\"{$locale}\"')) {$direction}",
            'pgsql'  => "\"{$column}\"->>'{$locale}' {$direction}",
            default  => "json_extract(\"{$column}\", '$.\"{$locale}\"') {$direction}",
        };
    }

    /**
     * Resolve the locale to use when sorting a TranslatableField column.
     *
     * Prefers the default locale when it is present in the available set,
     * so that content edited in the UI locale sorts as expected. Falls back
     * to the first available locale to prevent NULL-sort when app.locale is
     * not in bpadmin.locales (e.g. app.locale=en, content stored only in uk).
     */
    private function resolveSortLocale(): string
    {
        $available = $this->localeProvider->availableLocales();
        $default   = $this->localeProvider->defaultLocale();

        if (in_array($default, $available, true)) {
            return $default;
        }

        return $available[0] ?? $default;
    }

    /**
     * Obtain the database driver name from the query builder's underlying
     * connection in a PHPStan-safe way.
     *
     * {@see \Illuminate\Database\ConnectionInterface} does not declare
     * getDriverName(), but {@see \Illuminate\Database\Connection} (the concrete
     * class used in all built-in drivers) does. We guard with method_exists()
     * to satisfy static analysis without suppression annotations; the fallback
     * 'unknown' triggers the safe sqlite-compatible branch in the caller.
     *
     * @param Builder<Model> $query
     */
    private function resolveDriverName(Builder $query): string
    {
        $connection = $query->getConnection();

        if (method_exists($connection, 'getDriverName')) {
            return (string) $connection->getDriverName();
        }

        return 'unknown';
    }

    /**
     * Find the FieldContract instance for a field name in the definition.
     * Returns null when not found.
     */
    private function findField(EntityDefinitionContract $definition, string $fieldName): ?FieldContract
    {
        foreach ($definition->fields() as $field) {
            if ($field->name() === $fieldName) {
                return $field;
            }
        }
        return null;
    }

    /**
     * Assert that the given field name is present in the prebuilt allowlist.
     *
     * @param array<string, true> $allowed hash-set built once in list()
     * @throws InvalidArgumentException when the field is not in the allowlist.
     */
    private function validateField(array $allowed, string $field): void
    {
        if (!isset($allowed[$field])) {
            throw new InvalidArgumentException(
                "Field '{$field}' is not allowed for filtering.",
            );
        }
    }

    /**
     * Assert that the given field name is present in the prebuilt sortable allowlist.
     *
     * @param array<string, true> $allowed hash-set built once in list()
     * @throws InvalidArgumentException when the field is not sortable.
     */
    private function validateSortableField(array $allowed, string $field): void
    {
        if (!isset($allowed[$field])) {
            throw new InvalidArgumentException(
                "Field '{$field}' is not allowed for sorting.",
            );
        }
    }

    /**
     * Assert that the operator is in the allowlist.
     *
     * @throws InvalidArgumentException on unknown operator.
     */
    private function validateOperator(string $operator): void
    {
        if (!in_array(strtolower($operator), self::ALLOWED_OPERATORS, true)) {
            throw new InvalidArgumentException(
                "Operator '{$operator}' is not allowed.",
            );
        }
    }

    /**
     * Assert that the sort direction is either 'asc' or 'desc'.
     *
     * @throws InvalidArgumentException on invalid direction.
     */
    private function validateSortDirection(string $direction): void
    {
        if (!in_array(strtolower($direction), ['asc', 'desc'], true)) {
            throw new InvalidArgumentException(
                "Sort direction must be 'asc' or 'desc'.",
            );
        }
    }

    /**
     * Cast the key value to the correct PHP type based on EntityKey::$type.
     */
    private function castKey(EntityKey $key): int|string
    {
        return $key->type === 'int' ? (int) $key->value : (string) $key->value;
    }

    /**
     * Collect Eloquent relation names for eager-loading:
     *   - RelationFieldContract → relationName()
     *   - RelationPathField → relationPrefix() (supports nested dot-paths for Laravel)
     *   - MorphFileField → getMorphName() (morph relation stored on host model)
     *
     * Deduplicates so two RelationPathFields under the same relation produce one ->with().
     *
     * @return array<string>
     */
    private function getEagerLoadRelations(EntityDefinitionContract $definition): array
    {
        $relations = [];
        foreach ($definition->fields() as $field) {
            if ($field instanceof RelationFieldContract) {
                $relations[$field->relationName()] = true;
                continue;
            }
            if ($field instanceof RelationPathField) {
                $relations[$field->relationPrefix()] = true;
                continue;
            }
            // MorphFileField extends AbstractField (not RelationFieldContract) but
            // uses a morph relation on the host model — must be eager-loaded for list.
            if ($field instanceof MorphFileField) {
                $relations[$field->getMorphName()] = true;
            }
        }
        return array_keys($relations);
    }

    /**
     * Wrap an Eloquent model in an {@see EntityRecord} domain object.
     *
     * Eloquent relation objects are serialized to plain arrays so that
     * domain records never carry Eloquent model references.
     */
    private function toEntityRecord(EntityDefinitionContract $definition, Model $model): EntityRecord
    {
        $serializedRelations = collect($model->getRelations())
            ->map(fn(mixed $rel) => $rel === null
                ? null
                : ($rel instanceof Model
                    ? $rel->attributesToArray()
                    : (method_exists($rel, 'toArray') ? $rel->toArray() : [])))
            ->all();

        return new EntityRecord(
            definition: $definition,
            attributes: $model->attributesToArray(),
            relations: $serializedRelations,
        );
    }
}
