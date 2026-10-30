<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ModelLess\BootEmbeddedChild;

use BlackParadise\CoreAdmin\Domain\Fields\BelongsToField;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItem;

/** Violation: embeds the model-less {@see ChildSource} as a child definition. */
final class HostWithEmbeddedChild extends EntityDefinition
{
    public string $model = TestItem::class;

    public function fields(): array
    {
        return [
            TextField::make('name'),
            BelongsToField::make('child_id', TestItem::class)->embed(ChildSource::class),
        ];
    }
}
