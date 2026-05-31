<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Http\Middleware;

use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\CreatesTestItemTable;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Integration tests for AdminAuthenticated middleware.
 *
 * Verifies that unauthenticated JSON requests receive 401 and
 * authenticated requests pass through to the controller.
 */
final class AdminAuthenticatedTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTestItemTable;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestItemFixtures();
    }

    // ------------------------------------------------------------------
    // Unauthenticated path
    // ------------------------------------------------------------------

    public function test_unauthenticated_json_request_to_entity_index_returns_401(): void
    {
        $response = $this->getJson('/admin/test_item');

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_unauthenticated_json_request_to_dashboard_returns_401(): void
    {
        $response = $this->getJson('/admin/');

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_unauthenticated_json_request_to_show_returns_401(): void
    {
        $response = $this->getJson('/admin/test_item/1');

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    // ------------------------------------------------------------------
    // Authenticated path
    // ------------------------------------------------------------------

    public function test_authenticated_request_passes_through_middleware(): void
    {
        $this->loginAsUser();

        $response = $this->getJson('/admin/test_item');

        // 200 means the middleware passed through and the controller responded.
        $response->assertOk();
    }

    public function test_unauthenticated_browser_request_redirects_to_login(): void
    {
        // A non-JSON request (no Accept: application/json) should redirect.
        $response = $this->get('/admin/test_item');

        $response->assertRedirect();
    }
}
