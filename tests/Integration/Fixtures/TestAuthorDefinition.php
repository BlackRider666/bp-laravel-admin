<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures;

use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\EntityDefinition;

final class TestAuthorDefinition extends EntityDefinition
{
    public string $model = TestAuthor::class;

    public function resolveName(): string
    {
        return 'test_author';
    }

    public function fields(): array
    {
        return [
            TextField::make('name'),
            TextField::make('email'),
        ];
    }
}
