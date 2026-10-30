<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\Actions;

use BlackParadise\CoreAdmin\Domain\Action\ActionContext;
use BlackParadise\CoreAdmin\Domain\Action\ActionResult;
use BlackParadise\CoreAdmin\Domain\Action\ActionScope;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;

/** Entity-scope action with a required "title" input. */
final class RenameAction extends AbstractProbeAction
{
    public function name(): string
    {
        return 'rename';
    }

    public function scopes(): array
    {
        return [ActionScope::Entity];
    }

    public function fields(): array
    {
        return [TextField::make('title')->required()];
    }

    public function handle(ActionContext $context): ActionResult
    {
        $this->probe->record('rename', $context);
        $title = $context->input['title'] ?? '';

        return ActionResult::message('Renamed to ' . (is_string($title) ? $title : ''));
    }
}
