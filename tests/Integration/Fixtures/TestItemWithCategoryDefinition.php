<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures;

use BlackParadise\CoreAdmin\Domain\Fields\BelongsToField;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\EntityDefinition;

/**
 * EntityDefinition fixture for TestItem with a BelongsTo category relation.
 *
 * Used in eager-loading integration tests to verify that RelationFieldContract
 * fields cause EloquentEntityRepository to issue ->with() on the query.
 */
final class TestItemWithCategoryDefinition extends EntityDefinition
{
    public string $model = TestItem::class;

    public function resolveName(): string
    {
        return 'test_item';
    }

    public function fields(): array
    {
        return [
            TextField::make('name'),
            TextField::make('email'),
            BelongsToField::make('category_id', TestCategory::class)
                ->withDisplayField('title'),
        ];
    }
}
