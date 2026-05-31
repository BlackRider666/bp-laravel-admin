<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures;

use BlackParadise\CoreAdmin\Domain\Fields\HiddenField;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\EntityDefinition;

/**
 * Minimal EntityDefinition for TestProfile — used as the embedded definition
 * target in hasOne-embed integration tests.
 *
 * The FK column (test_item_id) is declared as HiddenField so that
 * filterAttributes lets it through when the controller's defer loop injects
 * the host id after the host update/create completes.
 */
final class TestProfileDefinition extends EntityDefinition
{
    public string $model = TestProfile::class;

    public function resolveName(): string
    {
        return 'test_profile';
    }

    public function fields(): array
    {
        return [
            HiddenField::make('test_item_id'),
            TextField::make('bio'),
        ];
    }
}
