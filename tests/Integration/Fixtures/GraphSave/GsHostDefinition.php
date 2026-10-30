<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\GraphSave;

use BlackParadise\CoreAdmin\Domain\Fields\FileField;
use BlackParadise\CoreAdmin\Domain\Fields\HasManyField;
use BlackParadise\CoreAdmin\Domain\Fields\HasOneField;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\EntityDefinition;

/** Host with a column file, validated hasMany children and a hasOne child whose NOT NULL column has no rule. */
final class GsHostDefinition extends EntityDefinition
{
    public string $model = GsHost::class;

    public function resolveName(): string
    {
        return 'gs_host';
    }

    public function fields(): array
    {
        return [
            TextField::make('name'),
            FileField::make('attachment')->disk('public'),
            HasManyField::make('items', GsItem::class)->embed(GsItemDefinition::class),
            HasOneField::make('note', GsNote::class)->embed(GsNoteDefinition::class),
        ];
    }
}
