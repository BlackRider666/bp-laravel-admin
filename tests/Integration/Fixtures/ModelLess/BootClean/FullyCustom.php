<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ModelLess\BootClean;

use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ModelLess\ArrayMutator;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ModelLess\ArrayRepository;

/** Clean: model-less with repository + mutator, writable. */
final class FullyCustom extends EntityDefinition
{
    public function fields(): array
    {
        return [TextField::make('title')];
    }

    public function repository(): string
    {
        return ArrayRepository::class;
    }

    public function mutator(): string
    {
        return ArrayMutator::class;
    }
}
