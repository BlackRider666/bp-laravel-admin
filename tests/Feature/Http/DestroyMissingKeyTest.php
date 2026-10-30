<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Feature\Http;

use BlackParadise\CoreAdmin\Domain\Events\EntityDeleted;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\CreatesGraphSaveFixtures;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\RecordsDomainEvents;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\GraphSave\GsHost;
use BlackParadise\LaravelAdmin\Tests\TestCase;

/** A27: deleting a non-existent key is a 404 (and never a silent success). */
final class DestroyMissingKeyTest extends TestCase
{
    use CreatesGraphSaveFixtures;
    use RecordsDomainEvents;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpGraphSaveFixtures();
        $this->recordEvents(EntityDeleted::class);
    }

    public function test_destroy_with_unknown_key_returns_404(): void
    {
        $this->deleteJson(route('bpadmin.entity.destroy', ['entity' => 'gs_host', 'id' => 9999]))->assertNotFound();
    }

    public function test_destroy_with_unknown_key_dispatches_no_event_and_changes_nothing(): void
    {
        GsHost::create(['name' => 'Keep']);

        $this->deleteJson(route('bpadmin.entity.destroy', ['entity' => 'gs_host', 'id' => 9999]));

        self::assertSame([], $this->seenEvents);
        $this->assertDatabaseCount('gs_hosts', 1);
    }

    public function test_destroy_twice_returns_404_the_second_time(): void
    {
        $host = GsHost::create(['name' => 'Once']);
        $url = route('bpadmin.entity.destroy', ['entity' => 'gs_host', 'id' => $host->id]);

        $this->deleteJson($url)->assertSuccessful();

        $this->deleteJson($url)->assertNotFound();
    }

    public function test_bulk_destroy_reports_the_missing_key_and_deletes_the_existing_one(): void
    {
        $host = GsHost::create(['name' => 'Exists']);

        $response = $this->postJson(route('bpadmin.entity.bulk-destroy', ['entity' => 'gs_host']), ['ids' => [$host->id, 9999]]);

        $response->assertOk()->assertJson(['deleted' => 1, 'failed_ids' => []]);
        self::assertSame(['9999'], array_map(strval(...), (array) $response->json('not_found_ids')));
        self::assertSame([$host->id], $this->seenKeys(EntityDeleted::class));
    }
}
