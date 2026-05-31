<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures;

use BlackParadise\CoreAdmin\Domain\Fields\BelongsToField;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\EntityDefinition;

final class TestPostWithEmbeddedAuthorDefinition extends EntityDefinition
{
    public string $model = TestPost::class;

    public function resolveName(): string
    {
        return 'test_post';
    }

    public function fields(): array
    {
        return [
            TextField::make('title'),
            BelongsToField::make('author_id', TestAuthor::class)
                ->embed(TestAuthorDefinition::class)
                ->owns(),
        ];
    }
}
