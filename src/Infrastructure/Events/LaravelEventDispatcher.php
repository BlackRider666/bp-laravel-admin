<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Infrastructure\Events;

use BlackParadise\CoreAdmin\Domain\Contracts\Events\DomainEventContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Events\EventDispatcherContract;
use Illuminate\Contracts\Events\Dispatcher;

final readonly class LaravelEventDispatcher implements EventDispatcherContract
{
    public function __construct(
        private Dispatcher $dispatcher,
    ) {}

    public function dispatch(DomainEventContract $event): void
    {
        $this->dispatcher->dispatch($event);
    }
}
