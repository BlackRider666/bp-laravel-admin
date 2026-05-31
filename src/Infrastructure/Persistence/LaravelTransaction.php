<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Infrastructure\Persistence;

use BlackParadise\CoreAdmin\Domain\Contracts\TransactionContract;
use Illuminate\Support\Facades\DB;

/**
 * Laravel implementation of {@see TransactionContract}.
 *
 * Делегує до DB::transaction(), яка надає savepoints, retry на deadlock
 * (default 1 attempt), і автоматичний rollback при exception.
 */
final class LaravelTransaction implements TransactionContract
{
    public function executeInTransaction(callable $work): mixed
    {
        return DB::transaction(static fn(): mixed => $work());
    }
}
