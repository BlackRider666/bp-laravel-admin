<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Feature\Console\Fixtures\Doctor;

use BlackParadise\CoreAdmin\Domain\Fields\HasManyField;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestComment;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItem;

/** Violation: a hasMany relation is not queryable but is listed in searchFields(). */
final class DoctorSearchableRelation extends EntityDefinition
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
