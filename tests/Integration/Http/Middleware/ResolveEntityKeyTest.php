<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Http\Middleware;

use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\CreatesTestItemTable;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItem;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Integration tests for ResolveEntityKey middleware.
 *
 * Verifies that the {id} route parameter is resolved into an EntityKey
 * domain object and stored on request attributes so controllers can use it.
 *
 * These tests verify the end-to-end effect: requests with a valid integer id
 * reach the controller and produce a coherent response. Invalid ids do not
 * break the middleware itself (the controller handles not-found).
 */
final class ResolveEntityKeyTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTestItemTable;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestItemFixtures();
        $this->loginAsUser();
    }

    // ------------------------------------------------------------------
    // Route with {id} segment — key is resolved
    // ------------------------------------------------------------------

    public function test_show_with_existing_id_resolves_key_and_returns_record(): void
    {
        $item = TestItem::create(['name' => 'Ivy', 'email' => 'ivy@example.com']);

        $response = $this->getJson("/admin/test_item/{$item->id}");

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Ivy']);
    }

    public function test_show_with_nonexistent_id_resolves_key_and_controller_returns_404(): void
    {
        // The middleware itself does not 404 — it resolves the key.
        // The controller/use case returns 404 when the record is absent.
        $response = $this->getJson('/admin/test_item/99999');

        $response->assertNotFound();
    }

    // ------------------------------------------------------------------
    // Routes without {id} segment — no key resolution, request passes through
    // ------------------------------------------------------------------

    public function test_index_route_without_id_passes_through_without_entity_key(): void
    {
        $response = $this->getJson('/admin/test_item');

        // No crash means the middleware correctly skipped key resolution.
        $response->assertOk();
    }

    public function test_update_with_existing_id_resolves_key_and_updates_record(): void
    {
        $item = TestItem::create(['name' => 'Jack', 'email' => 'jack@example.com']);

        $response = $this->putJson("/admin/test_item/{$item->id}", [
            'name'  => 'Jack Updated',
            'email' => 'jack-updated@example.com',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('test_items', ['id' => $item->id, 'name' => 'Jack Updated']);
    }

    public function test_destroy_with_existing_id_resolves_key_and_deletes_record(): void
    {
        $item = TestItem::create(['name' => 'Karen', 'email' => 'karen@example.com']);

        $response = $this->deleteJson("/admin/test_item/{$item->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('test_items', ['id' => $item->id]);
    }
}
