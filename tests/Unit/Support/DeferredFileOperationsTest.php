<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Unit\Support;

use BlackParadise\CoreAdmin\Domain\Contracts\Files\FileStorageProviderContract;
use BlackParadise\LaravelAdmin\Support\DeferredFileOperations;
use PHPUnit\Framework\TestCase;

/**
 * A2/A3 unit: DeferredFileOperations bookkeeping correctness.
 *
 * Bug: the class doesn't exist yet; when created it must commit/rollback only
 * the correct side-effect lists, and reset state afterwards.
 */
final class DeferredFileOperationsTest extends TestCase
{
    /**
     * Build an anonymous FileStorageProviderContract spy that records deletions.
     *
     * @param list<string> $deleted Reference accumulator for deleted paths.
     */
    private function spyStorage(array &$deleted): FileStorageProviderContract
    {
        return new class ($deleted) implements FileStorageProviderContract {
            public function __construct(private array &$deleted) {}

            public function store(string $path, mixed $file, ?string $disk = null): string
            {
                return 'stored/' . basename((string) $file);
            }

            public function delete(string $path, ?string $disk = null): bool
            {
                $this->deleted[] = $path;
                return true;
            }

            public function url(string $path, ?string $disk = null): string
            {
                return 'https://cdn.example.com/' . $path;
            }
        };
    }

    // ------------------------------------------------------------------
    // A2/A3 — commit deletes ONLY recorded deletions (not uploads)
    // ------------------------------------------------------------------

    public function test_commit_deletes_only_recorded_deletions(): void
    {
        $deleted = [];
        $ops = new DeferredFileOperations($this->spyStorage($deleted));
        $ops->recordUpload('uploads/new.png', 'public');
        $ops->recordDeletion('uploads/old.png', 'public');

        $ops->commit();

        self::assertSame(['uploads/old.png'], $deleted);
    }

    // ------------------------------------------------------------------
    // A3 — rollback deletes ONLY uploads (orphaned files), not deletions
    // ------------------------------------------------------------------

    public function test_rollback_deletes_only_uploads(): void
    {
        $deleted = [];
        $ops = new DeferredFileOperations($this->spyStorage($deleted));
        $ops->recordUpload('uploads/new.png', 'public');
        $ops->recordDeletion('uploads/old.png', 'public');

        $ops->rollback();

        self::assertSame(['uploads/new.png'], $deleted);
    }

    // ------------------------------------------------------------------
    // State resets after flush — second commit must be a no-op
    // ------------------------------------------------------------------

    public function test_state_resets_after_flush(): void
    {
        $deleted = [];
        $ops = new DeferredFileOperations($this->spyStorage($deleted));
        $ops->recordDeletion('uploads/a.png', null);

        $ops->commit();
        $ops->commit(); // second call must not re-delete

        self::assertSame(['uploads/a.png'], $deleted, 'second commit must be a no-op');
    }

    // ------------------------------------------------------------------
    // State resets after rollback — second rollback must be a no-op
    // ------------------------------------------------------------------

    public function test_state_resets_after_rollback(): void
    {
        $deleted = [];
        $ops = new DeferredFileOperations($this->spyStorage($deleted));
        $ops->recordUpload('uploads/new.png', 'public');

        $ops->rollback();
        $ops->rollback(); // second call must not re-delete

        self::assertSame(['uploads/new.png'], $deleted, 'second rollback must be a no-op');
    }
}
