<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Infrastructure\Persistence\Query;

use BlackParadise\CoreAdmin\Domain\Contracts\Fields\FieldContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * How a single field type takes part in list queries: search, sort and SELECT projection.
 * Resolved per field via {@see FieldQueryRegistry}.
 */
interface FieldQueryStrategyContract
{
    /**
     * Whether the field can be searched / sorted / filtered at all.
     */
    public function isQueryable(): bool;

    /**
     * Add an OR-ed search predicate for $term to $query (which is the inner group builder).
     *
     * @param Builder<Model> $query
     */
    public function applySearch(Builder $query, FieldContract $field, string $term): void;

    /**
     * Add an ORDER BY for the field.
     *
     * @param Builder<Model> $query
     */
    public function applySort(Builder $query, FieldContract $field, string $direction): void;

    /**
     * Columns the field needs in the list SELECT; [] when it is not column-backed;
     * null when they cannot be derived (caller falls back to SELECT *).
     *
     * @return list<string>|null
     */
    public function projection(FieldContract $field): ?array;
}
