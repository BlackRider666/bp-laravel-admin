<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ModelLess\BootRelationTarget;

use BlackParadise\CoreAdmin\Domain\Fields\BelongsToField;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItem;

/** Violation: Eloquent relation whose target is the model-less {@see LookupSource} definition. */
final class OrderWithLookup extends EntityDefinition
{
    public string $model = TestItem::class;

    public function fields(): array
    {
        return [
            TextField::make('name'),
            BelongsToField::make('lookup_id', LookupSource::class),
        ];
    }
}
