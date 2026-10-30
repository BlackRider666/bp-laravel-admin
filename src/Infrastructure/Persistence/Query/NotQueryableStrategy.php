<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Infrastructure\Persistence\Query;

use BlackParadise\CoreAdmin\Domain\Contracts\Fields\FieldContract;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * Side-effect relations without a column on the host table (has_many, has_one, belongs_to_many,
 * morph_many). They cannot be searched, sorted or filtered; definitions that try are rejected
 * by {@see FieldQueryRegistry::assertQueryable()}.
 */
final class NotQueryableStrategy implements FieldQueryStrategyContract
{
    public function isQueryable(): bool
    {
        return false;
    }

    public function applySearch(Builder $query, FieldContract $field, string $term): void
    {
        throw new InvalidArgumentException("Field '{$field->name()}' is not queryable and cannot be searched.");
    }

    public function applySort(Builder $query, FieldContract $field, string $direction): void
    {
        throw new InvalidArgumentException("Field '{$field->name()}' is not queryable and cannot be sorted.");
    }

    public function projection(FieldContract $field): array
    {
        return [];
    }
}
