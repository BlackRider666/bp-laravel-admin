<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Infrastructure\Persistence;

use BlackParadise\CoreAdmin\Domain\Contracts\Entity\EntityRecordContract;
use BlackParadise\CoreAdmin\Domain\Contracts\EntityDefinition\EntityDefinitionContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Fields\FieldContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Fields\RelationFieldContract;
use BlackParadise\CoreAdmin\Domain\Entity\EntityRecord;
use BlackParadise\CoreAdmin\Domain\Fields\RelationPathField;
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
final class EloquentEntityRepository implements EntityRepositoryInterface
{
    /**
     * Operators permitted in WHERE clauses.
     *
     * @var array<string>
     */
    private const ALLOWED_OPERATORS = ['=', '!=', '<', '>', '<=', '>=', 'like', 'in', 'not in'];

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

        foreach ($criteria->filters as $filter) {
            $this->applyFilter($query, $filter, $entityDefinition);
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
                $query->where(function ($q) use ($needle, $searchFields, $entityDefinition): void {
                    foreach ($searchFields as $fieldName) {
                        $this->validateField($entityDefinition, $fieldName);
                        $q->orWhere($fieldName, 'like', $needle);
                    }
                });
            }
        }

        foreach ($criteria->sort as $sort) {
            $this->applySort($query, $sort, $entityDefinition);
        }

        $page    = max(1, $criteria->page);
        $perPage = max(1, $criteria->perPage);

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

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
     * Apply a single {@see Filter} to the query builder after validating
     * the field name and operator against their respective allowlists.
     *
     * @param Builder<Model> $query
     * @throws InvalidArgumentException on disallowed field or operator.
     */
    private function applyFilter(
        Builder $query,
        Filter $filter,
        EntityDefinitionContract $definition,
    ): void {
        $this->validateField($definition, $filter->field);
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

        $query->where($filter->field, $filter->operator, $filter->value);
    }

    /**
     * Apply a single {@see Sort} directive to the query builder after validating
     * the field name and direction.
     *
     * @param Builder<Model> $query
     * @throws InvalidArgumentException on disallowed field or direction.
     */
    private function applySort(
        Builder $query,
        Sort $sort,
        EntityDefinitionContract $definition,
    ): void {
        $this->validateField($definition, $sort->field);
        $this->validateSortDirection($sort->direction);

        $query->orderBy($sort->field, $sort->direction);
    }

    /**
     * Assert that the given field name is declared in the entity definition.
     *
     * @throws InvalidArgumentException when the field is not in the definition.
     */
    private function validateField(EntityDefinitionContract $definition, string $field): void
    {
        $fields = array_map(fn(FieldContract $f): string => $f->name(), $definition->fields());

        if (!in_array($field, $fields, true)) {
            throw new InvalidArgumentException(
                "Field '{$field}' is not allowed for filtering.",
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
