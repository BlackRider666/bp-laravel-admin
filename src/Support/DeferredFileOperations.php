<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Support;

use BlackParadise\CoreAdmin\Domain\Contracts\Files\FileStorageProviderContract;
use Throwable;

/**
 * Request-scoped collector of file side-effects that must run on the OUTERMOST
 * transaction boundary, not inside a nested savepoint.
 *
 *  - uploads:   files written to disk this request — deleted if the write rolls back.
 *  - deletions: replaced/old/owned files — deleted only after a successful commit.
 *
 * **Outer-scope protocol:**
 *
 *   Callers that wrap the mutator in their OWN outer transaction (e.g. the
 *   {@see \BlackParadise\LaravelAdmin\Http\Controllers\AdminEntityController}) MUST:
 *     1. Call `beginOuterScope()` before starting the outer transaction.
 *     2. Call `endOuterScope()` in a finally block (before or after the
 *        corresponding `commit()/rollback()` call).
 *
 *   When `hasOuterScope()` returns true the mutator skips its own self-flush and
 *   defers to the outer caller. When it returns false (direct / CLI / seeder calls)
 *   the mutator self-flushes after its own transaction boundary.
 *
 * Direct (non-controller) callers of the mutator where NO outer scope is
 * registered MUST flush this collector themselves, or rely on the mutator's
 * self-flush when `hasOuterScope() === false`.
 */
final class DeferredFileOperations
{
    /** @var list<array{path: string, disk: ?string}> */
    private array $uploads = [];

    /** @var list<array{path: string, disk: ?string}> */
    private array $deletions = [];

    /** Number of registered outer-scope owners (typically 0 or 1). */
    private int $outerScopeDepth = 0;

    public function __construct(
        private readonly FileStorageProviderContract $fileStorage,
    ) {}

    /**
     * Signal that an outer transaction owner (e.g. the controller) has started
     * and will handle the final flush. Must be paired with endOuterScope().
     */
    public function beginOuterScope(): void
    {
        $this->outerScopeDepth++;
    }

    /**
     * Signal that the outer transaction owner has finished. Decrements the
     * depth counter; depth never goes below 0.
     */
    public function endOuterScope(): void
    {
        $this->outerScopeDepth = max(0, $this->outerScopeDepth - 1);
    }

    /**
     * Returns true when at least one outer-scope owner has been registered
     * and not yet released. The mutator uses this to decide whether to
     * self-flush or defer to the caller.
     */
    public function hasOuterScope(): bool
    {
        return $this->outerScopeDepth > 0;
    }

    public function recordUpload(string $path, ?string $disk): void
    {
        if ($path !== '') {
            $this->uploads[] = ['path' => $path, 'disk' => $disk];
        }
    }

    public function recordDeletion(string $path, ?string $disk): void
    {
        if ($path !== '') {
            $this->deletions[] = ['path' => $path, 'disk' => $disk];
        }
    }

    /**
     * Position mark for a nested write scope: how many uploads/deletions are recorded now.
     *
     * @return array{int, int}
     */
    public function mark(): array
    {
        return [count($this->uploads), count($this->deletions)];
    }

    /**
     * A nested scope failed (its DB work was rolled back by a savepoint): compensate only what
     * it recorded after the mark — delete its uploads, discard its deletions. Earlier ops stay.
     *
     * @param array{int, int} $mark
     */
    public function rollbackTo(array $mark): void
    {
        [$uploads, $deletions] = $mark;
        $this->deletePaths(array_slice($this->uploads, $uploads));
        $this->uploads   = array_slice($this->uploads, 0, $uploads);
        $this->deletions = array_slice($this->deletions, 0, $deletions);
    }

    /**
     * Move everything recorded after the mark out of this collector.
     *
     * @param array{int, int} $mark
     * @return array{uploads: list<array{path: string, disk: ?string}>, deletions: list<array{path: string, disk: ?string}>}
     */
    public function takeSince(array $mark): array
    {
        [$uploads, $deletions] = $mark;
        $taken = [
            'uploads'   => array_slice($this->uploads, $uploads),
            'deletions' => array_slice($this->deletions, $deletions),
        ];
        $this->uploads   = array_slice($this->uploads, 0, $uploads);
        $this->deletions = array_slice($this->deletions, 0, $deletions);

        return $taken;
    }

    /**
     * @param array{uploads: list<array{path: string, disk: ?string}>, deletions: list<array{path: string, disk: ?string}>} $ops
     */
    public function absorb(array $ops): void
    {
        array_push($this->uploads, ...$ops['uploads']);
        array_push($this->deletions, ...$ops['deletions']);
    }

    /**
     * Detach everything recorded so far into a standalone collector (this one is emptied),
     * so its commit can be deferred until a foreign DB transaction really commits.
     */
    public function detach(): self
    {
        $copy = new self($this->fileStorage);
        $copy->absorb(['uploads' => $this->uploads, 'deletions' => $this->deletions]);
        $this->reset();

        return $copy;
    }

    /** Outer commit succeeded: drop replaced/old files; keep uploads (now referenced). */
    public function commit(): void
    {
        $this->deletePaths($this->deletions);
        $this->reset();
    }

    /** Outer transaction rolled back: remove orphaned uploads; discard deletions. */
    public function rollback(): void
    {
        $this->deletePaths($this->uploads);
        $this->reset();
    }

    public function reset(): void
    {
        $this->uploads = [];
        $this->deletions = [];
    }

    /** @param list<array{path: string, disk: ?string}> $items */
    private function deletePaths(array $items): void
    {
        foreach ($items as $item) {
            try {
                $this->fileStorage->delete($item['path'], $item['disk']);
            } catch (Throwable) {
                // Swallow — cleanup must never raise and mask the real outcome.
            }
        }
    }
}
