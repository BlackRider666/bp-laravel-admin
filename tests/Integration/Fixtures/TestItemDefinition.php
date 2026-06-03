<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures;

use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\EntityDefinition;

/**
 * Minimal EntityDefinition for the TestItem model used in integration tests.
 *
 * Exposes two text fields: `name` and `email`.
 * The entity name resolves to `test_item` via the parent snake_case logic.
 */
final class TestItemDefinition extends EntityDefinition
{
    public string $model = TestItem::class;

    /**
     * Override to use 'test_item' as the entity name.
     *
     * The default snake_case logic on 'TestItemDefinition' would produce
     * 'test_item_definition' which is not the intent for these fixtures.
     */
    public function resolveName(): string
    {
        return 'test_item';
    }

    public function fields(): array
    {
        return [
            TextField::make('name')->sortable(),
            TextField::make('email'),
        ];
    }
}
