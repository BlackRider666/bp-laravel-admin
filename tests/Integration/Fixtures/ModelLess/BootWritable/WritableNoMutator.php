<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ModelLess\BootWritable;

use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ModelLess\ArrayRepository;

/** Violation: model-less + repository, no mutator, yet capabilities stay writable (default all()). */
final class WritableNoMutator extends EntityDefinition
{
    public function fields(): array
    {
        return [TextField::make('title')];
    }

    public function repository(): string
    {
        return ArrayRepository::class;
    }
}
