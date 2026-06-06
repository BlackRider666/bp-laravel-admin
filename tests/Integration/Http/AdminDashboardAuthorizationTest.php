<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Http;

use BlackParadise\CoreAdmin\Domain\Contracts\Auth\AuthorizationProviderContract;
use BlackParadise\CoreAdmin\Domain\Contracts\EntityDefinition\EntityDefinitionContract;
use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\BootsBPAdmin;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItem;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItemDefinition;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

/**
 * A14 (B12): Dashboard must only return entities the authenticated user may 'list'.
 *
 * Bug: AdminDashboardController returns ALL registered entities regardless of
 * the user's authorization. AuthorizationProviderContract::can('list', ...) is
 * never called.
 *
 * Fix: inject AuthorizationProviderContract and filter the registry with
 * $this->authorization->can('list', $def) before building the response.
 */
final class AdminDashboardAuthorizationTest extends TestCase
{
    use RefreshDatabase;
    use BootsBPAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBPAdmin();
        $this->actingAsAdmin();
    }

    // ------------------------------------------------------------------
    // A14 — unauthorized entity is excluded from dashboard
    // ------------------------------------------------------------------

    /**
     * When the user does NOT have 'list' permission on the 'test_item' entity,
     * the dashboard must NOT include 'test_item' in the entities list.
     *
     * Currently FAILS: the controller ignores authorization and returns all entities.
     */
    public function test_dashboard_excludes_entities_user_cannot_list(): void
    {
        /** @var EntityDefinitionRegistry $registry */
        $registry = $this->app->make(EntityDefinitionRegistry::class);
        $registry->register(new TestItemDefinition());

        // Bind auth that denies 'list' for all entities.
        $this->app->bind(AuthorizationProviderContract::class, function (): AuthorizationProviderContract {
            $mock = Mockery::mock(AuthorizationProviderContract::class);
            $mock->shouldReceive('can')
                ->with('list', Mockery::type(EntityDefinitionContract::class))
                ->andReturn(false);
            return $mock;
        });

        $response = $this->getJson('/admin/');

        $response->assertOk();
        $entities = $response->json('entities');
        $names = array_column($entities ?? [], 'name');

        $this->assertNotContains('test_item', $names);
    }

    // ------------------------------------------------------------------
    // A14 — authorized entity appears in dashboard
    // ------------------------------------------------------------------

    /**
     * When the user HAS 'list' permission on 'test_item', it must appear.
     */
    public function test_dashboard_includes_entities_user_can_list(): void
    {
        /** @var EntityDefinitionRegistry $registry */
        $registry = $this->app->make(EntityDefinitionRegistry::class);
        $registry->register(new TestItemDefinition());

        // Allow 'list'.
        $this->app->bind(AuthorizationProviderContract::class, function (): AuthorizationProviderContract {
            $mock = Mockery::mock(AuthorizationProviderContract::class);
            $mock->shouldReceive('can')
                ->with('list', Mockery::type(EntityDefinitionContract::class))
                ->andReturn(true);
            return $mock;
        });

        $response = $this->getJson('/admin/');

        $response->assertOk();
        $names = array_column($response->json('entities') ?? [], 'name');
        $this->assertContains('test_item', $names);
    }

    // ------------------------------------------------------------------
    // A14 — mixed permissions: only authorized entities shown
    // ------------------------------------------------------------------

    /**
     * When there are two registered entities and only one is authorized for 'list',
     * the dashboard must contain exactly one.
     */
    public function test_dashboard_filters_to_only_authorized_entities(): void
    {
        /** @var EntityDefinitionRegistry $registry */
        $registry = $this->app->make(EntityDefinitionRegistry::class);

        // Two definitions: 'test_item' (allowed) and a second one (denied).
        $allowedDef = new TestItemDefinition();

        $deniedDef = new class extends EntityDefinition {
            public string $model = TestItem::class;
            public function resolveName(): string
            {
                return 'restricted_entity';
            }
            public function fields(): array
            {
                return [];
            }
        };

        $registry->register($allowedDef);
        $registry->register($deniedDef);

        $this->app->bind(AuthorizationProviderContract::class, function (): AuthorizationProviderContract {
            $mock = Mockery::mock(AuthorizationProviderContract::class);
            $mock->shouldReceive('can')
                ->with('list', Mockery::on(fn(EntityDefinitionContract $def): bool => $def->name() === 'test_item'))
                ->andReturn(true);
            $mock->shouldReceive('can')
                ->with('list', Mockery::on(fn(EntityDefinitionContract $def): bool => $def->name() === 'restricted_entity'))
                ->andReturn(false);
            return $mock;
        });

        $response = $this->getJson('/admin/');

        $response->assertOk();
        $names = array_column($response->json('entities') ?? [], 'name');
        $this->assertContains('test_item', $names);
        $this->assertNotContains('restricted_entity', $names);
    }
}
