<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Http;

use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\CreatesTestItemTable;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Integration tests for AdminDashboardController.
 *
 * Verifies that the dashboard route returns a JSON response listing
 * all registered entity definitions via the JsonDashboardPresenter.
 */
final class AdminDashboardControllerTest extends TestCase
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
    // index — GET /admin/
    // ------------------------------------------------------------------

    public function test_dashboard_returns_200_with_page_key(): void
    {
        $response = $this->getJson('/admin/');

        $response->assertOk()
            ->assertJsonStructure(['page', 'entities'])
            ->assertJsonFragment(['page' => 'dashboard']);
    }

    public function test_dashboard_lists_registered_entities(): void
    {
        $response = $this->getJson('/admin/');

        $entities = $response->json('entities');
        $names = array_column($entities, 'name');

        $this->assertContains('test_item', $names);
    }

    public function test_dashboard_entity_entries_have_name_and_label(): void
    {
        $response = $this->getJson('/admin/');

        $entities = $response->json('entities');
        $this->assertNotEmpty($entities);

        foreach ($entities as $entity) {
            $this->assertArrayHasKey('name', $entity);
            $this->assertArrayHasKey('label', $entity);
        }
    }

    public function test_dashboard_returns_empty_entities_when_none_registered(): void
    {
        // Replace the registry with a fresh empty one.
        $this->app->instance(EntityDefinitionRegistry::class, new EntityDefinitionRegistry());

        $response = $this->getJson('/admin/');

        $response->assertOk()
            ->assertJson(['entities' => []]);
    }
}
