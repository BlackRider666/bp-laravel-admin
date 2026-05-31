<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Http;

use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\CreatesTestItemTable;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItem;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Integration tests for AdminEntityController.
 *
 * Verifies the full CRUD HTTP flow through real routes, middleware, use cases,
 * and the JsonEntityPresenter over an in-memory SQLite database.
 */
final class AdminEntityControllerTest extends TestCase
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
    // index — GET /admin/test_item
    // ------------------------------------------------------------------

    public function test_index_returns_200_with_data_key(): void
    {
        TestItem::create(['name' => 'Alice', 'email' => 'alice@example.com']);

        $response = $this->getJson('/admin/test_item');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_index_returns_all_persisted_records(): void
    {
        TestItem::create(['name' => 'Alice', 'email' => 'alice@example.com']);
        TestItem::create(['name' => 'Bob', 'email' => 'bob@example.com']);

        $response = $this->getJson('/admin/test_item');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_returns_empty_data_when_no_records_exist(): void
    {
        $response = $this->getJson('/admin/test_item');

        $response->assertOk()
            ->assertJson(['data' => []]);
    }

    // ------------------------------------------------------------------
    // create — GET /admin/test_item/create
    // ------------------------------------------------------------------

    public function test_create_returns_200_with_entity_and_fields(): void
    {
        $response = $this->getJson('/admin/test_item/create');

        $response->assertOk()
            ->assertJsonStructure(['entity', 'action', 'fields'])
            ->assertJsonFragment(['entity' => 'test_item', 'action' => 'create']);
    }

    public function test_create_returns_field_names_for_entity(): void
    {
        $response = $this->getJson('/admin/test_item/create');

        $fields = collect($response->json('fields'));
        $names = $fields->pluck('name')->toArray();

        $this->assertContains('name', $names);
        $this->assertContains('email', $names);
    }

    // ------------------------------------------------------------------
    // store — POST /admin/test_item
    // ------------------------------------------------------------------

    public function test_store_creates_record_and_returns_201(): void
    {
        $response = $this->postJson('/admin/test_item', [
            'name'  => 'Charlie',
            'email' => 'charlie@example.com',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data']);

        $this->assertDatabaseHas('test_items', ['name' => 'Charlie', 'email' => 'charlie@example.com']);
    }

    public function test_store_persists_correct_field_values(): void
    {
        $this->postJson('/admin/test_item', [
            'name'  => 'Diana',
            'email' => 'diana@example.com',
        ])->assertStatus(201);

        $this->assertSame(1, TestItem::count());
        $item = TestItem::first();
        $this->assertSame('Diana', $item->name);
        $this->assertSame('diana@example.com', $item->email);
    }

    // ------------------------------------------------------------------
    // show — GET /admin/test_item/{id}
    // ------------------------------------------------------------------

    public function test_show_returns_200_with_record_data(): void
    {
        $item = TestItem::create(['name' => 'Eve', 'email' => 'eve@example.com']);

        $response = $this->getJson("/admin/test_item/{$item->id}");

        $response->assertOk()
            ->assertJsonStructure(['data'])
            ->assertJsonFragment(['name' => 'Eve']);
    }

    public function test_show_returns_404_when_record_does_not_exist(): void
    {
        $response = $this->getJson('/admin/test_item/99999');

        $response->assertNotFound()
            ->assertJson(['message' => 'Not found.']);
    }

    // ------------------------------------------------------------------
    // edit — GET /admin/test_item/{id}/edit
    // ------------------------------------------------------------------

    public function test_edit_returns_200_with_record_and_fields(): void
    {
        $item = TestItem::create(['name' => 'Frank', 'email' => 'frank@example.com']);

        $response = $this->getJson("/admin/test_item/{$item->id}/edit");

        $response->assertOk()
            ->assertJsonStructure(['entity', 'action', 'data', 'fields'])
            ->assertJsonFragment(['action' => 'edit', 'entity' => 'test_item']);
    }

    public function test_edit_returns_404_when_record_does_not_exist(): void
    {
        $response = $this->getJson('/admin/test_item/99999/edit');

        $response->assertNotFound()
            ->assertJson(['message' => 'Not found.']);
    }

    // ------------------------------------------------------------------
    // update — PUT /admin/test_item/{id}
    // ------------------------------------------------------------------

    public function test_update_modifies_record_and_returns_200(): void
    {
        $item = TestItem::create(['name' => 'Grace', 'email' => 'grace@example.com']);

        $response = $this->putJson("/admin/test_item/{$item->id}", [
            'name'  => 'Grace Updated',
            'email' => 'grace-updated@example.com',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['data']);

        $this->assertDatabaseHas('test_items', [
            'id'    => $item->id,
            'name'  => 'Grace Updated',
            'email' => 'grace-updated@example.com',
        ]);
    }

    public function test_update_returns_404_when_record_does_not_exist(): void
    {
        $response = $this->putJson('/admin/test_item/99999', [
            'name'  => 'Nobody',
            'email' => 'nobody@example.com',
        ]);

        $response->assertNotFound()
            ->assertJson(['message' => 'Not found.']);
    }

    // ------------------------------------------------------------------
    // destroy — DELETE /admin/test_item/{id}
    // ------------------------------------------------------------------

    public function test_destroy_deletes_record_and_returns_204(): void
    {
        $item = TestItem::create(['name' => 'Harry', 'email' => 'harry@example.com']);

        $response = $this->deleteJson("/admin/test_item/{$item->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('test_items', ['id' => $item->id]);
    }

    public function test_destroy_returns_404_when_record_does_not_exist(): void
    {
        $response = $this->deleteJson('/admin/test_item/99999');

        $response->assertNotFound()
            ->assertJson(['message' => 'Not found.']);
    }
}
