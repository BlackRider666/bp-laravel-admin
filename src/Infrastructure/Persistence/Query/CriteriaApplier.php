<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Infrastructure\Persistence\Query;

use BlackParadise\CoreAdmin\Domain\Contracts\EntityDefinition\EntityDefinitionContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Fields\FieldContract;
use BlackParadise\CoreAdmin\Domain\Query\Criteria;
use BlackParadise\CoreAdmin\Domain\Query\Filter;
use BlackParadise\CoreAdmin\Domain\Query\Sort;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Translates domain {@see Criteria} (filters, search, sort) into Eloquent builder calls.
 * Field-type specifics (search/sort SQL) are delegated to {@see FieldQueryRegistry} strategies.
 *
 * All filter fields, operators and sort directions are validated against allowlists.
 */
final readonly class CriteriaApplier
{
    /** @var array<string> */
    private const ALLOWED_OPERATORS = ['=', '!=', '<', '>', '<=', '>=', 'like', 'in', 'not in'];

    public function __construct(private FieldQueryRegistry $queries) {}

    /**
     * @param Builder<Model> $query
     */
    public function apply(Builder $query, EntityDefinitionContract $definition, Criteria $criteria): void
    {
        $fields  = [];
        $allowed = [];
        foreach ($definition->fields() as $field) {
            $fields[$field->name()]  = $field;
            $allowed[$field->name()] = true;
        }

        foreach ($criteria->filters as $filter) {
            $this->applyFilter($query, $filter, $allowed);
        }

        if ($criteria->search !== null && $criteria->search !== '') {
            $this->applySearch($query, $definition, $fields, $criteria->search);
        }

        foreach ($criteria->sort as $sort) {
            $this->applySort($query, $sort, $fields);
        }
    }

    /**
     * @param Builder<Model> $query
     * @param array<string, FieldContract> $fields
     */
    private function applySearch(Builder $query, EntityDefinitionContract $definition, array $fields, string $term): void
    {
        $searchFields = $definition->searchFields();
        if ($searchFields === []) {
            return;
        }

        foreach ($searchFields as $name) {
            if (!isset($fields[$name])) {
                throw new InvalidArgumentException("Field '{$name}' is not allowed for filtering.");
            }
        }

        $query->where(function (Builder $q) use ($searchFields, $fields, $term): void {
            foreach ($searchFields as $name) {
                $field = $fields[$name];
                $this->queries->for($field)->applySearch($q, $field, $term);
            }
        });
    }

    /**
     * @param Builder<Model> $query
     * @param array<string, FieldContract> $fields
     */
    private function applySort(Builder $query, Sort $sort, array $fields): void
    {
        $field = $fields[$sort->field] ?? null;
        if ($field === null || !$field->isSortable()) {
            throw new InvalidArgumentException("Field '{$sort->field}' is not allowed for sorting.");
        }
        if (!in_array(strtolower($sort->direction), ['asc', 'desc'], true)) {
            throw new InvalidArgumentException("Sort direction must be 'asc' or 'desc'.");
        }

        $this->queries->for($field)->applySort($query, $field, $sort->direction);
    }

    /**
     * @param Builder<Model> $query
     * @param array<string, true> $allowed
     */
    private function applyFilter(Builder $query, Filter $filter, array $allowed): void
    {
        if (!isset($allowed[$filter->field])) {
            throw new InvalidArgumentException("Field '{$filter->field}' is not allowed for filtering.");
        }

        $operator = strtolower($filter->operator);
        if (!in_array($operator, self::ALLOWED_OPERATORS, true)) {
            throw new InvalidArgumentException("Operator '{$filter->operator}' is not allowed.");
        }

        if ($operator === 'in') {
            $query->whereIn($filter->field, (array) $filter->value);
            return;
        }

        if ($operator === 'not in') {
            $query->whereNotIn($filter->field, (array) $filter->value);
            return;
        }

        if ($operator === 'like') {
            $column = $query->getGrammar()->wrap($filter->field);
            $query->whereRaw("{$column} LIKE ? " . LikeEscape::CLAUSE, [LikeEscape::escape((string) $filter->value)]);
            return;
        }

        $query->where($filter->field, $filter->operator, $filter->value);
    }
}
