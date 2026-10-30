<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\OwnedCascade;

use BlackParadise\CoreAdmin\Domain\Fields\BelongsToManyField;
use BlackParadise\CoreAdmin\Domain\Fields\FileField;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestTag;

final class OcChildDefinition extends EntityDefinition
{
    public string $model = OcChild::class;

    public function resolveName(): string
    {
        return 'oc_child';
    }

    public function fields(): array
    {
        return [
            TextField::make('title'),
            FileField::make('doc')->disk('archive'),
            BelongsToManyField::make('tags', TestTag::class),
        ];
    }
}
