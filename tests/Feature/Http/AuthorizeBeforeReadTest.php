<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Feature\Http;

use BlackParadise\CoreAdmin\Domain\Contracts\Auth\AuthorizationProviderContract;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\CreatesGraphSaveFixtures;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\GraphSave\GsHost;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ScriptedAuthorization;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

/**
 * A24: an unauthorized create/update/delete answers 403 BEFORE any read or write — the entity table
 * is never queried. Only the write ability is denied (reads stay allowed), so any pre-read by the
 * controller (e.g. loading the current record) would be caught.
 */
final class AuthorizeBeforeReadTest extends TestCase
{
    use CreatesGraphSaveFixtures;

    /** @var list<string> */
    private array $entityTableQueries = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpGraphSaveFixtures();
    }

    /** @param list<string> $denied */
    private function deny(array $denied): void
    {
        app()->instance(AuthorizationProviderContract::class, new ScriptedAuthorization($denied));
    }

    private function watchEntityTable(): void
    {
        $this->entityTableQueries = [];
        DB::listen(function (QueryExecuted $query): void {
            if (str_contains($query->sql, 'gs_hosts')) {
                $this->entityTableQueries[] = $query->sql;
            }
        });
    }

    public function test_store_denied_returns_403_and_never_touches_the_entity_table(): void
    {
        $this->deny(['create']);
        $this->watchEntityTable();

        $this->postJson(route('bpadmin.entity.store', ['entity' => 'gs_host']), ['name' => 'X'])->assertForbidden();

        self::assertSame([], $this->entityTableQueries);
    }

    public function test_update_denied_returns_403_and_never_reads_or_writes_the_entity_table(): void
    {
        $host = GsHost::create(['name' => 'Before']);
        $this->deny(['update']);
        $this->watchEntityTable();

        $this->putJson(route('bpadmin.entity.update', ['entity' => 'gs_host', 'id' => $host->id]), ['name' => 'After'])->assertForbidden();

        self::assertSame([], $this->entityTableQueries);
        $this->assertDatabaseHas('gs_hosts', ['id' => $host->id, 'name' => 'Before']);
    }

    public function test_destroy_denied_returns_403_and_never_reads_or_writes_the_entity_table(): void
    {
        $host = GsHost::create(['name' => 'Keep']);
        $this->deny(['delete']);
        $this->watchEntityTable();

        $this->deleteJson(route('bpadmin.entity.destroy', ['entity' => 'gs_host', 'id' => $host->id]))->assertForbidden();

        self::assertSame([], $this->entityTableQueries);
        $this->assertDatabaseHas('gs_hosts', ['id' => $host->id]);
    }

    public function test_bulk_destroy_denied_never_reads_or_writes_the_entity_table(): void
    {
        $host = GsHost::create(['name' => 'Keep']);
        $this->deny(['delete']);
        $this->watchEntityTable();

        $this->postJson(route('bpadmin.entity.bulk-destroy', ['entity' => 'gs_host']), ['ids' => [$host->id]]);

        self::assertSame([], $this->entityTableQueries);
        $this->assertDatabaseHas('gs_hosts', ['id' => $host->id]);
    }

    public function test_update_denied_for_a_missing_key_returns_403_not_404(): void
    {
        $this->deny(['update']);

        $this->putJson(route('bpadmin.entity.update', ['entity' => 'gs_host', 'id' => 9999]), ['name' => 'After'])->assertForbidden();
    }

    public function test_destroy_denied_for_a_missing_key_returns_403_not_404(): void
    {
        $this->deny(['delete']);

        $this->deleteJson(route('bpadmin.entity.destroy', ['entity' => 'gs_host', 'id' => 9999]))->assertForbidden();
    }
}
