<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures;

use BlackParadise\CoreAdmin\Domain\Fields\MorphFileField;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\EntityDefinition;

/**
 * EntityDefinition backed by {@see TestAccountThatFailsDelete}.
 * Used to verify that file paths collected inside the transaction are NOT deleted
 * from disk when the transaction rolls back.
 */
final class TestAccountThatFailsDeleteDefinition extends EntityDefinition
{
    public string $model = TestAccountThatFailsDelete::class;

    public function resolveName(): string
    {
        return 'test_account_fail';
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
        ];
    }
}
