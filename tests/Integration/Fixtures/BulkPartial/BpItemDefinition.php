<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\BulkPartial;

use BlackParadise\CoreAdmin\Domain\Fields\FileField;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\EntityDefinition;

final class BpItemDefinition extends EntityDefinition
{
    public string $model = BpItem::class;

    public function resolveName(): string
    {
        return 'bp_item';
    }

    public function fields(): array
    {
        return [
            TextField::make('name'),
            FileField::make('doc')->disk('archive'),
        ];
    }
}
