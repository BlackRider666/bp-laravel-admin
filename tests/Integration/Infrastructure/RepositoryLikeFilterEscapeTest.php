<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Infrastructure;

use BlackParadise\CoreAdmin\Domain\Query\Criteria;
use BlackParadise\CoreAdmin\Domain\Query\Filter;
use BlackParadise\CoreAdmin\Domain\Repositories\EntityRepositoryInterface;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\CreatesTestItemTable;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItem;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItemFilterableDefinition;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * A19 (B18): The 'like' filter operator must escape user-supplied % and _ as literals.
 *
 * Bug: applyFilter() passes the raw value to ->where($field, 'like', $value), so
 * a user-supplied '50%' becomes a wildcard that matches "50anything" instead of
 * only the literal string "50%".
 *
 * Fix: in applyFilter(), when operator === 'like', escape %, _, and \ in the value
 * before passing it to the query builder (same escaping already used by the
 * full-text search path).
 */
final class RepositoryLikeFilterEscapeTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTestItemTable;

    private EntityRepositoryInterface $repository;
    private TestItemFilterableDefinition $definition;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestItemFixtures();

        $this->repository = $this->app->make(EntityRepositoryInterface::class);
        $this->definition = new TestItemFilterableDefinition();
    }

    // ------------------------------------------------------------------
    // A19 — literal % in filter value does not match as wildcard
    // ------------------------------------------------------------------

    /**
     * A filter of 'name LIKE "50%"' must be treated as the literal string "50%",
     * not as a pattern matching "50anything".
     *
     * Currently FAILS: unescaped % acts as wildcard → items named "50X", "50Y" match.
     */
    public function test_percent_in_like_filter_is_treated_as_literal(): void
    {
        TestItem::create(['name' => '50%',      'email' => 'a@test.com']); // literal match
        TestItem::create(['name' => '50percent', 'email' => 'b@test.com']); // should NOT match
        TestItem::create(['name' => '50',        'email' => 'c@test.com']); // should NOT match

        $criteria = new Criteria(
            filters: [new Filter('name', '50%', 'like')],
        );

        $result = $this->repository->list($this->definition, $criteria);

        // Only '50%' should match — not '50percent' or '50'.
        $this->assertCount(1, $result->items);
        $this->assertSame('50%', $result->items[0]->get('name'));
    }

    // ------------------------------------------------------------------
    // A19 — literal _ in filter value does not match as single-char wildcard
    // ------------------------------------------------------------------

    /**
     * A filter of 'name LIKE "a_b"' must only match the literal string "a_b",
     * not "axb" or "ayb".
     *
     * Currently FAILS: unescaped _ acts as wildcard.
     */
    public function test_underscore_in_like_filter_is_treated_as_literal(): void
    {
        TestItem::create(['name' => 'a_b', 'email' => 'x@test.com']); // literal match
        TestItem::create(['name' => 'axb', 'email' => 'y@test.com']); // must NOT match
        TestItem::create(['name' => 'ayb', 'email' => 'z@test.com']); // must NOT match

        $criteria = new Criteria(
            filters: [new Filter('name', 'a_b', 'like')],
        );

        $result = $this->repository->list($this->definition, $criteria);

        $this->assertCount(1, $result->items);
        $this->assertSame('a_b', $result->items[0]->get('name'));
    }

    // ------------------------------------------------------------------
    // A19 — normal = filter still works as exact match
    // ------------------------------------------------------------------

    public function test_exact_filter_is_unaffected_by_like_escape_fix(): void
    {
        TestItem::create(['name' => 'Alice', 'email' => 'a@test.com']);
        TestItem::create(['name' => 'Bob',   'email' => 'b@test.com']);

        $criteria = new Criteria(
            filters: [new Filter('name', 'Alice', '=')],
        );

        $result = $this->repository->list($this->definition, $criteria);

        $this->assertCount(1, $result->items);
        $this->assertSame('Alice', $result->items[0]->get('name'));
    }
}
