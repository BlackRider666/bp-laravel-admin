<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ModelLess;

use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\EntityDefinition;

/** A31: legacy `$creatable = false` flag on a model-less, otherwise writable definition. */
final class NonCreatableReports extends EntityDefinition
{
    public bool $creatable = false;

    public function fields(): array
    {
        return [TextField::make('title')->required()];
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
