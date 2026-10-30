<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Feature\Console\Fixtures\Doctor;

use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItem;

/** Valid definition, but never registered by the tests (embedded by {@see DoctorHostWithUnregisteredChild}). */
final class DoctorUnregisteredChild extends EntityDefinition
{
    public string $model = TestItem::class;

    public function fields(): array
    {
        return [TextField::make('name')];
    }
}
