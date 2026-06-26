<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures;

use BlackParadise\CoreAdmin\Domain\Fields\BelongsToManyField;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\EntityDefinition;

/**
 * Embedded child definition for TestPublication's 'items' HasMany relation.
 *
 * Contains a belongsToMany 'tags' field — used to verify that
 * EloquentEntityRepository builds nested eager-load paths and
 * deep-serializes sub-relations when the parent field has ->embed().
 */
final class TestPublicationItemDefinition extends EntityDefinition
{
    public string $model = TestPublicationItem::class;

    public function resolveName(): string
    {
        return 'test_publication_item';
    }

    public function fields(): array
    {
        return [
            TextField::make('title'),
            BelongsToManyField::make('tags', TestTag::class),
        ];
    }
}
