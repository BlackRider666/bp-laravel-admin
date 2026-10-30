<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Feature\Http;

use BlackParadise\CoreAdmin\Domain\Contracts\Entity\EntityRecordContract;
use BlackParadise\CoreAdmin\Domain\Contracts\EntityDefinition\EntityDefinitionContract;
use BlackParadise\CoreAdmin\Domain\Events\EntityDeleted;
use BlackParadise\CoreAdmin\Domain\Mutators\EntityMutatorInterface;
use BlackParadise\CoreAdmin\Domain\ValueObjects\EntityKey;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\CreatesOwnedCascadeFixtures;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\RecordsDomainEvents;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\OwnedCascade\OcSoftHost;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestTag;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Support\Facades\Storage;

/** Delete decorator: records every mutator delete (WRP-style) and delegates. */
final class CascadeRecordingMutator implements EntityMutatorInterface
{
    /** @var list<string> */
    public static array $deletes = [];

    public function __construct(private readonly EntityMutatorInterface $inner) {}

    public function create(EntityRecordContract $record): EntityRecordContract
    {
        return $this->inner->create($record);
    }

    public function update(EntityKey $key, EntityRecordContract $record): EntityRecordContract
    {
        return $this->inner->update($key, $record);
    }

    public function delete(EntityKey $key, EntityDefinitionContract $entityDefinition): bool
    {
        self::$deletes[] = $entityDefinition->name();

        return $this->inner->delete($key, $entityDefinition);
    }
}

/**
 * A25 + A54 + A55 (+ A21 on delete): owned hasMany/morphMany embedded children are deleted by BPAdmin
 * (rows, files after commit, belongsToMany pivots, delete events) with NO DB foreign keys; for a REAL
 * soft-deletable host a plain delete keeps everything and only force-delete cascades.
 */
final class OwnedCascadeDeleteTest extends TestCase
{
    use CreatesOwnedCascadeFixtures;
    use RecordsDomainEvents;

    protected function setUp(): void
    {
        parent::setUp();
        CascadeRecordingMutator::$deletes = [];
        $this->setUpOwnedCascadeFixtures();
        $this->recordEvents(EntityDeleted::class);
    }

    // ---- A25 / A55: non-soft-deletable host ---------------------------------------------------

    public function test_destroy_deletes_the_host_and_its_owned_children_rows(): void
    {
        $host = $this->seedPlainHostGraph();

        $this->deleteJson(route('bpadmin.entity.destroy', ['entity' => 'oc_host', 'id' => $host->id]))->assertSuccessful();

        $this->assertDatabaseMissing('oc_hosts', ['id' => $host->id]);
        $this->assertDatabaseMissing('oc_children', ['title' => 'Child Alpha']);
        $this->assertDatabaseMissing('oc_children', ['title' => 'Child Beta']);
        $this->assertDatabaseCount('oc_notes', 0);
    }

    public function test_destroy_removes_owned_children_files_from_their_disk(): void
    {
        $host = $this->seedPlainHostGraph();

        $this->deleteJson(route('bpadmin.entity.destroy', ['entity' => 'oc_host', 'id' => $host->id]))->assertSuccessful();

        Storage::disk('archive')->assertMissing('oc/alpha.bin');
        Storage::disk('archive')->assertMissing('oc/beta.bin');
        Storage::disk('archive')->assertMissing('oc/note.bin');
    }

    public function test_destroy_deletes_owned_children_pivot_rows_without_orphaning_them(): void
    {
        $host = $this->seedPlainHostGraph();
        self::assertSame(4, $this->pivotRows());

        $this->deleteJson(route('bpadmin.entity.destroy', ['entity' => 'oc_host', 'id' => $host->id]))->assertSuccessful();

        self::assertSame(1, $this->pivotRows(), 'Only the stranger child pivot row may remain.');
        self::assertSame(2, TestTag::count(), 'Related tags are not owned and must survive.');
    }

    public function test_destroy_leaves_another_hosts_children_and_files_untouched(): void
    {
        $host = $this->seedPlainHostGraph();

        $this->deleteJson(route('bpadmin.entity.destroy', ['entity' => 'oc_host', 'id' => $host->id]))->assertSuccessful();

        $this->assertDatabaseHas('oc_children', ['title' => 'Stranger child']);
        Storage::disk('archive')->assertExists('oc/stranger.bin');
    }

    public function test_destroy_dispatches_a_delete_event_for_the_host_and_every_owned_child_after_commit(): void
    {
        $host = $this->seedPlainHostGraph();

        $this->deleteJson(route('bpadmin.entity.destroy', ['entity' => 'oc_host', 'id' => $host->id]))->assertSuccessful();

        self::assertSame(4, $this->seenCount(EntityDeleted::class), 'host + 2 hasMany children + 1 morphMany child');
        self::assertTrue($this->allSeenAfterCommit());
    }

