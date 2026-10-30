<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Feature\Console\Fixtures\Doctor;

use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItem;

/** Valid definition that counts how often fields() is built. */
final class DoctorFieldsCounter extends EntityDefinition
{
    public static int $fieldsCalls = 0;

    public string $model = TestItem::class;

    public function fields(): array
    {
        self::$fieldsCalls++;

        return [TextField::make('name')];
    }

    public function searchFields(): array
    {
        return ['name'];
    }
}
