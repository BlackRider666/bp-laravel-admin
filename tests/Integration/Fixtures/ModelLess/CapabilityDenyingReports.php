<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ModelLess;

use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\EntityDefinition;

/** Advertises full capabilities, but its mutator throws CapabilityDeniedException. */
final class CapabilityDenyingReports extends EntityDefinition
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
        return CapabilityDenyingMutator::class;
    }
}
