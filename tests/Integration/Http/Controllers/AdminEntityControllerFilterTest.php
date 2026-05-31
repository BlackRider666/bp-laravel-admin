<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Http\Controllers;

use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\CreatesTestItemTable;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItem;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItemFilterableDefinition;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Integration tests for filter parsing in AdminEntityController::index().
 *
 * Verifies that ?filter[field]=value is applied only for fields marked filterable(),
 * and that unknown or non-filterable fields are silently ignored.
 */
final class AdminEntityControllerFilterTest extends TestCase
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
    // filter by filterable field — GET /admin/test_item?filter[name]=Alice
    // ------------------------------------------------------------------

    public function test_index_filters_records_by_exact_match(): void
    {
        // Replace the default TestItemDefinition with one that has name as filterable.
        /** @var EntityDefinitionRegistry $registry */
        $registry = $this->app->make(EntityDefinitionRegistry::class);
        $registry->register(new TestItemFilterableDefinition());

        TestItem::create(['name' => 'Alice', 'email' => 'alice@example.com']);
        TestItem::create(['name' => 'Bob',   'email' => 'bob@example.com']);

        $response = $this->getJson('/admin/test_item?filter[name]=Alice');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('Alice', $data[0]['name']);
    }

    // ------------------------------------------------------------------
    // filter by non-filterable field — should be silently ignored
    // ------------------------------------------------------------------

    public function test_index_ignores_filter_for_non_filterable_field(): void
    {
        // TestItemDefinition (registered by setUpTestItemFixtures) has no filterable fields.
        TestItem::create(['name' => 'Alice', 'email' => 'alice@example.com']);
        TestItem::create(['name' => 'Bob',   'email' => 'bob@example.com']);

        // `name` is not filterable in the default TestItemDefinition — filter must be ignored.
        $response = $this->getJson('/admin/test_item?filter[name]=Alice');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    // ------------------------------------------------------------------
    // filter by undefined (non-existent) field — should be silently ignored
    // ------------------------------------------------------------------

    public function test_index_ignores_filter_for_undefined_field(): void
    {
        // Replace with filterable definition so we know the filter logic runs.
        /** @var EntityDefinitionRegistry $registry */
        $registry = $this->app->make(EntityDefinitionRegistry::class);
        $registry->register(new TestItemFilterableDefinition());

        TestItem::create(['name' => 'Alice', 'email' => 'alice@example.com']);
        TestItem::create(['name' => 'Bob',   'email' => 'bob@example.com']);

        // `nonexistent` is not a declared field — must be silently ignored, both records returned.
        $response = $this->getJson('/admin/test_item?filter[nonexistent]=value');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }
}
