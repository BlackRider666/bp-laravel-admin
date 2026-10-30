<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\Actions;

use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\EntityDefinition;

final class RatedNoteDefinition extends EntityDefinition
{
    public string $model = ActionNote::class;

    public function resolveName(): string
    {
        return 'rated_note';
    }

    public function fields(): array
    {
        return [TextField::make('title')];
    }

    public function actions(): array
    {
        return [RateAction::class];
    }
}