    public function test_destroy_passes_owned_children_through_a_decorated_mutator(): void
    {
        app()->extend(EntityMutatorInterface::class, fn(EntityMutatorInterface $inner): EntityMutatorInterface => new CascadeRecordingMutator($inner));
        $host = $this->seedPlainHostGraph();

        $this->deleteJson(route('bpadmin.entity.destroy', ['entity' => 'oc_host', 'id' => $host->id]))->assertSuccessful();

        self::assertEqualsCanonicalizing(['oc_host', 'oc_child', 'oc_child', 'oc_note'], CascadeRecordingMutator::$deletes);
    }

    public function test_bulk_destroy_cascades_owned_children_for_every_key(): void
    {
        $first = $this->seedPlainHostGraph();
        $second = $this->seedPlainHostGraph();

        $this->postJson(route('bpadmin.entity.bulk-destroy', ['entity' => 'oc_host']), ['ids' => [$first->id, $second->id]])
            ->assertOk()
            ->assertJson(['deleted' => 2]);

        $this->assertDatabaseMissing('oc_children', ['title' => 'Child Alpha']);
        $this->assertDatabaseMissing('oc_children', ['title' => 'Child Beta']);
        Storage::disk('archive')->assertMissing('oc/alpha.bin');
    }

    // ---- A54: soft-deletable host --------------------------------------------------------------

    public function test_destroy_of_soft_deletable_host_keeps_children_files_and_pivot_rows(): void
    {
        $host = $this->seedSoftHostGraph();

        $this->deleteJson(route('bpadmin.entity.destroy', ['entity' => 'oc_soft_host', 'id' => $host->id]))->assertSuccessful();

        $this->assertSoftDeleted('oc_soft_hosts', ['id' => $host->id]);
        $this->assertDatabaseCount('oc_children', 2);
        Storage::disk('archive')->assertExists('oc/alpha.bin');
        Storage::disk('archive')->assertExists('oc/beta.bin');
        self::assertSame(3, $this->pivotRows());
    }

    public function test_destroy_of_soft_deletable_host_dispatches_no_child_delete_events(): void
    {
        $host = $this->seedSoftHostGraph();

        $this->deleteJson(route('bpadmin.entity.destroy', ['entity' => 'oc_soft_host', 'id' => $host->id]))->assertSuccessful();

        self::assertSame([$host->id], $this->seenKeys(EntityDeleted::class), 'Only the host delete event.');
    }

    public function test_restore_after_soft_delete_shows_the_same_children_with_files_and_pivots(): void
    {
        $host = $this->seedSoftHostGraph();
        $this->deleteJson(route('bpadmin.entity.destroy', ['entity' => 'oc_soft_host', 'id' => $host->id]))->assertSuccessful();

        OcSoftHost::onlyTrashed()->whereKey($host->id)->firstOrFail()->restoreFromTrash();

        $restored = OcSoftHost::query()->whereKey($host->id)->firstOrFail();
        self::assertEqualsCanonicalizing(['Child Alpha', 'Child Beta'], $restored->items->pluck('title')->all());
        Storage::disk('archive')->assertExists('oc/alpha.bin');
        self::assertSame(3, $this->pivotRows());
    }

    // ---- A55: force-delete of the soft-deletable host (real force-delete branch) ----------------

    public function test_force_delete_after_soft_delete_cascades_children_rows(): void
    {
        $host = $this->seedSoftHostGraph();
        $this->deleteJson(route('bpadmin.entity.destroy', ['entity' => 'oc_soft_host', 'id' => $host->id]))->assertSuccessful();

        OcSoftHost::withTrashed()->whereKey($host->id)->firstOrFail()->forceDelete();

        $this->assertDatabaseMissing('oc_soft_hosts', ['id' => $host->id]);
        $this->assertDatabaseCount('oc_children', 0);
    }

    public function test_force_delete_after_restore_removes_children_files_and_pivot_rows(): void
    {
        $host = $this->seedSoftHostGraph();
        $this->deleteJson(route('bpadmin.entity.destroy', ['entity' => 'oc_soft_host', 'id' => $host->id]))->assertSuccessful();
        OcSoftHost::onlyTrashed()->whereKey($host->id)->firstOrFail()->restoreFromTrash();

        OcSoftHost::query()->whereKey($host->id)->firstOrFail()->forceDelete();

        $this->assertDatabaseCount('oc_children', 0);
        Storage::disk('archive')->assertMissing('oc/alpha.bin');
        Storage::disk('archive')->assertMissing('oc/beta.bin');
        self::assertSame(0, $this->pivotRows());
        self::assertSame(2, TestTag::count());
    }
}
