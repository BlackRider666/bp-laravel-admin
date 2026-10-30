<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ModelLess;

use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\CoreAdmin\Domain\ValueObjects\EntityCapabilities;
use BlackParadise\LaravelAdmin\EntityDefinition;

/** A33: model-less, custom repository, NO mutator, explicitly read-only => valid. */
final class ReadOnlyReports extends EntityDefinition
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
