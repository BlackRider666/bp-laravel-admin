<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures;

use BlackParadise\CoreAdmin\Domain\Fields\BelongsToManyField;
use BlackParadise\CoreAdmin\Domain\Fields\HasManyField;
use BlackParadise\CoreAdmin\Domain\Fields\HasOneField;
use BlackParadise\CoreAdmin\Domain\Fields\MorphManyField;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\EntityDefinition;

/**
 * EntityDefinition fixture for TestItem with every side-effect relation kind:
 * belongsToMany (tags), hasMany (comments), hasOne (profile), morphMany (morphComments).
 *
 * Used by EloquentEntityMutatorRelationsTest to drive applyRelations() branches.
 */
final class TestItemWithRelationsDefinition extends EntityDefinition
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
            BelongsToManyField::make('tags', TestTag::class),
            HasManyField::make('comments', TestComment::class),
            HasOneField::make('profile', TestProfile::class),
            MorphManyField::make('morphComments', TestMorphComment::class),
        ];
    }
}
