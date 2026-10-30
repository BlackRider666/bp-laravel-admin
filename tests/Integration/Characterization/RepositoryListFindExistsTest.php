<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Characterization;

use BlackParadise\CoreAdmin\Domain\Contracts\Entity\EntityRecordContract;
use BlackParadise\CoreAdmin\Domain\Fields\TranslatableField;
use BlackParadise\CoreAdmin\Domain\Query\Criteria;
use BlackParadise\CoreAdmin\Domain\Query\Filter;
use BlackParadise\CoreAdmin\Domain\Query\PaginatedResult;
use BlackParadise\CoreAdmin\Domain\Query\Sort;
use BlackParadise\CoreAdmin\Domain\Repositories\EntityRepositoryInterface;
use BlackParadise\CoreAdmin\Domain\ValueObjects\EntityKey;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\CreatesEmbeddedRelationFixtures;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\CreatesRelationFixtures;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\CreatesTestMorphFileTables;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\StubsValueHasher;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestAccount;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestAccountDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestArticle;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestCategory;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestComment;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItem;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItemDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItemFilterableDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItemWithCategoryDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItemWithRelationsDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestMorphedFile;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestProfile;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestPublicationDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestTag;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Task 0.3 / A42 baseline — CHARACTERIZATION of EloquentEntityRepository.
 *
 * Snapshots the CURRENT result of list()/find()/exists() for filters, search
 * (plain + translatable), sort, column projection, eager-load and embedded
 * relations. Every test is GREEN today. W2 rewrites persistence internals and
 * must keep these results identical (A42); tests marked `BUG:` are the only
 * ones W2 flips deliberately.
 */
final class RepositoryListFindExistsTest extends TestCase
{
    use RefreshDatabase;
    use StubsValueHasher;
    use CreatesRelationFixtures;
    use CreatesEmbeddedRelationFixtures;
    use CreatesTestMorphFileTables;

    private EntityRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stubValueHasher(); // must be first

