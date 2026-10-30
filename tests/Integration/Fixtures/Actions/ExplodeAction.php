<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\Actions;

use BlackParadise\CoreAdmin\Domain\Action\ActionContext;
use BlackParadise\CoreAdmin\Domain\Action\ActionResult;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Writes, then throws: the write must be rolled back. */
final class ExplodeAction extends AbstractProbeAction
{
    public function name(): string
    {
        return 'explode';
    }

    public function handle(ActionContext $context): ActionResult
    {
        $this->probe->record('explode', $context);
        DB::table('action_notes')->update(['status' => 'exploded']);

        throw new RuntimeException('action failed');
    }
}
