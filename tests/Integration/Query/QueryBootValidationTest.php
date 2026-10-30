<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Query;

use BlackParadise\CoreAdmin\Domain\Contracts\Fields\FieldContract;
use BlackParadise\CoreAdmin\Domain\Exceptions\InvalidDefinitionException;
use BlackParadise\CoreAdmin\Domain\Fields\BelongsToManyField;
use BlackParadise\CoreAdmin\Domain\Fields\HasManyField;
use BlackParadise\CoreAdmin\Domain\Fields\HasOneField;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\CoreAdmin\Domain\Fields\TranslatableField;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\Query\FieldQueryRegistry;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestComment;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItem;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestProfile;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestTag;
use BlackParadise\LaravelAdmin\Tests\Integration\Query\Concerns\BuildsSearchableArticles;
use BlackParadise\LaravelAdmin\Tests\TestCase;

/**
 * Task 2.3 — A43 (field part): marking a non-queryable field searchable / sortable / filterable
 * fails with InvalidDefinitionException naming the field.
 *
 * Pinned API: FieldQueryRegistry::assertQueryable(EntityDefinitionContract $definition): void
 * (throws InvalidDefinitionException; the message contains the offending field name).
 */
final class QueryBootValidationTest extends TestCase
{
    use BuildsSearchableArticles;


    private const REGISTRY = FieldQueryRegistry::class;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stubQueryHasher();
    }

    private function assertQueryable(EntityDefinition $definition): void
    {
        self::assertTrue(class_exists(self::REGISTRY), 'FieldQueryRegistry must exist');

        app()->make(self::REGISTRY)->assertQueryable($definition);
    }

    /**
     * @param list<FieldContract> $fields
     * @param list<string> $search
     */
    private function definition(array $fields, array $search = []): EntityDefinition
    {
        return new class ($fields, $search) extends EntityDefinition {
            public string $model = TestItem::class;

            /**
             * @param list<FieldContract> $fieldList
             * @param list<string> $searchList
             */
            public function __construct(private readonly array $fieldList, private readonly array $searchList) {}

            public function resolveName(): string
            {
                return 'boot_validation';
            }

            public function fields(): array
            {
                return $this->fieldList;
            }

            public function searchFields(): array
            {
                return $this->searchList;
            }
        };
    }

    public function test_searchable_has_many_field_throws_naming_the_field(): void
    {
        $definition = $this->definition([TextField::make('name'), HasManyField::make('comments', TestComment::class)], ['name', 'comments']);

        $this->expectException(InvalidDefinitionException::class);
        $this->expectExceptionMessage('comments');

        $this->assertQueryable($definition);
    }

    public function test_sortable_has_one_field_throws_naming_the_field(): void
    {
        $definition = $this->definition([HasOneField::make('profile', TestProfile::class)->sortable()]);

        $this->expectException(InvalidDefinitionException::class);
        $this->expectExceptionMessage('profile');

        $this->assertQueryable($definition);
    }

    public function test_filterable_belongs_to_many_field_throws_naming_the_field(): void
    {
        $definition = $this->definition([BelongsToManyField::make('tags', TestTag::class)->filterable()]);

        $this->expectException(InvalidDefinitionException::class);
        $this->expectExceptionMessage('tags');

        $this->assertQueryable($definition);
    }

    public function test_non_queryable_field_without_query_flags_is_accepted(): void
    {
        $definition = $this->definition([TextField::make('name'), HasManyField::make('comments', TestComment::class)], ['name']);

        $this->assertQueryable($definition);
    }

    public function test_queryable_fields_with_all_query_flags_are_accepted(): void
    {
        $definition = $this->definition(
            [TextField::make('name')->sortable()->filterable(), TranslatableField::make('title')->sortable()],
            ['name', 'title'],
        );

        $this->assertQueryable($definition);
    }
}