        Schema::create('test_items', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->timestamps();
        });
        Schema::create('test_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->timestamps();
        });
        Schema::create('test_articles', function (Blueprint $table): void {
            $table->id();
            $table->json('title')->nullable();
            $table->timestamps();
        });
        $this->setUpRelationFixtures();
        $this->setUpEmbeddedRelationFixtures();
        $this->setUpMorphFileFixtures();

        $this->repository = $this->app->make(EntityRepositoryInterface::class);
    }

    // ------------------------------------------------------------------
    // helpers
    // ------------------------------------------------------------------

    private function seedItems(): void
    {
        TestItem::create(['name' => 'Alice', 'email' => 'alice@example.com']);
        TestItem::create(['name' => 'Bob', 'email' => 'bob@example.com']);
        TestItem::create(['name' => 'Carol', 'email' => 'carol@example.org']);
        TestItem::create(['name' => '100%_real', 'email' => 'pct@example.com']);
    }

    /** @return list<mixed> */
    private function names(PaginatedResult $result, string $field = 'name'): array
    {
        return array_map(fn(EntityRecordContract $r): mixed => $r->get($field), $result->items);
    }

    private function searchableArticleDefinition(): EntityDefinition
    {
        return new class extends EntityDefinition {
            public string $model = TestArticle::class;

            public function resolveName(): string
            {
                return 'test_article_searchable';
            }

            public function fields(): array
            {
                return [TranslatableField::make('title')->sortable()];
            }

            public function searchFields(): array
            {
                return ['title'];
            }
        };
    }

    private function seedArticles(): void
    {
        // Stored exactly as the mutator stores them (JSON_UNESCAPED_UNICODE).
        DB::table('test_articles')->insert([
            ['title' => json_encode(['en' => 'Whitney Report', 'uk' => 'Звіт'], JSON_UNESCAPED_UNICODE)],
            ['title' => json_encode(['en' => 'Other', 'uk' => 'Інше'], JSON_UNESCAPED_UNICODE)],
        ]);
    }

    // ------------------------------------------------------------------
    // filters
    // ------------------------------------------------------------------

    public function test_filter_equals_returns_exact_matches(): void
    {
        $this->seedItems();

        $result = $this->repository->list(new TestItemFilterableDefinition(), new Criteria(filters: [new Filter('name', 'Bob', '=')]));

        $this->assertSame(['Bob'], $this->names($result));
    }

    public function test_filter_not_equals_excludes_match(): void
    {
        $this->seedItems();

        $result = $this->repository->list(new TestItemFilterableDefinition(), new Criteria(filters: [new Filter('name', 'Bob', '!=')]));

        $this->assertSame(['Alice', 'Carol', '100%_real'], $this->names($result));
    }

    public function test_filter_like_wraps_nothing_and_matches_whole_value_case_insensitively_on_sqlite(): void
    {
        $this->seedItems();

        $exact = $this->repository->list(new TestItemFilterableDefinition(), new Criteria(filters: [new Filter('name', 'alice', 'like')]));
        $partial = $this->repository->list(new TestItemFilterableDefinition(), new Criteria(filters: [new Filter('name', 'Ali', 'like')]));

        $this->assertSame(['Alice'], $this->names($exact));
        $this->assertSame([], $this->names($partial));
    }

    public function test_filter_like_escapes_percent_and_underscore_literally(): void
    {
        $this->seedItems();

        $literal = $this->repository->list(new TestItemFilterableDefinition(), new Criteria(filters: [new Filter('name', '100%_real', 'like')]));
        $wildcard = $this->repository->list(new TestItemFilterableDefinition(), new Criteria(filters: [new Filter('name', '%', 'like')]));

        $this->assertSame(['100%_real'], $this->names($literal));
        $this->assertSame([], $this->names($wildcard));
    }

    public function test_filter_in_and_not_in(): void
    {
        $this->seedItems();

        $in = $this->repository->list(new TestItemFilterableDefinition(), new Criteria(filters: [new Filter('name', ['Alice', 'Carol'], 'in')]));
        $notIn = $this->repository->list(new TestItemFilterableDefinition(), new Criteria(filters: [new Filter('name', ['Alice', 'Carol'], 'not in')]));

        $this->assertSame(['Alice', 'Carol'], $this->names($in));
        $this->assertSame(['Bob', '100%_real'], $this->names($notIn));
    }

    public function test_filter_comparison_operators(): void
    {
        $this->seedItems();

        $gt = $this->repository->list(new TestItemFilterableDefinition(), new Criteria(filters: [new Filter('name', 'Bob', '>')]));
        $lte = $this->repository->list(new TestItemFilterableDefinition(), new Criteria(filters: [new Filter('name', 'Bob', '<=')]));

        $this->assertSame(['Carol'], $this->names($gt));
        $this->assertSame(['Alice', 'Bob', '100%_real'], $this->names($lte));
    }

    public function test_multiple_filters_are_anded(): void
    {
        $this->seedItems();

        $result = $this->repository->list(new TestItemFilterableDefinition(), new Criteria(filters: [
            new Filter('name', ['Alice', 'Bob', 'Carol'], 'in'),
            new Filter('email', 'bob@example.com', '='),
        ]));

        $this->assertSame(['Bob'], $this->names($result));
    }

    public function test_filter_on_undeclared_field_throws_invalid_argument(): void
    {
        $this->seedItems();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Field 'nope' is not allowed for filtering.");

        $this->repository->list(new TestItemFilterableDefinition(), new Criteria(filters: [new Filter('nope', 'x', '=')]));
    }

    // ------------------------------------------------------------------
    // search — plain
    // ------------------------------------------------------------------

    public function test_search_plain_is_substring_or_across_search_fields(): void
    {
        $this->seedItems();

        $byName = $this->repository->list(new TestItemFilterableDefinition(), new Criteria(search: 'ali'));
        $byEmail = $this->repository->list(new TestItemFilterableDefinition(), new Criteria(search: 'example.org'));

        $this->assertSame(['Alice'], $this->names($byName));
        $this->assertSame(['Carol'], $this->names($byEmail));
    }

    public function test_search_plain_percent_and_underscore_match_literally(): void
    {
        // A4 (flipped in W2): `%` / `_` match literally, so both hit the row '100%_real'.
        $this->seedItems();

        $pct = $this->repository->list(new TestItemFilterableDefinition(), new Criteria(search: '%'));
        $under = $this->repository->list(new TestItemFilterableDefinition(), new Criteria(search: '_'));

        $this->assertSame(['100%_real'], $this->names($pct));
        $this->assertSame(['100%_real'], $this->names($under));
    }

    public function test_search_is_ignored_when_definition_has_no_search_fields(): void
    {
        $this->seedItems();

        $result = $this->repository->list(new TestItemDefinition(), new Criteria(search: 'zzz-no-match'));

        $this->assertSame(4, $result->total);
    }

    public function test_search_empty_string_is_ignored(): void
    {
        $this->seedItems();

        $result = $this->repository->list(new TestItemFilterableDefinition(), new Criteria(search: ''));

        $this->assertSame(4, $result->total);
    }

    // ------------------------------------------------------------------
    // search — translatable (as-is)
    // ------------------------------------------------------------------

    public function test_search_translatable_matches_substring_of_raw_json_text(): void
    {
        $this->seedArticles();
        $definition = $this->searchableArticleDefinition();

        $result = $this->repository->list($definition, new Criteria(search: 'Whitney'));

        $this->assertSame(1, $result->total);
        $this->assertSame(['en' => 'Whitney Report', 'uk' => 'Звіт'], $result->items[0]->get('title'));
    }

    public function test_search_translatable_cyrillic_term_matches_unescaped_json(): void
    {
        config()->set('bpadmin.locales', ['en', 'uk']); // explicit: the old green relied on the match-all bug
        $this->seedArticles();

        $result = $this->repository->list($this->searchableArticleDefinition(), new Criteria(search: 'Звіт'));

        $this->assertSame(1, $result->total);
    }

    public function test_search_translatable_locale_key_uk_matches_no_rows(): void
    {
        // A3 (flipped in W2): the JSON key "uk" is not a value; no seeded value contains it.
        $this->seedArticles();

        $result = $this->repository->list($this->searchableArticleDefinition(), new Criteria(search: 'uk'));

        $this->assertSame(0, $result->total);
    }

    public function test_search_translatable_locale_key_en_matches_no_rows(): void
    {
        // A3 (flipped in W2): the JSON key "en" is not a value; no seeded value contains it.
        $this->seedArticles();

        $result = $this->repository->list($this->searchableArticleDefinition(), new Criteria(search: 'en'));

        $this->assertSame(0, $result->total);
    }

    public function test_search_translatable_colon_and_quote_match_no_rows(): void
    {
        // A3 (flipped in W2): JSON punctuation `:` and `"` must not match.
        $this->seedArticles();
        $definition = $this->searchableArticleDefinition();

        $colon = $this->repository->list($definition, new Criteria(search: ':'));
        $quote = $this->repository->list($definition, new Criteria(search: '"'));

        $this->assertSame(0, $colon->total);
        $this->assertSame(0, $quote->total);
    }

    public function test_search_translatable_case_insensitivity_follows_driver_collation(): void
    {
        // On SQLite LIKE is ASCII case-insensitive. (A1 concerns utf8mb4_bin on MySQL; not reproducible here.)
        $this->seedArticles();

        $result = $this->repository->list($this->searchableArticleDefinition(), new Criteria(search: 'WHITNEY'));

        $this->assertSame(1, $result->total);
    }

    public function test_search_translatable_non_matching_term_returns_nothing(): void
    {
        $this->seedArticles();

        $result = $this->repository->list($this->searchableArticleDefinition(), new Criteria(search: 'zzzz'));

        $this->assertSame(0, $result->total);
    }

    // ------------------------------------------------------------------
    // sort
    // ------------------------------------------------------------------

    public function test_sort_asc_and_desc_on_sortable_column(): void
    {
        $this->seedItems();

        $asc = $this->repository->list(new TestItemDefinition(), new Criteria(sort: [new Sort('name', 'asc')]));
        $desc = $this->repository->list(new TestItemDefinition(), new Criteria(sort: [new Sort('name', 'desc')]));

        $this->assertSame(['100%_real', 'Alice', 'Bob', 'Carol'], $this->names($asc));
        $this->assertSame(['Carol', 'Bob', 'Alice', '100%_real'], $this->names($desc));
    }

    public function test_sort_on_non_sortable_field_throws_invalid_argument_only_for_unknown_field(): void
    {
        // Allow-list is "declared field names"; unknown names are rejected.
        $this->seedItems();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Field 'ghost' is not allowed for sorting.");

        $this->repository->list(new TestItemDefinition(), new Criteria(sort: [new Sort('ghost', 'asc')]));
    }

    public function test_sort_translatable_uses_default_locale_value(): void
    {
        config()->set('bpadmin.locales', ['uk', 'en']);
        config()->set('app.locale', 'uk');
        $this->seedArticles();

        $asc = $this->repository->list($this->searchableArticleDefinition(), new Criteria(sort: [new Sort('title', 'asc')]));
        $desc = $this->repository->list($this->searchableArticleDefinition(), new Criteria(sort: [new Sort('title', 'desc')]));

        // Binary (code point) order on SQLite: 'І' (U+0406) < 'З' (U+0417).
        $this->assertSame(['Інше', 'Звіт'], array_map(fn(EntityRecordContract $r): mixed => $r->get('title')['uk'], $asc->items));
        $this->assertSame(['Звіт', 'Інше'], array_map(fn(EntityRecordContract $r): mixed => $r->get('title')['uk'], $desc->items));
    }

    // ------------------------------------------------------------------
    // pagination
    // ------------------------------------------------------------------

    public function test_pagination_metadata_and_page_slice(): void
    {
        $this->seedItems();

        $result = $this->repository->list(
            new TestItemDefinition(),
            new Criteria(sort: [new Sort('name', 'asc')], page: 2, perPage: 3),
        );

        $this->assertSame(['Carol'], $this->names($result));
        $this->assertSame([4, 2, 3], [$result->total, $result->page, $result->perPage]);
    }

    public function test_pagination_clamps_page_and_per_page_to_minimum_one(): void
    {
        $this->seedItems();

        $result = $this->repository->list(new TestItemDefinition(), new Criteria(page: 0, perPage: 0));

        $this->assertSame([4, 1, 1], [$result->total, $result->page, $result->perPage]);
        $this->assertCount(1, $result->items);
    }

    // ------------------------------------------------------------------
    // projection
    // ------------------------------------------------------------------

    public function test_list_projects_only_key_and_declared_columns_for_simple_definition(): void
    {
        $this->seedItems();

        $result = $this->repository->list(new TestItemDefinition(), new Criteria(perPage: 1));

        $this->assertSame(['id', 'name', 'email'], array_keys($result->items[0]->attributes()));
    }

    public function test_list_projects_all_columns_when_definition_has_morph_file_field(): void
    {
        TestAccount::create(['name' => 'Acme']);

        $result = $this->repository->list(new TestAccountDefinition(), new Criteria());

        $this->assertSame(['id', 'name', 'created_at', 'updated_at'], array_keys($result->items[0]->attributes()));
    }

    public function test_list_projection_skips_side_effect_relation_fields_but_keeps_other_columns(): void
    {
        TestItem::create(['name' => 'Alice', 'email' => 'a@e.com']);

        $result = $this->repository->list(new TestItemWithRelationsDefinition(), new Criteria());

        $this->assertSame(['id', 'name', 'email'], array_keys($result->items[0]->attributes()));
    }

    public function test_find_returns_all_columns_unlike_list_projection(): void
    {
        $item = TestItem::create(['name' => 'Alice', 'email' => 'a@e.com']);

        $record = $this->repository->find(new TestItemDefinition(), new EntityKey($item->id));

        $this->assertSame(['id', 'name', 'email', 'category_id', 'created_at', 'updated_at'], array_keys($record->attributes()));
    }

    // ------------------------------------------------------------------
    // eager-load
    // ------------------------------------------------------------------

    public function test_list_eager_loads_belongs_to_relation_as_attribute_array(): void
    {
        $category = TestCategory::create(['title' => 'Cat A']);
        TestItem::create(['name' => 'Alice', 'email' => 'a@e.com', 'category_id' => $category->id]);

        $result = $this->repository->list(new TestItemWithCategoryDefinition(), new Criteria());

        $this->assertSame('Cat A', $result->items[0]->relation('category')['title']);
    }

    public function test_list_eager_loads_every_side_effect_relation_kind(): void
    {
        $item = TestItem::create(['name' => 'Alice', 'email' => 'a@e.com']);
        $tag = TestTag::create(['name' => 'T1']);
        $item->tags()->attach($tag->id, ['approved' => true]);
        TestComment::create(['test_item_id' => $item->id, 'text' => 'c1']);
        TestProfile::create(['test_item_id' => $item->id, 'bio' => 'bio1']);
        $item->morphComments()->create(['text' => 'mc1']);

        $result = $this->repository->list(new TestItemWithRelationsDefinition(), new Criteria());
        $record = $result->items[0];

        $this->assertSame('T1', $record->relation('tags')[0]['name']);
        $this->assertSame(1, $record->relation('tags')[0]['pivot']['approved']);
        $this->assertSame('c1', $record->relation('comments')[0]['text']);
        $this->assertSame('bio1', $record->relation('profile')['bio']);
        $this->assertSame('mc1', $record->relation('morphComments')[0]['text']);
    }

    public function test_list_eager_loads_morph_file_relation_for_morph_file_field(): void
    {
        $account = TestAccount::create(['name' => 'Acme']);
        TestMorphedFile::create([
            'fileable_type' => TestAccount::class,
            'fileable_id' => $account->id,
            'type' => 'avatar',
            'name' => 'a.png',
            'path' => 'avatars/a.png',
        ]);

        $result = $this->repository->list(new TestAccountDefinition(), new Criteria());

        $this->assertSame('avatars/a.png', $result->items[0]->relation('files')[0]['path']);
    }

    // ------------------------------------------------------------------
    // embedded
    // ------------------------------------------------------------------

    public function test_list_embedded_has_many_serialises_children_with_nested_relations(): void
    {
        [$publicationId, $tagIds] = $this->seedPublicationWithItemAndTags();

        $result = $this->repository->list(new TestPublicationDefinition(), new Criteria());
        $items = $result->items[0]->relation('items');

        $this->assertSame($publicationId, $result->items[0]->id());
        $this->assertCount(1, $items);
        $this->assertSame('Item One', $items[0]['title']);
        $this->assertEqualsCanonicalizing($tagIds, array_column($items[0]['tags'], 'id'));
    }

    public function test_find_embedded_has_many_with_no_children_returns_empty_list(): void
    {
        $id = $this->seedPublicationWithNoItems();

        $record = $this->repository->find(new TestPublicationDefinition(), new EntityKey($id));

        $this->assertSame([], $record->relation('items'));
    }

    // ------------------------------------------------------------------
    // find / exists
    // ------------------------------------------------------------------

    public function test_find_returns_record_for_existing_key(): void
    {
        $item = TestItem::create(['name' => 'Alice', 'email' => 'a@e.com']);

        $record = $this->repository->find(new TestItemDefinition(), new EntityKey($item->id));

        $this->assertSame('Alice', $record->get('name'));
        $this->assertSame($item->id, $record->id());
    }

    public function test_find_returns_null_for_missing_key(): void
    {
        $this->assertNull($this->repository->find(new TestItemDefinition(), new EntityKey(999)));
    }

    public function test_find_casts_numeric_string_key_when_type_is_int(): void
    {
        $item = TestItem::create(['name' => 'Alice', 'email' => 'a@e.com']);

        $record = $this->repository->find(new TestItemDefinition(), new EntityKey((string) $item->id, 'int'));

        $this->assertSame('Alice', $record->get('name'));
    }

    public function test_find_eager_loads_relations_of_definition(): void
    {
        $item = TestItem::create(['name' => 'Alice', 'email' => 'a@e.com']);
        TestComment::create(['test_item_id' => $item->id, 'text' => 'c1']);

        $record = $this->repository->find(new TestItemWithRelationsDefinition(), new EntityKey($item->id));

        $this->assertSame('c1', $record->relation('comments')[0]['text']);
        $this->assertNull($record->relation('profile'));
    }

    public function test_exists_true_for_existing_and_false_for_missing(): void
    {
        $item = TestItem::create(['name' => 'Alice', 'email' => 'a@e.com']);

        $this->assertTrue($this->repository->exists(new TestItemDefinition(), new EntityKey($item->id)));
        $this->assertFalse($this->repository->exists(new TestItemDefinition(), new EntityKey(999)));
    }
}
