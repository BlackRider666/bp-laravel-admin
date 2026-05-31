<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Infrastructure;

use BlackParadise\CoreAdmin\Domain\Contracts\Entity\EntityRecordContract;
use BlackParadise\CoreAdmin\Domain\Fields\RelationPathField;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\CoreAdmin\Domain\Query\Criteria;
use BlackParadise\CoreAdmin\Domain\Query\Filter;
use BlackParadise\CoreAdmin\Domain\Query\PaginatedResult;
use BlackParadise\CoreAdmin\Domain\Query\Sort;
use BlackParadise\CoreAdmin\Domain\Repositories\EntityRepositoryInterface;
use BlackParadise\CoreAdmin\Domain\ValueObjects\EntityKey;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\CreatesTestItemTable;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestCategory;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItem;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItemDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItemWithCategoryDefinition;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Integration tests for EloquentEntityRepository.
 *
 * Tests run against a real in-memory SQLite database to verify that the
 * Eloquent implementation correctly translates domain Criteria into SQL queries.
 */
final class EloquentEntityRepositoryTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTestItemTable;

    private EntityRepositoryInterface $repository;
    private TestItemDefinition $definition;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestItemFixtures();

        $this->repository = $this->app->make(EntityRepositoryInterface::class);
        $this->definition = new TestItemDefinition();
    }

    // ------------------------------------------------------------------
    // list()
    // ------------------------------------------------------------------

    public function test_list_returns_paginated_result_instance(): void
    {
        $result = $this->repository->list($this->definition, new Criteria());

        $this->assertInstanceOf(PaginatedResult::class, $result);
    }

    public function test_list_returns_empty_items_when_table_is_empty(): void
    {
        $result = $this->repository->list($this->definition, new Criteria());

        $this->assertCount(0, $result->items);
        $this->assertSame(0, $result->total);
    }

    public function test_list_returns_all_records_when_within_page_size(): void
    {
        TestItem::create(['name' => 'Alice', 'email' => 'alice@example.com']);
        TestItem::create(['name' => 'Bob', 'email' => 'bob@example.com']);

        $result = $this->repository->list($this->definition, new Criteria());

        $this->assertCount(2, $result->items);
        $this->assertSame(2, $result->total);
    }

    public function test_list_respects_per_page_limit(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            TestItem::create(['name' => "Item $i", 'email' => "item{$i}@example.com"]);
        }

        $result = $this->repository->list($this->definition, new Criteria(perPage: 3));

        $this->assertCount(3, $result->items);
        $this->assertSame(5, $result->total);
    }

    public function test_list_returns_second_page_correctly(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            TestItem::create(['name' => "Item $i", 'email' => "item{$i}@example.com"]);
        }

        $result = $this->repository->list($this->definition, new Criteria(page: 2, perPage: 3));

        $this->assertCount(2, $result->items);
        $this->assertSame(2, $result->page);
    }

    public function test_list_with_sort_orders_results_ascending(): void
    {
        TestItem::create(['name' => 'Zoe', 'email' => 'zoe@example.com']);
        TestItem::create(['name' => 'Amy', 'email' => 'amy@example.com']);

        $sort   = new Sort('name', 'asc');
        $result = $this->repository->list($this->definition, new Criteria(sort: [$sort]));

        $names = array_map(fn(EntityRecordContract $r): mixed => $r->get('name'), $result->items);
        $this->assertSame(['Amy', 'Zoe'], $names);
    }

    public function test_list_with_sort_orders_results_descending(): void
    {
        TestItem::create(['name' => 'Amy', 'email' => 'amy@example.com']);
        TestItem::create(['name' => 'Zoe', 'email' => 'zoe@example.com']);

        $sort   = new Sort('name', 'desc');
        $result = $this->repository->list($this->definition, new Criteria(sort: [$sort]));

        $names = array_map(fn(EntityRecordContract $r): mixed => $r->get('name'), $result->items);
        $this->assertSame(['Zoe', 'Amy'], $names);
    }

    public function test_list_with_filter_returns_matching_records_only(): void
    {
        TestItem::create(['name' => 'Alice', 'email' => 'alice@example.com']);
        TestItem::create(['name' => 'Bob', 'email' => 'bob@example.com']);

        $filter = new Filter('name', 'Alice', '=');
        $result = $this->repository->list($this->definition, new Criteria(filters: [$filter]));

        $this->assertCount(1, $result->items);
        $this->assertSame('Alice', $result->items[0]->get('name'));
    }

    public function test_list_with_invalid_field_throws_invalid_argument_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $filter = new Filter('not_a_field', 'value', '=');
        $this->repository->list($this->definition, new Criteria(filters: [$filter]));
    }

    // ------------------------------------------------------------------
    // find()
    // ------------------------------------------------------------------

    public function test_find_returns_entity_record_for_existing_id(): void
    {
        $item = TestItem::create(['name' => 'Carol', 'email' => 'carol@example.com']);

        $key    = new EntityKey($item->id, 'int');
        $record = $this->repository->find($this->definition, $key);

        $this->assertNotNull($record);
        $this->assertSame('Carol', $record->get('name'));
    }

    public function test_find_returns_null_for_nonexistent_id(): void
    {
        $key    = new EntityKey(99999, 'int');
        $record = $this->repository->find($this->definition, $key);

        $this->assertNull($record);
    }

    // ------------------------------------------------------------------
    // exists()
    // ------------------------------------------------------------------

    public function test_exists_returns_true_when_record_is_present(): void
    {
        $item = TestItem::create(['name' => 'Dan', 'email' => 'dan@example.com']);

        $key = new EntityKey($item->id, 'int');

        $this->assertTrue($this->repository->exists($this->definition, $key));
    }

    public function test_exists_returns_false_when_record_is_absent(): void
    {
        $key = new EntityKey(99999, 'int');

        $this->assertFalse($this->repository->exists($this->definition, $key));
    }

    // ------------------------------------------------------------------
    // Eager loading
    // ------------------------------------------------------------------

    /**
     * Create the auxiliary tables needed by eager-loading tests.
     *
     * `test_items` already exists (from CreatesTestItemTable), so we only add
     * the nullable `category_id` column and create `test_categories`.
     */
    private function setUpEagerLoadFixtures(): void
    {
        Schema::table('test_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('category_id')->nullable();
        });

        Schema::create('test_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->timestamps();
        });
    }

    public function test_list_eager_loads_belongs_to_relations(): void
    {
        $this->setUpEagerLoadFixtures();

        $category = TestCategory::create(['title' => 'Science']);
        TestItem::create(['name' => 'Alice', 'email' => 'alice@example.com', 'category_id' => $category->id]);

        $definition = new TestItemWithCategoryDefinition();
        $result     = $this->repository->list($definition, new Criteria());

        $this->assertCount(1, $result->items);

        $record         = $result->items[0];
        $categoryData   = $record->relation('category');

        $this->assertIsArray($categoryData);
        $this->assertSame('Science', $categoryData['title']);
    }

    public function test_find_eager_loads_belongs_to_relations(): void
    {
        $this->setUpEagerLoadFixtures();

        $category = TestCategory::create(['title' => 'Technology']);
        $item     = TestItem::create(['name' => 'Bob', 'email' => 'bob@example.com', 'category_id' => $category->id]);

        $definition = new TestItemWithCategoryDefinition();
        $key        = new EntityKey($item->id, 'int');
        $record     = $this->repository->find($definition, $key);

        $this->assertNotNull($record);

        $categoryData = $record->relation('category');

        $this->assertIsArray($categoryData);
        $this->assertSame('Technology', $categoryData['title']);
    }

    public function test_list_eager_loads_relation_path_prefix(): void
    {
        $this->setUpEagerLoadFixtures();

        $cat = TestCategory::create(['title' => 'Electronics']);
        TestItem::create(['name' => 'Phone', 'email' => 'p@t.com', 'category_id' => $cat->id]);

        // Definition має RelationPathField('category.title') — не RelationFieldContract.
        $definition = new class extends EntityDefinition {
            public string $model = TestItem::class;
            public function resolveName(): string
            {
                return 'test_item';
            }
            public function fields(): array
            {
                return [
                    TextField::make('name'),
                    RelationPathField::make('category.title'),
                ];
            }
        };

        $result = $this->repository->list($definition, new Criteria());

        self::assertCount(1, $result->items);
        $relation = $result->items[0]->relation('category');
        self::assertNotNull($relation, 'Category relation has not been eager-loaded.');
        self::assertSame('Electronics', $relation['title']);
    }
}
