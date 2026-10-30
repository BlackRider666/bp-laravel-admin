<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Persistence;

use BlackParadise\CoreAdmin\Domain\Contracts\Events\EventDispatcherContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Persistence\WriteScopeContract;
use BlackParadise\CoreAdmin\Domain\Events\EntityCreated;
use BlackParadise\CoreAdmin\Domain\ValueObjects\EntityKey;
use BlackParadise\LaravelAdmin\Support\DeferredFileOperations;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\RecordsDomainEvents;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Task 3.3 — the Laravel WriteScopeContract: one DB transaction + file side-effects flushed and
 * domain events released only on the OUTERMOST commit; discarded on rollback.
 * No RefreshDatabase on purpose: the real transaction boundary is the subject under test.
 */
final class LaravelWriteScopeTest extends TestCase
{
    use RecordsDomainEvents;

    private WriteScopeContract $scope;
    private DeferredFileOperations $files;
    private EventDispatcherContract $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('archive');
        Schema::create('ws_rows', function (Blueprint $t): void {
            $t->id();
            $t->string('label');
        });

        $this->scope = resolve(WriteScopeContract::class);
        $this->files = resolve(DeferredFileOperations::class);
        $this->dispatcher = resolve(EventDispatcherContract::class);
        $this->recordEvents(EntityCreated::class);
    }

    private function fireCreated(int $id): void
    {
        $this->dispatcher->dispatch(new EntityCreated(new EntityKey($id, 'int'), []));
    }

    public function test_scope_is_the_laravel_implementation(): void
    {
        $expected = 'BlackParadise\LaravelAdmin\Infrastructure\Persistence\LaravelWriteScope';

        self::assertSame($expected, $this->scope::class);
    }

    public function test_run_returns_the_work_result(): void
    {
        $result = $this->scope->run(static fn(): string => 'done');

        self::assertSame('done', $result);
    }

    public function test_run_executes_the_work_inside_a_db_transaction_and_commits(): void
    {
        $levelInside = $this->scope->run(function (): int {
            DB::table('ws_rows')->insert(['label' => 'a']);

            return DB::transactionLevel();
        });

        self::assertGreaterThan(0, $levelInside);
        self::assertSame(0, DB::transactionLevel());
        $this->assertDatabaseHas('ws_rows', ['label' => 'a']);
    }

    public function test_run_rolls_back_writes_and_rethrows_when_work_throws(): void
    {
        try {
            $this->scope->run(function (): never {
                DB::table('ws_rows')->insert(['label' => 'a']);
                throw new RuntimeException('boom');
            });
            self::fail('The work exception must propagate.');
        } catch (RuntimeException $e) {
            self::assertSame('boom', $e->getMessage());
        }

        $this->assertDatabaseCount('ws_rows', 0);
        self::assertSame(0, DB::transactionLevel());
    }

    // ---- deferred files --------------------------------------------------------------------

    public function test_run_removes_recorded_deletions_only_after_commit(): void
    {
        Storage::disk('archive')->put('ws/old.bin', 'x');

        $existedInside = $this->scope->run(function (): bool {
            $this->files->recordDeletion('ws/old.bin', 'archive');

            return Storage::disk('archive')->exists('ws/old.bin');
        });

        self::assertTrue($existedInside, 'The file must still exist while the scope is open.');
        Storage::disk('archive')->assertMissing('ws/old.bin');
    }

    public function test_run_on_failure_removes_recorded_uploads_and_keeps_recorded_deletions(): void
    {
        Storage::disk('archive')->put('ws/old.bin', 'x');
        Storage::disk('archive')->put('ws/new.bin', 'x');

        try {
            $this->scope->run(function (): never {
                $this->files->recordUpload('ws/new.bin', 'archive');
                $this->files->recordDeletion('ws/old.bin', 'archive');
                throw new RuntimeException('boom');
            });
        } catch (RuntimeException) {
            // expected
        }

        Storage::disk('archive')->assertMissing('ws/new.bin');
        Storage::disk('archive')->assertExists('ws/old.bin');
    }

    public function test_nested_scope_commit_does_not_flush_files_before_the_outer_scope_commits(): void
    {
        Storage::disk('archive')->put('ws/old.bin', 'x');

        $existedAfterInner = $this->scope->run(function (): bool {
            $this->scope->run(fn() => $this->files->recordDeletion('ws/old.bin', 'archive'));

            return Storage::disk('archive')->exists('ws/old.bin');
        });

        self::assertTrue($existedAfterInner, 'Only the outermost commit may flush file deletions.');
        Storage::disk('archive')->assertMissing('ws/old.bin');
    }

    public function test_outer_rollback_after_inner_commit_keeps_deletions_and_removes_uploads(): void
    {
        Storage::disk('archive')->put('ws/old.bin', 'x');
        Storage::disk('archive')->put('ws/new.bin', 'x');

        try {
            $this->scope->run(function (): never {
                $this->scope->run(function (): void {
                    $this->files->recordUpload('ws/new.bin', 'archive');
                    $this->files->recordDeletion('ws/old.bin', 'archive');
                });
                throw new RuntimeException('outer fails');
            });
        } catch (RuntimeException) {
            // expected
        }

        Storage::disk('archive')->assertExists('ws/old.bin');
        Storage::disk('archive')->assertMissing('ws/new.bin');
    }

    // ---- events ----------------------------------------------------------------------------

    public function test_run_delivers_events_only_after_commit(): void
    {
        $seenInside = $this->scope->run(function (): int {
            DB::table('ws_rows')->insert(['label' => 'a']);
            $this->fireCreated(1);

            return $this->seenCount(EntityCreated::class);
        });

        self::assertSame(0, $seenInside, 'No event may be delivered while the scope is open.');
        self::assertSame([1], $this->seenKeys(EntityCreated::class));
        self::assertTrue($this->allSeenAfterCommit());
    }

    public function test_run_discards_events_on_rollback(): void
    {
        try {
            $this->scope->run(function (): never {
                $this->fireCreated(1);
                throw new RuntimeException('boom');
            });
        } catch (RuntimeException) {
            // expected
        }

        self::assertSame([], $this->seenEvents);
    }

    public function test_nested_scope_commit_releases_events_only_with_the_outer_commit(): void
    {
        $seenAfterInner = $this->scope->run(function (): int {
            $this->scope->run(fn() => $this->fireCreated(1));

            return $this->seenCount(EntityCreated::class);
        });

        self::assertSame(0, $seenAfterInner);
        self::assertSame([1], $this->seenKeys(EntityCreated::class));
        self::assertTrue($this->allSeenAfterCommit());
    }

    public function test_outer_rollback_after_inner_commit_discards_the_inner_events(): void
    {
        try {
            $this->scope->run(function (): never {
                $this->scope->run(fn() => $this->fireCreated(1));
                throw new RuntimeException('outer fails');
            });
        } catch (RuntimeException) {
            // expected
        }

        self::assertSame([], $this->seenEvents);
    }

    public function test_inner_failure_caught_by_outer_drops_only_the_inner_events(): void
    {
        $this->scope->run(function (): void {
            $this->fireCreated(1);
            try {
                $this->scope->run(function (): never {
                    $this->fireCreated(2);
                    throw new RuntimeException('inner fails');
                });
            } catch (RuntimeException) {
                // the outer scope carries on
            }
            $this->fireCreated(3);
        });

        self::assertSame([1, 3], $this->seenKeys(EntityCreated::class));
    }

    public function test_events_dispatched_outside_any_scope_are_delivered_immediately(): void
    {
        $this->fireCreated(7);

        self::assertSame([7], $this->seenKeys(EntityCreated::class));
    }
}
