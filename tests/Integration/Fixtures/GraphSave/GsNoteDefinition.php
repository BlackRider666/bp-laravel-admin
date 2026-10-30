<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\GraphSave;

use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\EntityDefinition;

/** `body` is NOT NULL in the table but carries no validation rule: an empty value fails at the DB. */
final class GsNoteDefinition extends EntityDefinition
{
    public string $model = GsNote::class;

    public function resolveName(): string
    {
        return 'gs_note';
    }

    public function fields(): array
    {
        return [
            TextField::make('body'),
        ];
    }
}
