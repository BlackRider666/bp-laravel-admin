<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ModelLess;

use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\EntityDefinition;

/** A33: model-less (no $model) definition with a custom repository AND mutator => fully writable. */
final class Reports extends EntityDefinition
{
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
