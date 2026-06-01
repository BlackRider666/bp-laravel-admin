<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures;

use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\EntityDefinition;

/**
 * Minimal EntityDefinition for TestProfile — used as the embedded definition
 * target in hasOne-embed integration tests.
 *
 * The FK column (test_item_id) is intentionally NOT declared: EmbeddedChildWriter
 * persists the child through the host relation, so Eloquent assigns the FK
 * automatically and the author never has to expose it as a form field.
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
            TextField::make('bio'),
        ];
    }
}
