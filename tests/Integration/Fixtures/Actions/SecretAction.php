<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\Actions;

use BlackParadise\CoreAdmin\Domain\Action\ActionContext;
use BlackParadise\CoreAdmin\Domain\Action\ActionResult;

/** Guarded by an explicit permission() instead of the default "action.<name>" ability. */
final class SecretAction extends AbstractProbeAction
{
    public const PERMISSION = 'notes.secret';

    public function name(): string
    {
        return 'secret';
    }

    public function permission(): string
    {
        return self::PERMISSION;
    }

    public function handle(ActionContext $context): ActionResult
    {
        $this->probe->record('secret', $context);

        return ActionResult::message('Secret done');
    }
}
