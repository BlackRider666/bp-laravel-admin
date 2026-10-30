<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ModelLess;

use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\CoreAdmin\Domain\ValueObjects\EntityCapabilities;
use BlackParadise\LaravelAdmin\EntityDefinition;

/** Read-only, model-less, with a REQUIRED field — lets tests prove capability 404 beats validation 422. */
final class ReadOnlyRequiredReports extends EntityDefinition
{
    public function fields(): array
    {
        return [TextField::make('title')->required()];
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
