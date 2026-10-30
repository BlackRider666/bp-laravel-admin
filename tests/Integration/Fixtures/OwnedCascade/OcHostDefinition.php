<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\OwnedCascade;

use BlackParadise\CoreAdmin\Domain\Fields\HasManyField;
use BlackParadise\CoreAdmin\Domain\Fields\MorphManyField;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\EntityDefinition;

final class OcHostDefinition extends EntityDefinition
{
    public string $model = OcHost::class;

    public function resolveName(): string
    {
        return 'oc_host';
    }

    public function fields(): array
    {
        return [
            TextField::make('name'),
            HasManyField::make('items', OcChild::class)->embed(OcChildDefinition::class)->owns(),
            MorphManyField::make('notes', OcNote::class)->embed(OcNoteDefinition::class)->owns(),
        ];
    }
}
