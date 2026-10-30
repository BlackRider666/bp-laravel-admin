<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Infrastructure\Persistence\Concerns;

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Opt-in atomic force delete for soft-deletable models with an owned-children cascade.
 * Hosts use this trait INSTEAD of {@see SoftDeletes} (it brings SoftDeletes itself).
 *
 * Event listeners never open or close transactions, so a plain `forceDelete()` outside a
 * transaction is not atomic. With this trait the whole `forceDelete()` / `forceDeleteQuietly()`
 * (forceDeleting, owned cascade, host DELETE, deleted / forceDeleted listeners) runs in one
 * transaction on the model's connection: a failure, or a delete cancelled by a listener
 * (returns false), rolls the cascade back. Inside an ambient transaction it joins it as a
 * savepoint. Event semantics are those of vanilla SoftDeletes; `forceDeleted` fires inside
 * the transaction (before commit). Soft `delete()` is untouched and opens no transaction.
 *
 * A host class that defines its own `forceDelete()` overrides this trait's wrapper and
 * silently loses atomicity.
 */
trait AtomicSoftDeletes
{
    use SoftDeletes {
        forceDelete as private softDeletesForceDelete;
    }

    /**
     * @return bool|null true on success, false when cancelled (and rolled back)
     */
    public function forceDelete()
    {
        $cancelled = new AtomicForceDeleteCancelled();

        try {
            return $this->getConnection()->transaction(function () use ($cancelled) {
                $result = $this->softDeletesForceDelete();
                if ($result === false) {
                    throw $cancelled;
                }

                return $result;
            });
        } catch (AtomicForceDeleteCancelled $e) {
            if ($e === $cancelled) {
                return false;
            }

            throw $e;
        }
    }
}
