<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Feature\Console\Fixtures\DoctorBoot;

use BlackParadise\CoreAdmin\Domain\Fields\HasManyField;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestComment;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItem;

/** Discovered from the DoctorBoot directory. Violation: a hasMany relation listed in searchFields(). */
final class DoctorBootInvalid extends EntityDefinition
{
    public string $model = TestItem::class;

    public function fields(): array
    {
        return [TextField::make('name'), HasManyField::make('comments', TestComment::class)];
    }

    public function searchFields(): array
    {
        return ['name', 'comments'];
    }
}
