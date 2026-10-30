<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Feature\Http;

use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\CreatesAtomicSoftHost;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\CreatesOwnedCascadeFixtures;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\OwnedCascade\OcSoftHost;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Fix rounds 3.4 / I-3, I-4: event listeners never open or commit transactions. Level-0 atomicity of a
 * force delete is opt-in: a host using AtomicSoftDeletes wraps forceDelete() in its own
 * transaction. A host without the trait is non-atomic by contract but never leaves a dangling
 * transaction behind. No RefreshDatabase on purpose.
 */
final class OwnedCascadeAtomicForceDeleteTraitTest extends TestCase
{
    use CreatesAtomicSoftHost;
    use CreatesOwnedCascadeFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOwnedCascadeFixtures();
    }

    private function assertGraphIntact(): void
    {
        $this->assertDatabaseCount('oc_children', 2);
        $this->assertDatabaseHas('oc_soft_hosts', ['name' => 'Soft', 'deleted_at' => null]);
        Storage::disk('archive')->assertExists('oc/alpha.bin');
        Storage::disk('archive')->assertExists('oc/beta.bin');
        self::assertSame(3, $this->pivotRows());
    }

    /** A table whose FK references the host: the host DELETE (not the children's) is what fails. */
    private function referenceHostFromAnotherTable(int $hostId): void
    {
        DB::statement('PRAGMA foreign_keys = ON');
        Schema::create('oc_host_refs', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('soft_host_id');
            $t->foreign('soft_host_id')->references('id')->on('oc_soft_hosts');
        });
        DB::table('oc_host_refs')->insert(['soft_host_id' => $hostId]);
    }

    /** An unrelated later commit must not flush file deletions of an abandoned force delete. */
    private function commitSomethingUnrelated(): void
    {
        DB::transaction(static function (): void {
            DB::table('test_tags')->insert(['name' => 'later', 'created_at' => now(), 'updated_at' => now()]);
        });
    }

    /** Wildcard `deleting` listener registered AFTER the provider's: cancels only the host's delete. */
    private function cancelHostDeleteLate(Model $host): void
    {
        Event::listen('eloquent.deleting: *', static fn(string $event, array $payload): ?bool => $payload[0] instanceof $host ? false : null);
    }

    private function atomicHost(): Model
    {
        $class = $this->declareAtomicSoftHost();
        $seeded = $this->seedSoftHostGraph();

        return $this->loadAtomicHost($class, $seeded->id);
    }

    // --- atomic host (uses the trait) ---------------------------------------------------------

    public function test_trait_host_with_failing_host_delete_leaves_no_open_transaction(): void
    {
        $host = $this->atomicHost();
        $this->referenceHostFromAnotherTable((int) $host->getKey());

        try {
            $this->forceDeleteModel($host);
            self::fail('QueryException expected');
        } catch (QueryException) {
            // expected
        }

        self::assertSame(0, DB::transactionLevel());
    }

    public function test_trait_host_with_failing_host_delete_keeps_children_rows_files_and_pivots(): void
    {
        $host = $this->atomicHost();
        $this->referenceHostFromAnotherTable((int) $host->getKey());

        try {
            $this->forceDeleteModel($host);
        } catch (QueryException) {
            // expected
        }

        $this->assertGraphIntact();
    }

    public function test_trait_host_cancelled_by_late_deleting_listener_leaves_no_open_transaction(): void
    {
        $host = $this->atomicHost();
        $this->cancelHostDeleteLate($host);

        $this->forceDeleteModel($host);

        self::assertSame(0, DB::transactionLevel());
    }

    public function test_trait_host_cancelled_by_late_deleting_listener_keeps_children_rows_files_and_pivots(): void
    {
        $host = $this->atomicHost();
        $this->cancelHostDeleteLate($host);

        $result = $this->forceDeleteModel($host);

        self::assertFalse($result);
        $this->assertGraphIntact();
    }

    public function test_trait_host_cancelled_by_late_listener_leaks_no_pending_file_deletes_into_a_later_commit(): void
    {
        $host = $this->atomicHost();
        $this->cancelHostDeleteLate($host);
        $this->forceDeleteModel($host);

        $this->commitSomethingUnrelated();

        Storage::disk('archive')->assertExists('oc/alpha.bin');
        Storage::disk('archive')->assertExists('oc/beta.bin');
    }

    public function test_trait_host_with_throwing_deleted_listener_rolls_everything_back(): void
    {
        $host = $this->atomicHost();
        Event::listen('eloquent.deleted: ' . $host::class, static function (): never {
            throw new RuntimeException('deleted listener fails');
        });

        try {
            $this->forceDeleteModel($host);
        } catch (RuntimeException) {
            // expected
        }

        $this->assertGraphIntact();
    }

    public function test_trait_host_with_throwing_deleted_listener_leaves_no_open_transaction(): void
    {
        $host = $this->atomicHost();
        Event::listen('eloquent.deleted: ' . $host::class, static function (): never {
            throw new RuntimeException('deleted listener fails');
        });

        try {
            $this->forceDeleteModel($host);
        } catch (RuntimeException) {
            // expected
        }

        self::assertSame(0, DB::transactionLevel());
    }

    public function test_trait_host_with_throwing_force_deleted_listener_rolls_everything_back(): void
    {
        $host = $this->atomicHost();
        Event::listen('eloquent.forceDeleted: ' . $host::class, static function (): never {
            throw new RuntimeException('forceDeleted listener fails');
        });

        try {
            $this->forceDeleteModel($host);
        } catch (RuntimeException) {
            // expected
        }

        $this->assertGraphIntact();
    }

    public function test_trait_host_with_throwing_force_deleted_listener_leaves_no_open_transaction_and_no_leaked_file_deletes(): void
    {
        $host = $this->atomicHost();
        Event::listen('eloquent.forceDeleted: ' . $host::class, static function (): never {
            throw new RuntimeException('forceDeleted listener fails');
        });

        try {
            $this->forceDeleteModel($host);
        } catch (RuntimeException) {
            // expected
        }
        $this->commitSomethingUnrelated();

        self::assertSame(0, DB::transactionLevel());
        Storage::disk('archive')->assertExists('oc/alpha.bin');
    }

    public function test_trait_host_runs_the_cascade_inside_the_trait_transaction(): void
    {
        $host = $this->atomicHost();
        $levelInsideDelete = null;
        Event::listen('eloquent.deleting: *', static function (string $event, array $payload) use (&$levelInsideDelete, $host): void {
            if ($payload[0] instanceof $host) {
                $levelInsideDelete = DB::transactionLevel();
            }
        });

        $this->forceDeleteModel($host);

        self::assertSame(1, $levelInsideDelete);
    }

    public function test_trait_host_successful_force_delete_removes_graph_and_closes_transaction(): void
    {
        $host = $this->atomicHost();

        $result = $this->forceDeleteModel($host);

        self::assertTrue($result);
        self::assertSame(0, DB::transactionLevel());
        $this->assertDatabaseCount('oc_children', 0);
        $this->assertDatabaseCount('oc_soft_hosts', 0);
        self::assertSame(0, $this->pivotRows());
        Storage::disk('archive')->assertMissing('oc/alpha.bin');
        Storage::disk('archive')->assertMissing('oc/beta.bin');
    }

    // --- plain host (no trait): non-atomic by contract, but never a listener-owned transaction --

    public function test_plain_host_listener_does_not_open_a_transaction_during_the_delete(): void
    {
        $host = $this->seedSoftHostGraph();
        $levels = [];
        Event::listen('eloquent.deleting: *', static function (string $event, array $payload) use (&$levels): void {
            if ($payload[0] instanceof OcSoftHost) {
                $levels[] = DB::transactionLevel();
            }
        });
        Event::listen('eloquent.deleted: ' . OcSoftHost::class, static function () use (&$levels): void {
            $levels[] = DB::transactionLevel();
        });

        $host->forceDelete();

        self::assertSame([0, 0], $levels);
    }

    public function test_plain_host_force_delete_still_cascades_children_and_closes_nothing_open(): void
    {
        $host = $this->seedSoftHostGraph();

        $host->forceDelete();

        self::assertSame(0, DB::transactionLevel());
        $this->assertDatabaseCount('oc_children', 0);
        Storage::disk('archive')->assertMissing('oc/alpha.bin');
    }

    public function test_plain_host_with_failing_host_delete_leaves_no_open_transaction(): void
    {
        $host = $this->seedSoftHostGraph();
        $this->referenceHostFromAnotherTable($host->id);

        try {
            $host->forceDelete();
            self::fail('QueryException expected');
        } catch (QueryException) {
            // expected
        }

        self::assertSame(0, DB::transactionLevel());
    }

    public function test_plain_host_cancelled_by_late_listener_leaves_no_open_transaction(): void
    {
        $host = $this->seedSoftHostGraph();
        $this->cancelHostDeleteLate($host);

        $host->forceDelete();

        self::assertSame(0, DB::transactionLevel());
    }
}
