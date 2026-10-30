<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Infrastructure\Persistence;

use BlackParadise\CoreAdmin\Domain\Contracts\Persistence\WriteScopeContract;
use BlackParadise\CoreAdmin\Domain\Contracts\TransactionContract;
use BlackParadise\LaravelAdmin\Infrastructure\Events\LaravelEventDispatcher;
use BlackParadise\LaravelAdmin\Support\DeferredFileOperations;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * One atomic write scope: a DB transaction (savepoint when nested) plus the file
 * side-effects and domain events recorded while it is open. Only the OUTERMOST scope
 * flushes file deletions and releases events on commit; a rollback removes recorded
 * uploads, keeps deletions and drops the events of the failed scope.
 *
 * Inside a foreign (ambient) DB transaction the outermost scope defers its file deletions via
 * `afterCommit`; a rollback of the ambient transaction discards them, so nothing is deleted.
 * Known limitation: Laravel has no `afterRollBack` hook, so files uploaded by scopes that
 * already completed inside an ambient transaction that is later rolled back stay on disk as
 * orphans (no data loss; clean up with a periodic orphan sweep).
 */
final class LaravelWriteScope implements WriteScopeContract
{
    private int $depth = 0;

    public function __construct(
        private readonly TransactionContract $transactions,
        private readonly DeferredFileOperations $files,
        private readonly LaravelEventDispatcher $events,
    ) {}

    public function run(callable $work): mixed
    {
        $outermost = $this->depth === 0;
        $this->depth++;
        // Tell the mutator an outer owner flushes files, so it must not self-flush.
        $this->files->beginOuterScope();
        $this->events->begin();
        $mark = $this->files->mark();

        try {
            $result = $this->transactions->executeInTransaction($work);
        } catch (Throwable $e) {
            $this->events->rollback();
            if ($outermost) {
                $this->files->rollback();
            } else {
                $this->files->rollbackTo($mark);
            }

            throw $e;
        } finally {
            $this->files->endOuterScope();
            $this->depth--;
        }

        $this->events->commit();
        if ($outermost) {
            if (DB::transactionLevel() > 0) {
                // Foreign (ambient) transaction: files go only after its real commit; a
                // rollback discards the callback, so deletions never run.
                $deferred = $this->files->detach();
                DB::afterCommit(static fn() => $deferred->commit());
            } else {
                $this->files->commit();
            }
        }

        return $result;
    }
}
