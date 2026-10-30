<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ClearFile;

use BlackParadise\CoreAdmin\Domain\Fields\FileField;
use BlackParadise\CoreAdmin\Domain\Fields\ImageField;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\EntityDefinition;

/** All files live on the non-default `archive` disk. `name` is NOT NULL in the table. */
final class ClearDocDefinition extends EntityDefinition
{
    public string $model = ClearDoc::class;

    public function resolveName(): string
    {
        return 'clear_doc';
    }

    public function fields(): array
    {
        return [
            TextField::make('name'),
            FileField::make('optional_doc')->disk('archive'),
            ImageField::make('optional_img')->disk('archive'),
            FileField::make('required_doc')->required()->disk('archive'),
            FileField::make('roc_doc')->requiredOnCreate()->disk('archive'),
        ];
    }
}
