<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Support;

use BlackParadise\CoreAdmin\Domain\Contracts\EntityDefinition\EntityDefinitionContract;
use BlackParadise\CoreAdmin\Domain\Fields\Base\AbstractRelationField;
use BlackParadise\CoreAdmin\Domain\Fields\HasOneField;
use Illuminate\Support\Str;

final class HasOneFkResolver
{
    public function resolve(EntityDefinitionContract $definition, AbstractRelationField $field): string
    {
        if ($field instanceof HasOneField && $field->getForeignKey() !== null) {
            return $field->getForeignKey();
        }

        return Str::snake(class_basename($definition->modelClass())) . '_id';
    }
}
