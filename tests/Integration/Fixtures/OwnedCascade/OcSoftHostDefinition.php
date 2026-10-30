<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\OwnedCascade;

use BlackParadise\CoreAdmin\Domain\Fields\HasManyField;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\EntityDefinition;

final class OcSoftHostDefinition extends EntityDefinition
{
    public string $model = OcSoftHost::class;

    public function resolveName(): string
    {
        return 'oc_soft_host';
    }

    public function fields(): array
    {
        return [
            TextField::make('name'),
            HasManyField::make('items', OcChild::class)->embed(OcChildDefinition::class)->owns(),
        ];
    }
}
