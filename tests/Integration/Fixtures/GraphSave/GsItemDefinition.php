<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\GraphSave;

use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\EntityDefinition;

final class GsItemDefinition extends EntityDefinition
{
    public string $model = GsItem::class;

    public function resolveName(): string
    {
        return 'gs_item';
    }

    public function fields(): array
    {
        return [
            TextField::make('title')->required(),
        ];
    }
}
