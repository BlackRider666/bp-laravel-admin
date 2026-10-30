<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ModelLess\BootClean;

use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\CoreAdmin\Domain\ValueObjects\EntityCapabilities;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ModelLess\ArrayRepository;

/** Clean: model-less read-only without mutator. */
final class ReadOnlyNoMutator extends EntityDefinition
{
    public function fields(): array
    {
        return [TextField::make('title')];
    }

    public function repository(): string
    {
        return ArrayRepository::class;
    }

    public function capabilities(): EntityCapabilities
    {
        return EntityCapabilities::readOnly();
    }
}
