<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Feature\Http;

use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\ActsAsPermissiveAdmin;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ModelLess\ArrayStore;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ModelLess\ReadOnlyReports;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ModelLess\Reports;
use BlackParadise\LaravelAdmin\Tests\TestCase;

/**
 * A33: a model-less definition (no $model) with a custom repository + mutator supports full CRUD over
 * HTTP; one without a mutator is read-only. (Boot errors for "writable without mutator": see
 * ModelLessBootValidationTest. Precise capability status codes: Task 4.6b.)
 */
final class ModelLessDefinitionTest extends TestCase
{
    use ActsAsPermissiveAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stubHasher();
        $this->permitEverything();
        $this->loginAdmin();

        ArrayStore::reset();
        $registry = app()->make(EntityDefinitionRegistry::class);
        $registry->register(new Reports());
        $registry->register(new ReadOnlyReports());
    }

    protected function tearDown(): void
    {
        ArrayStore::reset();
        parent::tearDown();
    }

    public function test_index_lists_records_from_the_custom_repository(): void
    {
        ArrayStore::reset([1 => ['id' => 1, 'title' => 'Q1'], 2 => ['id' => 2, 'title' => 'Q2']]);

        $response = $this->getJson(route('bpadmin.entity.index', ['entity' => 'reports']));

        $response->assertOk();
        self::assertSame(['Q1', 'Q2'], array_column((array) $response->json('data'), 'title'));
    }

    public function test_show_returns_the_record_from_the_custom_repository(): void
    {
        ArrayStore::reset([3 => ['id' => 3, 'title' => 'Annual']]);

        $response = $this->getJson(route('bpadmin.entity.show', ['entity' => 'reports', 'id' => 3]));

        $response->assertOk()->assertJsonPath('data.title', 'Annual');
    }

    public function test_store_persists_through_the_custom_mutator(): void
    {
        $response = $this->postJson(route('bpadmin.entity.store', ['entity' => 'reports']), ['title' => 'Created']);

        $response->assertCreated();
        self::assertSame(['Created'], array_column(ArrayStore::$rows, 'title'));
    }

    public function test_update_persists_through_the_custom_mutator(): void
    {
        ArrayStore::reset([1 => ['id' => 1, 'title' => 'Old']]);

        $response = $this->putJson(route('bpadmin.entity.update', ['entity' => 'reports', 'id' => 1]), ['title' => 'New']);

        $response->assertOk();
        self::assertSame('New', ArrayStore::$rows[1]['title'] ?? null);
    }

    public function test_destroy_removes_the_record_through_the_custom_mutator(): void
    {
        ArrayStore::reset([1 => ['id' => 1, 'title' => 'Doomed']]);

        $this->deleteJson(route('bpadmin.entity.destroy', ['entity' => 'reports', 'id' => 1]))->assertSuccessful();

        self::assertSame([], ArrayStore::$rows);
    }

    public function test_store_validates_required_fields_before_touching_the_custom_mutator(): void
    {
        $response = $this->postJson(route('bpadmin.entity.store', ['entity' => 'reports']), ['title' => '']);

        $response->assertStatus(422);
        self::assertSame([], ArrayStore::$rows);
    }

    public function test_read_only_model_less_definition_still_lists_records(): void
    {
        ArrayStore::reset([1 => ['id' => 1, 'title' => 'Visible']]);

        $response = $this->getJson(route('bpadmin.entity.index', ['entity' => 'read_only_reports']));

        $response->assertOk();
        self::assertSame(['Visible'], array_column((array) $response->json('data'), 'title'));
    }

    public function test_read_only_model_less_definition_rejects_store_and_changes_nothing(): void
    {
        $response = $this->postJson(route('bpadmin.entity.store', ['entity' => 'read_only_reports']), ['title' => 'Nope']);

        $response->assertClientError();
        self::assertSame([], ArrayStore::$rows);
    }

    public function test_read_only_model_less_definition_rejects_destroy_and_keeps_the_record(): void
    {
        ArrayStore::reset([1 => ['id' => 1, 'title' => 'Keep']]);

        $response = $this->deleteJson(route('bpadmin.entity.destroy', ['entity' => 'read_only_reports', 'id' => 1]));

        $response->assertClientError();
        self::assertCount(1, ArrayStore::$rows);
    }
}
