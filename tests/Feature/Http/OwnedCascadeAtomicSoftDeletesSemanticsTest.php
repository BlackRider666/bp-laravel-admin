<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Feature\Http;

use ArrayObject;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\CreatesAtomicSoftHost;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\CreatesOwnedCascadeFixtures;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Fix round 3.4 / I-4: AtomicSoftDeletes replaces SoftDeletes and keeps vanilla Laravel semantics
 * (event order, isForceDeleting() in forceDeleted, soft delete / restore) while wrapping the whole
 * forceDelete() in one transaction. No RefreshDatabase on purpose (level-0 assertions).
 */
final class OwnedCascadeAtomicSoftDeletesSemanticsTest extends TestCase
{
    use CreatesAtomicSoftHost;
    use CreatesOwnedCascadeFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOwnedCascadeFixtures();
    }

    private function atomicHost(): Model
    {
        $class = $this->declareAtomicSoftHost();
        $seeded = $this->seedSoftHostGraph();

        return $this->loadAtomicHost($class, $seeded->id);
    }

    // --- retired API -------------------------------------------------------------------------

    public function test_the_old_atomic_force_deletes_trait_no_longer_exists(): void
    {
        self::assertFalse(trait_exists($this->retiredTraitName()));
    }

    // --- host shape --------------------------------------------------------------------------

    public function test_host_uses_only_atomic_soft_deletes_and_soft_deletes_arrives_through_it(): void
    {
        $host = $this->atomicHost();

        self::assertSame([self::ATOMIC_TRAIT], array_values(class_uses($host)));
        self::assertContains(SoftDeletes::class, class_uses_recursive($host));
    }

    public function test_provider_cascades_children_for_a_host_that_only_uses_atomic_soft_deletes(): void
    {
        $host = $this->atomicHost();

        $this->forceDeleteModel($host);

        $this->assertDatabaseCount('oc_children', 0);
        self::assertSame(0, $this->pivotRows());
        Storage::disk('archive')->assertMissing('oc/alpha.bin');
    }

    // --- vanilla event semantics -------------------------------------------------------------

    public function test_force_delete_fires_events_once_each_in_vanilla_order(): void
    {
        $host = $this->atomicHost();
        $log = [];
        foreach (['forceDeleting', 'deleting', 'deleted', 'forceDeleted'] as $event) {
            Event::listen("eloquent.{$event}: " . $host::class, static function () use (&$log, $event): void {
                $log[] = $event;
            });
        }

        $this->forceDeleteModel($host);

        self::assertSame(['forceDeleting', 'deleting', 'deleted', 'forceDeleted'], $log);
    }

    public function test_force_deleted_observer_sees_is_force_deleting_false_as_in_vanilla_laravel(): void
    {
        $host = $this->atomicHost();
        $seen = null;
        Event::listen('eloquent.forceDeleted: ' . $host::class, static function (Model $model) use (&$seen): void {
            $seen = self::callSoftDeletesMethod($model, 'isForceDeleting');
        });

        $this->forceDeleteModel($host);

        self::assertFalse($seen);
    }

    public function test_deleting_observer_sees_is_force_deleting_true_during_a_force_delete(): void
    {
        $host = $this->atomicHost();
        $seen = null;
        Event::listen('eloquent.deleting: ' . $host::class, static function (Model $model) use (&$seen): void {
            $seen = self::callSoftDeletesMethod($model, 'isForceDeleting');
        });

        $this->forceDeleteModel($host);

        self::assertTrue($seen);
    }

    public function test_force_delete_fires_deleting_and_deleted_exactly_once_on_the_host(): void
    {
        $host = $this->atomicHost();
        $counts = ['deleting' => 0, 'deleted' => 0, 'forceDeleted' => 0];
        foreach (array_keys($counts) as $event) {
            Event::listen("eloquent.{$event}: " . $host::class, static function () use (&$counts, $event): void {
                $counts[$event]++;
            });
        }

        $this->forceDeleteModel($host);

        self::assertSame(['deleting' => 1, 'deleted' => 1, 'forceDeleted' => 1], $counts);
    }

    // --- soft delete / restore still vanilla -------------------------------------------------

    public function test_soft_delete_through_atomic_soft_deletes_keeps_row_and_children(): void
    {
        $host = $this->atomicHost();

        $host->delete();

        self::assertTrue(self::callSoftDeletesMethod($host, 'trashed'));
        $this->assertSoftDeleted('oc_soft_hosts', ['id' => $host->getKey()]);
        $this->assertDatabaseCount('oc_children', 2);
        Storage::disk('archive')->assertExists('oc/alpha.bin');
    }

    public function test_soft_delete_does_not_fire_force_events_nor_open_a_transaction_of_its_own(): void
    {
        $host = $this->atomicHost();
        $log = [];
        foreach (['forceDeleting', 'deleting', 'deleted', 'forceDeleted'] as $event) {
            Event::listen("eloquent.{$event}: " . $host::class, static function () use (&$log, $event): void {
                $log[] = $event;
            });
        }
        $begun = 0;
        Event::listen(TransactionBeginning::class, static function () use (&$begun): void {
            $begun++;
        });

        $host->delete();

        self::assertSame(['deleting', 'deleted'], $log);
        self::assertSame(0, $begun);
    }

    public function test_restore_through_atomic_soft_deletes_clears_deleted_at(): void
    {
        $host = $this->atomicHost();
        $host->delete();

        $restored = self::callSoftDeletesMethod($host, 'restore');

        self::assertTrue($restored);
        self::assertFalse(self::callSoftDeletesMethod($host, 'trashed'));
        $this->assertDatabaseHas('oc_soft_hosts', ['id' => $host->getKey(), 'deleted_at' => null]);
    }

    public function test_soft_deleted_host_can_be_force_deleted_later_with_full_cascade(): void
    {
        $host = $this->atomicHost();
        $host->delete();

        $result = $this->forceDeleteModel($host);

        self::assertTrue($result);
        $this->assertDatabaseCount('oc_soft_hosts', 0);
        $this->assertDatabaseCount('oc_children', 0);
    }

    // --- forceDeleteQuietly ------------------------------------------------------------------

    public function test_force_delete_quietly_runs_inside_a_single_transaction(): void
    {
        $host = $this->atomicHost();
        $begun = 0;
        Event::listen(TransactionBeginning::class, static function () use (&$begun): void {
            $begun++;
        });

        self::callSoftDeletesMethod($host, 'forceDeleteQuietly');

        self::assertSame(1, $begun);
    }

    public function test_force_delete_quietly_removes_the_row_and_leaves_no_open_transaction(): void
    {
        $host = $this->atomicHost();

        $result = self::callSoftDeletesMethod($host, 'forceDeleteQuietly');

        self::assertTrue($result);
        self::assertSame(0, DB::transactionLevel());
        $this->assertDatabaseCount('oc_soft_hosts', 0);
    }

    public function test_force_delete_quietly_with_failing_host_delete_rolls_back_and_leaves_no_open_transaction(): void
    {
        $host = $this->atomicHost();
        DB::statement('PRAGMA foreign_keys = ON');
        Schema::create('oc_host_refs', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('soft_host_id');
            $t->foreign('soft_host_id')->references('id')->on('oc_soft_hosts');
        });
        DB::table('oc_host_refs')->insert(['soft_host_id' => $host->getKey()]);

        try {
            self::callSoftDeletesMethod($host, 'forceDeleteQuietly');
            self::fail('QueryException expected');
        } catch (QueryException) {
            // expected
        }

        self::assertSame(0, DB::transactionLevel());
        $this->assertDatabaseHas('oc_soft_hosts', ['id' => $host->getKey()]);
    }

    // --- cancellation ------------------------------------------------------------------------

    public function test_force_delete_cancelled_by_a_forcedeleting_listener_returns_false_and_keeps_everything(): void
    {
        $host = $this->atomicHost();
        Event::listen('eloquent.forceDeleting: ' . $host::class, static fn(): bool => false);

        $result = $this->forceDeleteModel($host);

        self::assertFalse($result);
        self::assertSame(0, DB::transactionLevel());
        $this->assertDatabaseCount('oc_children', 2);
        $this->assertDatabaseHas('oc_soft_hosts', ['name' => 'Soft', 'deleted_at' => null]);
    }

    public function test_force_delete_succeeds_again_on_the_same_instance_after_a_cancelled_attempt(): void
    {
        $host = $this->atomicHost();
        $state = new ArrayObject(['cancel' => true]);
        Event::listen('eloquent.deleting: ' . $host::class, static fn(): ?bool => $state['cancel'] === true ? false : null);
        $this->forceDeleteModel($host);
        $state['cancel'] = false;

        $result = $this->forceDeleteModel($host);

        self::assertTrue($result);
        $this->assertDatabaseCount('oc_soft_hosts', 0);
        $this->assertDatabaseCount('oc_children', 0);
    }
}
