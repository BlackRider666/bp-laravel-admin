<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures;

use BlackParadise\CoreAdmin\Domain\Fields\BelongsToManyField;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\EntityDefinition;

final class TestItemWithPivotCallbackDefinition extends EntityDefinition
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
            BelongsToManyField::make('tags', TestTag::class)
                ->pivotPayload(fn($targetId, array $hostAttrs): array => [
                    'approved' => ((int) $targetId) % 2 === 0,
                ]),
        ];
    }
}
