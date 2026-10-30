<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\Actions;

use BlackParadise\CoreAdmin\Domain\Action\ActionContext;
use BlackParadise\CoreAdmin\Domain\Action\ActionResult;
use BlackParadise\CoreAdmin\Domain\ValueObjects\EntityKey;
use Illuminate\Support\Facades\DB;

/** Archives the given notes (row / bulk) or every note (entity scope). */
final class ArchiveNoteAction extends AbstractProbeAction
{
    public function name(): string
    {
        return 'archive';
    }

    public function handle(ActionContext $context): ActionResult
    {
        $this->probe->record('archive', $context);

        $query = DB::table('action_notes');
        if ($context->keys !== []) {
            $query->whereIn('id', array_map(static fn(EntityKey $key): int|string => $key->value, $context->keys));
        }

        return ActionResult::message('Archived ' . $query->update(['status' => 'archived']));
    }
}
