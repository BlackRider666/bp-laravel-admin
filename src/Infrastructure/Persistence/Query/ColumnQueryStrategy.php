<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Infrastructure\Persistence\Query;

use BlackParadise\CoreAdmin\Domain\Contracts\Fields\FieldContract;
use Illuminate\Database\Eloquent\Builder;

/**
 * Default strategy: the field is a plain column on the host table.
 */
final class ColumnQueryStrategy implements FieldQueryStrategyContract
{
    public function isQueryable(): bool
    {
        return true;
    }

    public function applySearch(Builder $query, FieldContract $field, string $term): void
    {
        $column = $query->getGrammar()->wrap($field->name());
        $query->orWhereRaw("{$column} LIKE ? " . LikeEscape::CLAUSE, [LikeEscape::contains($term)]);
    }

    public function applySort(Builder $query, FieldContract $field, string $direction): void
    {
        $query->orderBy($field->name(), $direction);
    }

    public function projection(FieldContract $field): array
    {
        return [$field->name()];
    }
}
