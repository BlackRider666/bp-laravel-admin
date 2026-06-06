<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Http\Controllers;

use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\CreatesTestItemTable;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\StubsValueHasher;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItem;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItemFilterableDefinition;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * A16 (B14): GET /{entity}?filter[x][]=y (array value) must NOT return HTTP 500.
 *
 * Bug: when a filter value is an array (e.g. filter[name][]=Alice), the request
 * passes the 'filter.*' validation (or lack thereof) and the array value reaches
 * htmlspecialchars() in the Blade layer, crashing with "htmlspecialchars(): Argument
 * #1 ($string) must be of type string, array given".
 *
 * Fix: add 'filter.*' => ['nullable', 'string'] validation rule to EntityIndexRequest
 * so array-valued filters fail validation (HTTP 422) instead of crashing.
 *
 * In JSON mode (no Blade), the fix must ensure array values are rejected by
 * validation (422) — since they could cause type errors in the repository layer
 * or silently produce wrong SQL.
 */
final class AdminEntityControllerFilterArrayTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTestItemTable;
    use StubsValueHasher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stubValueHasher(); // must be first — prevents LaravelValueHasher fatal
        $this->setUpTestItemFixtures();

        /** @var EntityDefinitionRegistry $registry */
        $registry = $this->app->make(EntityDefinitionRegistry::class);
        $registry->register(new TestItemFilterableDefinition());

        $this->loginAsUser();
    }

    // ------------------------------------------------------------------
    // A16 — array filter value is rejected by validation (422)
    // ------------------------------------------------------------------

    /**
     * GET /admin/test_item?filter[name][]=Alice passes an array as the filter value.
     * After the fix, 'filter.*' => 'string' validation rejects it with HTTP 422.
     *
     * Currently FAILS: no 'filter.*' rule exists, so the array passes validation
     * and returns HTTP 200 with potentially wrong results.
     *
     * Note: the spec says "не дає 500" but also says validation rejects arrays.
     * The correct post-fix state is 422 (validation error), not 200.
     */
    public function test_array_filter_value_is_rejected_by_validation_with_422(): void
    {
        $response = $this->getJson('/admin/test_item?filter[name][]=Alice');

        // After fix: array-valued filter rejected → 422.
        // Currently: no filter.* rule → 200 (array bypasses validation).
        $response->assertStatus(422);
    }

    // ------------------------------------------------------------------
    // A16 — scalar filter value is still valid (200)
    // ------------------------------------------------------------------

    /**
     * Normal scalar filter must still work after the fix.
     */
    public function test_scalar_filter_value_is_accepted(): void
    {
        TestItem::create([
            'name'  => 'Alice',
            'email' => 'alice@example.com',
        ]);
        TestItem::create([
            'name'  => 'Bob',
            'email' => 'bob@example.com',
        ]);

        $response = $this->getJson('/admin/test_item?filter[name]=Alice');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }
}
