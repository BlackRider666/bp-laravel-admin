<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Infrastructure\Persistence\Query;

use BlackParadise\CoreAdmin\Domain\Contracts\Fields\FieldContract;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fields whose value is read from a relation (relation_path, morph_to, morph_file): their backing
 * columns cannot be derived from the field name, so the list must SELECT * to keep eager-loading
 * working. Search and sort keep column semantics.
 */
final readonly class RelationPathQueryStrategy implements FieldQueryStrategyContract
{
    public function __construct(private ColumnQueryStrategy $column) {}

    public function isQueryable(): bool
    {
        return true;
    }

    public function applySearch(Builder $query, FieldContract $field, string $term): void
    {
        $this->column->applySearch($query, $field, $term);
    }

    public function applySort(Builder $query, FieldContract $field, string $direction): void
    {
        $this->column->applySort($query, $field, $direction);
    }

    public function projection(FieldContract $field): ?array
    {
        return null;
    }
}
