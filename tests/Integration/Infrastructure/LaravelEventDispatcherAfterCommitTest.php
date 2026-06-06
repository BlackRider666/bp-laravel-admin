<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Infrastructure;

use BlackParadise\CoreAdmin\Domain\Events\EntityDeleted;
use BlackParadise\CoreAdmin\Domain\ValueObjects\EntityKey;
use BlackParadise\LaravelAdmin\Infrastructure\Events\LaravelEventDispatcher;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RuntimeException;

/**
 * A1 (B1): LaravelEventDispatcher must defer events to the outermost transaction boundary.
 *
 * Bug: dispatch() fires the event immediately, even inside an open DB transaction.
 * Fix: wrap dispatch in DB::afterCommit() so the event is delivered only on OUTERMOST
 *      commit (and discarded entirely on rollback).
 */
final class LaravelEventDispatcherAfterCommitTest extends TestCase
{
    // ------------------------------------------------------------------
    // A1 — event NOT delivered when outer transaction rolls back
    // ------------------------------------------------------------------

    public function test_event_not_delivered_when_outer_transaction_rolls_back(): void
    {
        Event::fake();
        $dispatcher = new LaravelEventDispatcher(resolve('events'));

        try {
            DB::transaction(function () use ($dispatcher): never {
                $dispatcher->dispatch(new EntityDeleted(new EntityKey(1, 'int')));
                throw new RuntimeException('force rollback');
            });
        } catch (RuntimeException) {
            // expected
        }

        Event::assertNotDispatched(EntityDeleted::class);
    }

    // ------------------------------------------------------------------
    // A1 — event delivered immediately when no active transaction
    // ------------------------------------------------------------------

    public function test_event_delivered_immediately_without_active_transaction(): void
    {
        Event::fake();
        $dispatcher = new LaravelEventDispatcher(resolve('events'));

        $dispatcher->dispatch(new EntityDeleted(new EntityKey(1, 'int')));

        Event::assertDispatched(EntityDeleted::class);
    }
}
