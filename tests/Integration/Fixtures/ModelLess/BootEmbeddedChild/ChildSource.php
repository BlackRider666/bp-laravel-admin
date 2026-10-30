<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ModelLess\BootEmbeddedChild;

use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ModelLess\ArrayMutator;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ModelLess\ArrayRepository;

/** Valid on its own (fully custom) — the violation is embedding it under an Eloquent host. */
final class ChildSource extends EntityDefinition
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
