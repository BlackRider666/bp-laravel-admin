<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ModelLess;

use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItem;

/** A32: Eloquent model for writes + custom repository for reads (WRP shape). */
final class CustomReadItems extends EntityDefinition
{
    public string $model = TestItem::class;

    public function fields(): array
    {
        return [TextField::make('name'), TextField::make('email')];
    }

    public function repository(): string
    {
        return ArrayRepository::class;
    }
}
