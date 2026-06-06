<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Infrastructure\Events;

use BlackParadise\CoreAdmin\Domain\Contracts\Events\DomainEventContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Events\EventDispatcherContract;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;

final readonly class LaravelEventDispatcher implements EventDispatcherContract
{
    public function __construct(
        private Dispatcher $dispatcher,
    ) {}

    public function dispatch(DomainEventContract $event): void
    {
        // Defer delivery until the OUTERMOST DB transaction commits. With no
        // active transaction the manager runs the callback immediately. On
        // rollback the queued callback is discarded — no phantom events.
        DB::afterCommit(fn(): mixed => $this->dispatcher->dispatch($event));
    }
}
