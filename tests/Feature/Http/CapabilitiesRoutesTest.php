<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Feature\Http;

use BlackParadise\CoreAdmin\Domain\Contracts\Auth\AuthorizationProviderContract;
use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\ActsAsPermissiveAdmin;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ModelLess\ArrayStore;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ModelLess\CapabilityDenyingReports;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ModelLess\NonCreatableReports;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ModelLess\ReadOnlyReports;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ModelLess\ReadOnlyRequiredReports;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ModelLess\Reports;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ScriptedAuthorization;
use BlackParadise\LaravelAdmin\Tests\TestCase;

/**
 * Task 4.6b: HTTP capability enforcement.
 *
 * A29 readOnly() => 404 on every write route (incl. invalid payloads: capability beats validation);
 * A30 authz-denied on a capability-allowed route => 403, capability-denied => 404;
 * A31 `$creatable = false` => create/store 404 without instanceof checks;
 * core CapabilityDeniedException escaping a use case => 404.
 */
final class CapabilitiesRoutesTest extends TestCase
{
    use ActsAsPermissiveAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stubHasher();
        $this->permitEverything();
        $this->loginAdmin();

        ArrayStore::reset([1 => ['id' => 1, 'title' => 'Seed']]);
        $registry = app()->make(EntityDefinitionRegistry::class);
        foreach ([
            new Reports(),
            new ReadOnlyReports(),
            new ReadOnlyRequiredReports(),
            new NonCreatableReports(),
            new CapabilityDenyingReports(),
        ] as $definition) {
            $registry->register($definition);
        }
    }

    protected function tearDown(): void
    {
        ArrayStore::reset();
        parent::tearDown();
    }

    private function deny(string ...$abilities): void
    {
        app()->instance(AuthorizationProviderContract::class, new ScriptedAuthorization(array_values($abilities)));
    }

    // ---- A29 -------------------------------------------------------------------------------

    public function test_create_form_on_read_only_entity_returns_404(): void
    {
        $this->getJson(route('bpadmin.entity.create', ['entity' => 'read_only_reports']))->assertNotFound();
    }

    public function test_store_on_read_only_entity_returns_404_and_persists_nothing(): void
    {
        $this->postJson(route('bpadmin.entity.store', ['entity' => 'read_only_reports']), ['title' => 'X'])->assertNotFound();

        self::assertCount(1, ArrayStore::$rows);
    }

    public function test_edit_form_on_read_only_entity_returns_404(): void
    {
        $this->getJson(route('bpadmin.entity.edit', ['entity' => 'read_only_reports', 'id' => 1]))->assertNotFound();
    }

    public function test_update_on_read_only_entity_returns_404_and_keeps_the_record(): void
    {
        $this->putJson(route('bpadmin.entity.update', ['entity' => 'read_only_reports', 'id' => 1]), ['title' => 'X'])->assertNotFound();

        self::assertSame('Seed', ArrayStore::$rows[1]['title'] ?? null);
    }

    public function test_destroy_on_read_only_entity_returns_404_and_keeps_the_record(): void
    {
        $this->deleteJson(route('bpadmin.entity.destroy', ['entity' => 'read_only_reports', 'id' => 1]))->assertNotFound();

        self::assertCount(1, ArrayStore::$rows);
    }

    public function test_bulk_destroy_on_read_only_entity_returns_404_and_keeps_the_record(): void
    {
        $this->postJson(route('bpadmin.entity.bulk-destroy', ['entity' => 'read_only_reports']), ['ids' => [1]])->assertNotFound();

        self::assertCount(1, ArrayStore::$rows);
    }

    public function test_read_only_entity_still_serves_index_and_show(): void
    {
        $this->getJson(route('bpadmin.entity.index', ['entity' => 'read_only_reports']))->assertOk();
        $this->getJson(route('bpadmin.entity.show', ['entity' => 'read_only_reports', 'id' => 1]))->assertOk();
    }

    // ---- review m-2: capability check precedes payload validation ---------------------------

    public function test_store_with_invalid_payload_on_read_only_entity_returns_404_not_422(): void
    {
        $this->postJson(route('bpadmin.entity.store', ['entity' => 'read_only_required_reports']), ['title' => ''])->assertNotFound();
    }

    public function test_update_with_invalid_payload_on_read_only_entity_returns_404_not_422(): void
    {
        $this->putJson(route('bpadmin.entity.update', ['entity' => 'read_only_required_reports', 'id' => 1]), ['title' => ''])->assertNotFound();
    }

    public function test_bulk_destroy_with_invalid_payload_on_read_only_entity_returns_404_not_422(): void
    {
        $this->postJson(route('bpadmin.entity.bulk-destroy', ['entity' => 'read_only_reports']), ['ids' => []])->assertNotFound();
    }

    // ---- A30 --------------------------------------------------------------------------------

    public function test_store_when_authorization_denied_on_capability_allowed_route_returns_403(): void
    {
        $this->deny('create');

        $this->postJson(route('bpadmin.entity.store', ['entity' => 'reports']), ['title' => 'X'])->assertForbidden();
    }

    public function test_update_when_authorization_denied_on_capability_allowed_route_returns_403(): void
    {
        $this->deny('update');

        $this->putJson(route('bpadmin.entity.update', ['entity' => 'reports', 'id' => 1]), ['title' => 'X'])->assertForbidden();
    }

    public function test_destroy_when_authorization_denied_on_capability_allowed_route_returns_403(): void
    {
        $this->deny('delete');

        $this->deleteJson(route('bpadmin.entity.destroy', ['entity' => 'reports', 'id' => 1]))->assertForbidden();
    }

    public function test_store_on_read_only_entity_returns_404_even_for_fully_authorized_user(): void
    {
        $this->postJson(route('bpadmin.entity.store', ['entity' => 'read_only_reports']), ['title' => 'X'])->assertNotFound();
    }

    public function test_store_on_read_only_entity_returns_404_not_403_when_authorization_also_denied(): void
    {
        $this->deny('create');

        $this->postJson(route('bpadmin.entity.store', ['entity' => 'read_only_reports']), ['title' => 'X'])->assertNotFound();
    }

    // ---- A31 --------------------------------------------------------------------------------

    public function test_create_form_on_non_creatable_entity_returns_404(): void
    {
        $this->getJson(route('bpadmin.entity.create', ['entity' => 'non_creatable_reports']))->assertNotFound();
    }

    public function test_store_on_non_creatable_entity_returns_404_and_persists_nothing(): void
    {
        $this->postJson(route('bpadmin.entity.store', ['entity' => 'non_creatable_reports']), ['title' => 'X'])->assertNotFound();

        self::assertCount(1, ArrayStore::$rows);
    }

    public function test_update_on_non_creatable_entity_still_succeeds(): void
    {
        $this->putJson(route('bpadmin.entity.update', ['entity' => 'non_creatable_reports', 'id' => 1]), ['title' => 'New'])->assertOk();

        self::assertSame('New', ArrayStore::$rows[1]['title'] ?? null);
    }

    // ---- CapabilityDeniedException => 404 ---------------------------------------------------

    public function test_store_when_use_case_throws_capability_denied_returns_404(): void
    {
        $this->postJson(route('bpadmin.entity.store', ['entity' => 'capability_denying_reports']), ['title' => 'X'])->assertNotFound();
    }

    public function test_update_when_use_case_throws_capability_denied_returns_404(): void
    {
        $this->putJson(route('bpadmin.entity.update', ['entity' => 'capability_denying_reports', 'id' => 1]), ['title' => 'X'])->assertNotFound();
    }

    public function test_destroy_when_use_case_throws_capability_denied_returns_404(): void
    {
        $this->deleteJson(route('bpadmin.entity.destroy', ['entity' => 'capability_denying_reports', 'id' => 1]))->assertNotFound();
    }
}
