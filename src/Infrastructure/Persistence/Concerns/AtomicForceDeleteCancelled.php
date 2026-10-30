<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Infrastructure\Persistence\Concerns;

use Exception;

/**
 * Internal sentinel used by {@see AtomicSoftDeletes} to roll a transaction back when a
 * listener cancels the force delete. Never escapes the trait.
 *
 * @internal
 */
final class AtomicForceDeleteCancelled extends Exception {}
