<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Feature\Http;

use BlackParadise\CoreAdmin\Domain\Events\EntityCreated;
use BlackParadise\CoreAdmin\Domain\Events\EntityUpdated;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\CreatesGraphSaveFixtures;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\RecordsDomainEvents;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\GraphSave\GsHost;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * A22 + A23: a host saved with embedded children is atomic (rows, files, events) and
 * its domain events are delivered only after the outermost commit.
 */
final class GraphSaveAtomicityTest extends TestCase
{
    use CreatesGraphSaveFixtures;
    use RecordsDomainEvents;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpGraphSaveFixtures();
        $this->recordEvents(EntityCreated::class, EntityUpdated::class);
    }

    private function upload(): UploadedFile
    {
        return UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf');
    }

    // ---- A22: child fails validation ------------------------------------------------------

    public function test_store_when_second_child_fails_validation_returns_422_and_leaves_no_host_or_child_rows(): void
    {
        $response = $this->postJson(route('bpadmin.entity.store', ['entity' => 'gs_host']), [
            'name' => 'Host',
            'attachment' => $this->upload(),
            'items' => [['title' => 'Valid'], ['title' => '']],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('gs_hosts', 0);
        $this->assertDatabaseCount('gs_items', 0);
    }

    public function test_store_when_child_fails_validation_leaves_no_files_on_disk(): void
    {
        $this->postJson(route('bpadmin.entity.store', ['entity' => 'gs_host']), [
            'name' => 'Host',
            'attachment' => $this->upload(),
            'items' => [['title' => '']],
        ])->assertStatus(422);

        self::assertSame([], $this->publicFiles());
    }

    public function test_store_when_child_fails_validation_dispatches_no_events(): void
    {
        $this->postJson(route('bpadmin.entity.store', ['entity' => 'gs_host']), [
            'name' => 'Host',
            'items' => [['title' => '']],
        ])->assertStatus(422);

        self::assertSame([], $this->seenEvents);
    }

    // ---- A22: child write fails at the DB after the host row was written -------------------

    public function test_store_when_child_write_fails_after_host_insert_rolls_back_host_row(): void
    {
        $response = $this->postJson(route('bpadmin.entity.store', ['entity' => 'gs_host']), [
            'name' => 'Host',
            'attachment' => $this->upload(),
            'items' => [['title' => 'Valid']],
            'note' => ['body' => ''],
        ]);

        self::assertGreaterThanOrEqual(400, $response->baseResponse->getStatusCode());
        $this->assertDatabaseCount('gs_hosts', 0);
        $this->assertDatabaseCount('gs_items', 0);
    }

    public function test_store_when_child_write_fails_after_host_insert_leaves_no_files_and_no_events(): void
    {
        $this->postJson(route('bpadmin.entity.store', ['entity' => 'gs_host']), [
            'name' => 'Host',
            'attachment' => $this->upload(),
            'note' => ['body' => ''],
        ]);

        self::assertSame([], $this->publicFiles());
        self::assertSame([], $this->seenEvents);
    }

    public function test_update_when_child_write_fails_keeps_host_row_old_file_and_dispatches_no_events(): void
    {
        Storage::disk('public')->put('gs/old.bin', 'old');
        $host = GsHost::create(['name' => 'Before', 'attachment' => 'gs/old.bin']);

        $response = $this->putJson(route('bpadmin.entity.update', ['entity' => 'gs_host', 'id' => $host->id]), [
            'name' => 'After',
            'attachment' => $this->upload(),
            'note' => ['body' => ''],
        ]);

        self::assertGreaterThanOrEqual(400, $response->baseResponse->getStatusCode());
        $this->assertDatabaseHas('gs_hosts', ['id' => $host->id, 'name' => 'Before', 'attachment' => 'gs/old.bin']);
        self::assertSame(['gs/old.bin'], $this->publicFiles());
        self::assertSame([], $this->seenEvents);
    }

    // ---- A23: events only after commit ----------------------------------------------------

    public function test_store_with_children_dispatches_created_event_once_after_commit(): void
    {
        $this->postJson(route('bpadmin.entity.store', ['entity' => 'gs_host']), [
            'name' => 'Host',
            'items' => [['title' => 'One'], ['title' => 'Two']],
            'note' => ['body' => 'Text'],
        ])->assertCreated();

        self::assertSame(1, $this->seenCount(EntityCreated::class));
        self::assertTrue($this->allSeenAfterCommit(), 'EntityCreated must be delivered at transaction level 0.');
        $this->assertDatabaseCount('gs_items', 2);
        $this->assertDatabaseCount('gs_notes', 1);
    }

    public function test_update_with_children_dispatches_updated_event_once_after_commit(): void
    {
        $host = GsHost::create(['name' => 'Before']);

        $this->putJson(route('bpadmin.entity.update', ['entity' => 'gs_host', 'id' => $host->id]), [
            'name' => 'After',
            'items' => [['title' => 'One']],
        ])->assertOk();

        self::assertSame([$host->id], $this->seenKeys(EntityUpdated::class));
        self::assertTrue($this->allSeenAfterCommit(), 'EntityUpdated must be delivered at transaction level 0.');
        $this->assertDatabaseHas('gs_hosts', ['id' => $host->id, 'name' => 'After']);
    }

    public function test_store_when_child_write_fails_dispatches_no_created_event(): void
    {
        $this->postJson(route('bpadmin.entity.store', ['entity' => 'gs_host']), [
            'name' => 'Host',
            'note' => ['body' => ''],
        ]);

        self::assertSame(0, $this->seenCount(EntityCreated::class));
    }
}
