<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures;

use BlackParadise\CoreAdmin\Domain\Fields\MorphFileField;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\EntityDefinition;

final class TestAccountDefinition extends EntityDefinition
{
    public string $model = TestAccount::class;

    public function resolveName(): string
    {
        return 'test_account';
    }

    public function fields(): array
    {
        return [
            TextField::make('name'),
            MorphFileField::make('avatar')
                ->morphName('files')
                ->storesAs('avatar')
                ->fileModel(TestMorphedFile::class)
                ->directory('avatars'),
            MorphFileField::make('image')
                ->morphName('files')
                ->storesAs('image')
                ->fileModel(TestMorphedFile::class)
                ->directory('images'),
        ];
    }
}
