<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Persistence;

use BlackParadise\CoreAdmin\Domain\Contracts\Persistence\WriteScopeContract;
use BlackParadise\LaravelAdmin\Support\DeferredFileOperations;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Fix round 3.4 (I-2 + M-1): file side-effects of a write scope honour an AMBIENT (foreign) DB
 * transaction, and a failed INNER scope caught by an outer one is compensated by position
 * (mirrors RecordedEvents). No RefreshDatabase: real transaction boundaries are the subject.
 */
final class WriteScopeAmbientTransactionTest extends TestCase
{
    private WriteScopeContract $scope;
    private DeferredFileOperations $files;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('archive');
        $this->scope = resolve(WriteScopeContract::class);
        $this->files = resolve(DeferredFileOperations::class);
    }

    // ---- I-2: outermost scope inside a foreign DB::transaction ----------------------------------

    public function test_scope_inside_ambient_transaction_does_not_delete_files_before_the_ambient_commit(): void
    {
        Storage::disk('archive')->put('amb/old.bin', 'x');
        $existedAfterScope = false;

        DB::transaction(function () use (&$existedAfterScope): void {
            $this->scope->run(fn() => $this->files->recordDeletion('amb/old.bin', 'archive'));
            $existedAfterScope = Storage::disk('archive')->exists('amb/old.bin');
        });

        self::assertTrue($existedAfterScope, 'The file may only go after the real (ambient) commit.');
        Storage::disk('archive')->assertMissing('amb/old.bin');
    }

    public function test_ambient_rollback_after_a_successful_scope_keeps_the_deletion_files(): void
    {
        Storage::disk('archive')->put('amb/old.bin', 'x');

        try {
            DB::transaction(function (): never {
                $this->scope->run(fn() => $this->files->recordDeletion('amb/old.bin', 'archive'));
                throw new RuntimeException('ambient fails');
            });
        } catch (RuntimeException) {
            // expected
        }

        Storage::disk('archive')->assertExists('amb/old.bin');
    }

    public function test_scope_without_ambient_transaction_still_deletes_files_on_its_own_commit(): void
    {
        Storage::disk('archive')->put('amb/old.bin', 'x');

        $this->scope->run(fn() => $this->files->recordDeletion('amb/old.bin', 'archive'));

        Storage::disk('archive')->assertMissing('amb/old.bin');
    }

    // ---- M-1: inner scope fails, outer catches and commits ---------------------------------------

    public function test_failed_inner_scope_caught_by_outer_removes_the_inner_uploads(): void
    {
        Storage::disk('archive')->put('nest/inner-new.bin', 'x');

        $this->scope->run(function (): void {
            try {
                $this->scope->run(function (): never {
                    $this->files->recordUpload('nest/inner-new.bin', 'archive');
                    throw new RuntimeException('inner fails');
                });
            } catch (RuntimeException) {
                // the outer scope carries on
            }
        });

        Storage::disk('archive')->assertMissing('nest/inner-new.bin');
    }

    public function test_failed_inner_scope_caught_by_outer_discards_the_inner_deletions(): void
    {
        Storage::disk('archive')->put('nest/inner-old.bin', 'x');

        $this->scope->run(function (): void {
            try {
                $this->scope->run(function (): never {
                    $this->files->recordDeletion('nest/inner-old.bin', 'archive');
                    throw new RuntimeException('inner fails');
                });
            } catch (RuntimeException) {
                // the outer scope carries on
            }
        });

        Storage::disk('archive')->assertExists('nest/inner-old.bin');
    }

    public function test_failed_inner_scope_leaves_outer_own_uploads_and_deletions_intact(): void
    {
        Storage::disk('archive')->put('nest/outer-old-before.bin', 'x');
        Storage::disk('archive')->put('nest/outer-old-after.bin', 'x');
        Storage::disk('archive')->put('nest/outer-new.bin', 'x');

        $this->scope->run(function (): void {
            $this->files->recordDeletion('nest/outer-old-before.bin', 'archive');
            $this->files->recordUpload('nest/outer-new.bin', 'archive');
            try {
                $this->scope->run(function (): never {
                    $this->files->recordDeletion('nest/outer-old-after.bin', 'archive');
                    throw new RuntimeException('inner fails');
                });
            } catch (RuntimeException) {
                // the outer scope carries on
            }
        });

        Storage::disk('archive')->assertMissing('nest/outer-old-before.bin');
        Storage::disk('archive')->assertExists('nest/outer-new.bin');
        Storage::disk('archive')->assertExists('nest/outer-old-after.bin');
    }

    public function test_failed_inner_scope_then_outer_rollback_removes_all_uploads_and_keeps_all_deletions(): void
    {
        Storage::disk('archive')->put('nest/old.bin', 'x');
        Storage::disk('archive')->put('nest/outer-new.bin', 'x');
        Storage::disk('archive')->put('nest/inner-new.bin', 'x');

        try {
            $this->scope->run(function (): never {
                $this->files->recordUpload('nest/outer-new.bin', 'archive');
                try {
                    $this->scope->run(function (): never {
                        $this->files->recordUpload('nest/inner-new.bin', 'archive');
                        $this->files->recordDeletion('nest/old.bin', 'archive');
                        throw new RuntimeException('inner fails');
                    });
                } catch (RuntimeException) {
                    // swallowed
                }
                throw new RuntimeException('outer fails');
            });
        } catch (RuntimeException) {
            // expected
        }

        Storage::disk('archive')->assertMissing('nest/outer-new.bin');
        Storage::disk('archive')->assertMissing('nest/inner-new.bin');
        Storage::disk('archive')->assertExists('nest/old.bin');
    }
}
