<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Http\Middleware;

use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\CreatesTestItemTable;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Integration tests for ValidateEntity middleware.
 *
 * Verifies that requests for unknown entity names result in 404,
 * while registered entities pass through to the controller.
 */
final class ValidateEntityTest extends TestCase
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
    // Unknown entity → 404
    // ------------------------------------------------------------------

    public function test_unknown_entity_name_returns_404(): void
    {
        $response = $this->getJson('/admin/does_not_exist');

        $response->assertNotFound();
    }

    public function test_unknown_entity_on_show_route_returns_404(): void
    {
        $response = $this->getJson('/admin/does_not_exist/1');

        $response->assertNotFound();
    }

    public function test_unknown_entity_on_create_route_returns_404(): void
    {
        $response = $this->getJson('/admin/does_not_exist/create');

        $response->assertNotFound();
    }

    // ------------------------------------------------------------------
    // Known entity → passes through
    // ------------------------------------------------------------------

    public function test_registered_entity_passes_middleware_and_reaches_controller(): void
    {
        $response = $this->getJson('/admin/test_item');

        // 200 proves middleware allowed the request through.
        $response->assertOk();
    }
}
