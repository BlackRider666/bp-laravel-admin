<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Feature\Console\Fixtures\Doctor;

use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItem;

/** Valid: searchable text column on an Eloquent model. */
final class DoctorCleanItem extends EntityDefinition
{
    public string $model = TestItem::class;

    public function fields(): array
    {
        return [TextField::make('name')];
    }

    public function searchFields(): array
    {
        return ['name'];
    }
}
