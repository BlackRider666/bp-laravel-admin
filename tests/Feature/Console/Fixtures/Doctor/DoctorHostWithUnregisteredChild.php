<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Feature\Console\Fixtures\Doctor;

use BlackParadise\CoreAdmin\Domain\Fields\BelongsToField;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItem;

/** Violation: embeds a definition class that is not registered. */
final class DoctorHostWithUnregisteredChild extends EntityDefinition
{
    public string $model = TestItem::class;

    public function fields(): array
    {
        return [
            TextField::make('name'),
            BelongsToField::make('child_id', TestItem::class)->embed(DoctorUnregisteredChild::class),
        ];
    }
}
