<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\Actions;

use BlackParadise\CoreAdmin\Domain\Action\ActionContext;
use BlackParadise\CoreAdmin\Domain\Action\ActionResult;
use BlackParadise\CoreAdmin\Domain\Action\ActionScope;
use BlackParadise\CoreAdmin\Domain\Fields\NumberField;

/** Row-scope action with a required, bounded numeric "rating" input (1..5). */
final class RateAction extends AbstractProbeAction
{
    public function name(): string
    {
        return 'rate';
    }

    public function scopes(): array
    {
        return [ActionScope::Row];
    }

    public function fields(): array
    {
        return [NumberField::make('rating')->required()->min(1)->max(5)];
    }

    public function handle(ActionContext $context): ActionResult
    {
        $this->probe->record('rate', $context);

        return ActionResult::message('Rated');
    }
}
