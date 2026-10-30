<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Infrastructure\Events;

use BlackParadise\CoreAdmin\Domain\Contracts\Events\DomainEventContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Events\EventDispatcherContract;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;

/**
 * Delivers an event to the Laravel dispatcher once the OUTERMOST DB transaction
 * has committed (immediately when none is open). A rollback discards the callback.
 */
final readonly class AfterCommitEventDispatcher implements EventDispatcherContract
{
    public function __construct(
        private Dispatcher $dispatcher,
    ) {}

    public function dispatch(DomainEventContract $event): void
    {
        DB::afterCommit(fn(): mixed => $this->dispatcher->dispatch($event));
    }
}
