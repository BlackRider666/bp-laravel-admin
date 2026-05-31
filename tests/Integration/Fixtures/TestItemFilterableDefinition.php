<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures;

use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\EntityDefinition;

/**
 * EntityDefinition fixture with one filterable field (`name`) and one non-filterable field (`email`).
 *
 * Used by filter-related integration tests to verify that only fields marked
 * filterable() are accepted as filter inputs from the request.
 */
final class TestItemFilterableDefinition extends EntityDefinition
{
    public string $model = TestItem::class;

    /**
     * Override to reuse the same `test_item` entity name and the `test_items` table
     * that CreatesTestItemTable creates in-memory.
     */
    public function resolveName(): string
    {
        return 'test_item';
    }

    public function fields(): array
    {
        return [
            TextField::make('name')->filterable(),
            TextField::make('email'),
        ];
    }

    public function searchFields(): array
    {
        return ['name', 'email'];
    }
}
