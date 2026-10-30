<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\Actions;

use BlackParadise\CoreAdmin\Domain\Action\ActionContext;
use BlackParadise\CoreAdmin\Domain\Action\ActionResult;
use BlackParadise\CoreAdmin\Domain\Action\ActionScope;

final class RowOnlyAction extends AbstractProbeAction
{
    public function name(): string
    {
        return 'row_only';
    }

    public function scopes(): array
    {
        return [ActionScope::Row];
    }

    public function handle(ActionContext $context): ActionResult
    {
        $this->probe->record('row_only', $context);

        return ActionResult::message('Row done');
    }
}
