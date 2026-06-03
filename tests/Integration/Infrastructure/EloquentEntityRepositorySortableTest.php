<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Infrastructure;

use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\CoreAdmin\Domain\Query\Criteria;
use BlackParadise\CoreAdmin\Domain\Query\Sort;
use BlackParadise\CoreAdmin\Domain\Repositories\EntityRepositoryInterface;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItem;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Integration tests for Bug #11 — sort_by restricted to isSortable() fields.
 *
 * Verifies that the repository rejects sort attempts on non-sortable fields
 * and allows sort on fields declared with ->sortable().
 */
final class EloquentEntityRepositorySortableTest extends TestCase
{
    use RefreshDatabase;

    private EntityRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('test_items', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->timestamps();
        });

        $this->repository = $this->app->make(EntityRepositoryInterface::class);
    }

    public function test_sort_on_sortable_field_is_allowed(): void
    {
        TestItem::create(['name' => 'Zoe', 'email' => 'z@t.com']);
        TestItem::create(['name' => 'Amy', 'email' => 'a@t.com']);

        $definition = new class extends EntityDefinition {
            public string $model = TestItem::class;
            public function resolveName(): string
            {
                return 'test_item';
            }
            public function fields(): array
            {
                return [
                    TextField::make('name')->sortable(),
                    TextField::make('email'),
                ];
            }
        };

        $result = $this->repository->list($definition, new Criteria(sort: [new Sort('name', 'asc')]));

        self::assertCount(2, $result->items);
        self::assertSame('Amy', $result->items[0]->get('name'));
        self::assertSame('Zoe', $result->items[1]->get('name'));
    }

    public function test_sort_on_non_sortable_field_throws(): void
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
                    TextField::make('name'),  // NOT sortable
                    TextField::make('email'),
                ];
            }
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Field 'name' is not allowed for sorting.");

        $this->repository->list($definition, new Criteria(sort: [new Sort('name', 'asc')]));
    }

    public function test_sort_on_completely_unknown_field_throws(): void
    {
        $definition = new class extends EntityDefinition {
            public string $model = TestItem::class;
            public function resolveName(): string
            {
                return 'test_item';
            }
            public function fields(): array
            {
                return [TextField::make('name')->sortable()];
            }
        };

        $this->expectException(InvalidArgumentException::class);

        $this->repository->list($definition, new Criteria(sort: [new Sort('not_a_field', 'asc')]));
    }
}
