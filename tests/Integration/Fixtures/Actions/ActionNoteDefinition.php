<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\Actions;

use BlackParadise\CoreAdmin\Domain\Contracts\Action\ActionContract;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\EntityDefinition;

final class ActionNoteDefinition extends EntityDefinition
{
    public string $model = ActionNote::class;

    public function resolveName(): string
    {
        return 'action_note';
    }

    public function fields(): array
    {
        return [
            TextField::make('title'),
            TextField::make('status'),
        ];
    }

    /**
     * Actions are declared as class-strings; the container-backed resolver turns them into instances.
     *
     * @return list<class-string<ActionContract>>
     */
    public function actions(): array
    {
        return [
            ArchiveNoteAction::class,
            RowOnlyAction::class,
            SecretAction::class,
            RenameAction::class,
            ExplodeAction::class,
        ];
    }
}
