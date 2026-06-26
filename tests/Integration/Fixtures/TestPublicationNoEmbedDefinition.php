<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures;

use BlackParadise\CoreAdmin\Domain\Fields\HasManyField;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\EntityDefinition;

/**
 * Variant of TestPublicationDefinition whose 'items' HasManyField has NO ->embed()
 * and NO ->withDisplayEagerLoad(). Used to verify that deep-serialization is
 * strictly opt-in: serialized items must NOT carry a 'tags' sub-relation key.
 */
final class TestPublicationNoEmbedDefinition extends EntityDefinition
{
    public string $model = TestPublication::class;

    public function resolveName(): string
    {
        return 'test_publication_no_embed';
    }

    public function fields(): array
    {
        return [
            TextField::make('name'),
            HasManyField::make('items', TestPublicationItem::class),
        ];
    }
}
