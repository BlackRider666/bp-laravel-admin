<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ClearFile;

use BlackParadise\CoreAdmin\Domain\Fields\MorphFileField;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestMorphedFile;

final class ClearMorphHostDefinition extends EntityDefinition
{
    public string $model = ClearMorphHost::class;

    public function resolveName(): string
    {
        return 'clear_morph_host';
    }

    public function fields(): array
    {
        return [
            TextField::make('name'),
            $this->morph(MorphFileField::make('optional_file'), 'optional'),
            $this->morph(MorphFileField::make('required_file')->required(), 'required'),
            $this->morph(MorphFileField::make('roc_file')->requiredOnCreate(), 'roc'),
        ];
    }

    private function morph(MorphFileField $field, string $type): MorphFileField
    {
        return $field->morphName('files')
            ->storesAs($type)
            ->fileModel(TestMorphedFile::class)
            ->directory('morph_' . $type)
            ->disk('archive');
    }
}
