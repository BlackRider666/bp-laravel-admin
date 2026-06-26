<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures;

use BlackParadise\CoreAdmin\Domain\Fields\HasManyField;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\EntityDefinition;

/**
 * Host definition for deep-relation eager-load tests.
 *
 * 'items' field uses ->embed(TestPublicationItemDefinition::class), which causes
 * EloquentEntityRepository to build nested eager-load paths ('items.tags') and
 * deep-serialize sub-relations in toEntityRecord().
 *
 * Used in EloquentEntityRepositoryDeepRelationTest to verify:
 *   - embedded belongsToMany survives deep-serialization (tags appear inside items)
 *   - non-deep (no embed, no displayEagerLoad) relation stays flat (plain toArray)
 */
final class TestPublicationDefinition extends EntityDefinition
{
    public string $model = TestPublication::class;

    public function resolveName(): string
    {
        return 'test_publication';
    }

    public function fields(): array
    {
        return [
            TextField::make('name'),
            HasManyField::make('items', TestPublicationItem::class)
                ->embed(TestPublicationItemDefinition::class),
        ];
    }
}
