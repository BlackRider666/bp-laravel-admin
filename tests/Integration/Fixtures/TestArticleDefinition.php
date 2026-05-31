<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures;

use BlackParadise\CoreAdmin\Domain\Fields\TranslatableField;
use BlackParadise\LaravelAdmin\EntityDefinition;

final class TestArticleDefinition extends EntityDefinition
{
    public string $model = TestArticle::class;

    public function resolveName(): string
    {
        return 'test_article';
    }

    public function fields(): array
    {
        return [
            TranslatableField::make('title'),
        ];
    }
}
