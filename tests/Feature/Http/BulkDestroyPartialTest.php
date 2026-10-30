<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Feature\Http;

use BlackParadise\CoreAdmin\Domain\Events\EntityDeleted;
use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\ActsAsPermissiveAdmin;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\RecordsDomainEvents;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\BulkPartial\BpItem;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\BulkPartial\BpItemDefinition;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * A26: bulk delete is per-key atomic. A key that fails is reported and rolled back (row + file kept,
 * no event); every other key is deleted, its file removed and its event dispatched after commit.
 */
final class BulkDestroyPartialTest extends TestCase
{
    use ActsAsPermissiveAdmin;
    use RecordsDomainEvents;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('archive');
        $this->stubHasher();
        Schema::create('bp_items', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->string('doc')->nullable();
            $t->timestamps();
        });
        app()->make(EntityDefinitionRegistry::class)->register(new BpItemDefinition());
        $this->permitEverything();
        $this->loginAdmin();
        $this->recordEvents(EntityDeleted::class);
    }

    private function seedItem(string $name, string $path): BpItem
    {
        Storage::disk('archive')->put($path, 'file');

        return BpItem::create(['name' => $name, 'doc' => $path]);
    }

    /** @return iterable<string, array{list<string>}> */
    public static function orderings(): iterable
    {
        yield 'failing key first' => [['poison', 'ok-a', 'ok-b']];
        yield 'failing key in the middle' => [['ok-a', 'poison', 'ok-b']];
        yield 'failing key last' => [['ok-a', 'ok-b', 'poison']];
    }

    /** @param list<string> $names */
    #[DataProvider('orderings')]
    public function test_bulk_destroy_deletes_the_other_keys_and_reports_the_failed_key(array $names): void
    {
        $ids = [];
        foreach ($names as $name) {
            $ids[$name] = $this->seedItem($name, 'bp/' . $name . '.bin')->id;
        }

        $response = $this->postJson(route('bpadmin.entity.bulk-destroy', ['entity' => 'bp_item']), ['ids' => array_values($ids)]);

        $response->assertOk()->assertJson(['deleted' => 2]);
        self::assertSame([(string) $ids['poison']], array_map(strval(...), (array) $response->json('failed_ids')));
        $this->assertDatabaseMissing('bp_items', ['id' => $ids['ok-a']]);
        $this->assertDatabaseMissing('bp_items', ['id' => $ids['ok-b']]);
        $this->assertDatabaseHas('bp_items', ['id' => $ids['poison']]);
    }

    /** @param list<string> $names */
    #[DataProvider('orderings')]
    public function test_bulk_destroy_cleans_files_of_deleted_keys_and_keeps_the_failed_keys_file(array $names): void
    {
        $ids = [];
        foreach ($names as $name) {
            $ids[$name] = $this->seedItem($name, 'bp/' . $name . '.bin')->id;
        }

        $this->postJson(route('bpadmin.entity.bulk-destroy', ['entity' => 'bp_item']), ['ids' => array_values($ids)])->assertOk();

        Storage::disk('archive')->assertMissing('bp/ok-a.bin');
        Storage::disk('archive')->assertMissing('bp/ok-b.bin');
        Storage::disk('archive')->assertExists('bp/poison.bin');
    }

    /** @param list<string> $names */
    #[DataProvider('orderings')]
    public function test_bulk_destroy_dispatches_events_for_deleted_keys_even_though_one_key_fails(array $names): void
    {
        $ids = [];
        foreach ($names as $name) {
            $ids[$name] = $this->seedItem($name, 'bp/' . $name . '.bin')->id;
        }

        $this->postJson(route('bpadmin.entity.bulk-destroy', ['entity' => 'bp_item']), ['ids' => array_values($ids)])->assertOk();

        $seen = $this->seenKeys(EntityDeleted::class);
        sort($seen);
        $expected = [$ids['ok-a'], $ids['ok-b']];
        sort($expected);
        self::assertSame($expected, $seen, 'No event for the failed key, one for each deleted key.');
        self::assertTrue($this->allSeenAfterCommit());
    }

    public function test_bulk_destroy_with_only_a_failing_key_deletes_nothing_and_reports_it(): void
    {
        $poison = $this->seedItem('poison', 'bp/poison.bin');

        $response = $this->postJson(route('bpadmin.entity.bulk-destroy', ['entity' => 'bp_item']), ['ids' => [$poison->id]]);

        $response->assertOk()->assertJson(['deleted' => 0]);
        self::assertSame([(string) $poison->id], array_map(strval(...), (array) $response->json('failed_ids')));
        self::assertSame([], $this->seenEvents);
        Storage::disk('archive')->assertExists('bp/poison.bin');
    }
}
