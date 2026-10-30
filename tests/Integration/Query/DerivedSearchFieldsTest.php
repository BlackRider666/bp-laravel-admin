<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Query;

use BlackParadise\CoreAdmin\Domain\Contracts\Fields\FieldContract;
use BlackParadise\CoreAdmin\Domain\Exceptions\InvalidDefinitionException;
use BlackParadise\CoreAdmin\Domain\Fields\HasManyField;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\Query\FieldQueryRegistry;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\CreatesTestItemTable;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestComment;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItem;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * F-1: the default EntityDefinition::searchFields() derives from fields marked ->searchable().
 */
final class DerivedSearchFieldsTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTestItemTable;

    /**
     * @param list<FieldContract> $fields
     * @param list<string>|null $explicit
     */
    private function definition(array $fields, ?array $explicit = null, string $name = 'derived_search'): EntityDefinition
    {
        return new class ($fields, $explicit, $name) extends EntityDefinition {
            public string $model = TestItem::class;

            /**
             * @param list<FieldContract> $fieldList
             * @param list<string>|null $explicit
             */
            public function __construct(
                private readonly array $fieldList,
                private readonly ?array $explicit,
                private readonly string $entityName,
            ) {}

            public function resolveName(): string
            {
                return $this->entityName;
            }

            public function fields(): array
            {
                return $this->fieldList;
            }

            public function searchFields(): array
            {
                return $this->explicit ?? parent::searchFields();
            }
        };
    }

    public function test_search_fields_returns_searchable_field_names_in_field_order(): void
    {
        $definition = $this->definition([
            TextField::make('email')->searchable(),
            TextField::make('note'),
            TextField::make('name')->searchable(),
        ]);

        self::assertSame(['email', 'name'], $definition->searchFields());
    }

    public function test_search_fields_when_no_field_is_searchable_returns_empty_array(): void
    {
        $definition = $this->definition([TextField::make('name'), TextField::make('email')->filterable()]);

        self::assertSame([], $definition->searchFields());
    }

    public function test_search_fields_excludes_filterable_only_fields(): void
    {
        $definition = $this->definition([TextField::make('name')->searchable(), TextField::make('email')->filterable()]);

        self::assertSame(['name'], $definition->searchFields());
    }

    public function test_search_fields_explicit_override_wins_over_searchable_flags(): void
    {
        $definition = $this->definition([TextField::make('name')->searchable(), TextField::make('email')], ['email']);

        self::assertSame(['email'], $definition->searchFields());
    }

    public function test_default_search_fields_of_a_subclass_without_override_derive_from_flags(): void
    {
        $definition = new class extends EntityDefinition {
            public string $model = TestItem::class;

            public function resolveName(): string
            {
                return 'derived_no_override';
            }

            public function fields(): array
            {
                return [TextField::make('name')->searchable()];
            }
        };

        self::assertSame(['name'], $definition->searchFields());
    }

    public function test_assert_queryable_fails_for_derived_non_queryable_searchable_field(): void
    {
        $definition = $this->definition([TextField::make('name'), HasManyField::make('comments', TestComment::class)->searchable()]);

        $this->expectException(InvalidDefinitionException::class);
        $this->expectExceptionMessageMatches('/comments/');

        resolve(FieldQueryRegistry::class)->assertQueryable($definition);
    }

    public function test_list_endpoint_q_param_searches_derived_fields_only(): void
    {
        $this->setUpTestItemFixtures();
        $this->loginAsUser();
        resolve(EntityDefinitionRegistry::class)->register($this->definition([
            TextField::make('name')->searchable(),
            TextField::make('email'),
        ], null, 'derived_search_http'));
        TestItem::create(['name' => 'Alice Wonder', 'email' => 'zzz@example.com']);
        TestItem::create(['name' => 'Bob', 'email' => 'alice@example.com']);

        $response = $this->getJson('/admin/derived_search_http?q=alice');

        $response->assertOk();
        self::assertSame(['Alice Wonder'], array_column($response->json('data'), 'name'));
    }
}
