<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration;

use BlackParadise\CoreAdmin\Domain\Fields\BelongsToField;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\CoreAdmin\Domain\Query\Criteria;
use BlackParadise\CoreAdmin\Domain\Repositories\EntityRepositoryInterface;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\CreatesTestItemTable;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestCategory;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItem;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Verifies that the list() path issues a projected SELECT (column list)
 * instead of SELECT *.
 */
final class ListColumnProjectionTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTestItemTable;

    private EntityRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestItemFixtures();

        $this->repository = $this->app->make(EntityRepositoryInterface::class);
    }

    public function test_list_selects_only_definition_declared_columns_not_star(): void
    {
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
                    TextField::make('email'),
                ];
            }
        };

        TestItem::create(['name' => 'Alice', 'email' => 'alice@example.com']);

        DB::enableQueryLog();

        $this->repository->list($definition, new Criteria(page: 1, perPage: 10));

        $selects = array_values(array_filter(
            DB::getQueryLog(),
            fn(array $q): bool => str_starts_with(strtolower((string) $q['query']), 'select'),
        ));

        $dataQuery = collect($selects)->first(
            fn($q): bool => ! str_contains(strtolower((string) $q['query']), 'count(*)'),
        )['query'] ?? '';

        $this->assertNotEmpty($dataQuery, 'No data SELECT query was logged.');
        $this->assertStringNotContainsStringIgnoringCase('select *', $dataQuery);
    }

    public function test_list_includes_primary_key_and_belongs_to_fk_in_projection(): void
    {
        Schema::table('test_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('category_id')->nullable();
        });

        Schema::create('test_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->timestamps();
        });

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
                    BelongsToField::make('category_id', TestCategory::class)
                        ->withDisplayField('title'),
                ];
            }
        };

        $category = TestCategory::create(['title' => 'Tech']);
        TestItem::create(['name' => 'Bob', 'email' => 'bob@example.com', 'category_id' => $category->id]);

        DB::enableQueryLog();

        $result = $this->repository->list($definition, new Criteria(page: 1, perPage: 10));

        $selects = array_values(array_filter(
            DB::getQueryLog(),
            fn(array $q): bool => str_starts_with(strtolower((string) $q['query']), 'select'),
        ));

        $dataQuery = collect($selects)->first(
            fn($q): bool => ! str_contains(strtolower((string) $q['query']), 'count(*)'),
        )['query'] ?? '';

        $this->assertNotEmpty($dataQuery, 'No data SELECT query was logged.');
        $this->assertStringNotContainsStringIgnoringCase('select *', $dataQuery);

        // BelongsTo FK column must be projected so the relation can load.
        $this->assertStringContainsString('category_id', $dataQuery);

        // Result must still have data.
        $this->assertCount(1, $result->items);
        $this->assertSame('Bob', $result->items[0]->get('name'));
    }
}
