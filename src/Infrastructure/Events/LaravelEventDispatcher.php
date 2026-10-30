<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Infrastructure\Events;

use BlackParadise\CoreAdmin\Domain\Contracts\Events\DomainEventContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Events\EventDispatcherContract;
use BlackParadise\CoreAdmin\Domain\Events\RecordedEvents;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Scope-aware domain event dispatcher.
 *
 * Inside a write scope ({@see \BlackParadise\LaravelAdmin\Infrastructure\Persistence\LaravelWriteScope})
 * events are recorded and released only when the outermost scope commits (dropped on
 * rollback). Outside a scope they go straight to the after-commit delivery.
 * Must be a single shared (scoped) instance so every use case records into the same scope.
 */
final readonly class LaravelEventDispatcher implements EventDispatcherContract
{
    private RecordedEvents $recorded;

    public function __construct(Dispatcher $dispatcher)
    {
        $this->recorded = new RecordedEvents(new AfterCommitEventDispatcher($dispatcher));
    }

    public function dispatch(DomainEventContract $event): void
    {
        $this->recorded->dispatch($event);
    }

    public function begin(): void
    {
        $this->recorded->begin();
    }

    public function commit(): void
    {
        $this->recorded->commit();
    }

    public function rollback(): void
    {
        $this->recorded->rollback();
    }
}
