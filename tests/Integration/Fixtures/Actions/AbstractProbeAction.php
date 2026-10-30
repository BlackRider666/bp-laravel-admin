<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\Actions;

use BlackParadise\CoreAdmin\Domain\Action\ActionContext;
use BlackParadise\CoreAdmin\Domain\Action\ActionResult;
use BlackParadise\CoreAdmin\Domain\Action\ActionScope;
use BlackParadise\CoreAdmin\Domain\Contracts\Action\ActionContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Fields\FieldContract;

/**
 * Test action: implements the current ActionContract plus the extra scopes()/fields()/handle() the
 * ExecuteActionUseCase consumes; resolved from the container (constructor-injected probe).
 */
abstract class AbstractProbeAction implements ActionContract
{
    public function __construct(protected readonly ActionProbe $probe) {}

    public function label(): string
    {
        return ucfirst($this->name());
    }

    public function permission(): ?string
    {
        return null;
    }

    public function confirm(): bool
    {
        return false;
    }

    /** @return array<string, mixed> */
    public function meta(): array
    {
        return [];
    }

    /** @return list<ActionScope> */
    public function scopes(): array
    {
        return [ActionScope::Row, ActionScope::Bulk, ActionScope::Entity];
    }

    /** @return list<FieldContract> */
    public function fields(): array
    {
        return [];
    }

    abstract public function handle(ActionContext $context): ActionResult;
}
