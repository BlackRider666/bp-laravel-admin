<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\OwnedCascade;

use BlackParadise\CoreAdmin\Domain\Fields\FileField;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\EntityDefinition;

final class OcNoteDefinition extends EntityDefinition
{
    public string $model = OcNote::class;

    public function resolveName(): string
    {
        return 'oc_note';
    }

    public function fields(): array
    {
        return [
            TextField::make('text'),
            FileField::make('attachment')->disk('archive'),
        ];
    }
}
