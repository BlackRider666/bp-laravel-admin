<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Query;

use BlackParadise\CoreAdmin\Domain\Contracts\Entity\EntityRecordContract;
use BlackParadise\CoreAdmin\Domain\Contracts\LocaleProviderContract;
use BlackParadise\CoreAdmin\Domain\Query\Criteria;
use BlackParadise\CoreAdmin\Domain\Query\Sort;
use BlackParadise\LaravelAdmin\Tests\Integration\Query\Concerns\BuildsSearchableArticles;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Task 2.3 — A9: translatable sort orders by the current-locale value and the JSON path is a
 * binding, never interpolated into the SQL text.
 */
final class TranslatableSortBindingTest extends TestCase
{
    use RefreshDatabase;
    use BuildsSearchableArticles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stubQueryHasher();
        config()->set('bpadmin.locales', ['uk', 'en']);
        config()->set('app.locale', 'uk');
        $this->createArticlesTable();
        $this->insertArticle(['en' => 'Whitney Report', 'uk' => 'Звіт']);
        $this->insertArticle(['en' => 'Other', 'uk' => 'Інше']);
    }

    /** @return list<array{query: string, bindings: array<int, mixed>}> */
    private function sortQueries(string $direction): array
    {
        $repository = $this->repository();
        DB::flushQueryLog();
        DB::enableQueryLog();

        $repository->list($this->titleOnlyDefinition(), new Criteria(sort: [new Sort('title', $direction)]));

        $queries = array_values(array_filter(
            DB::getQueryLog(),
            static fn(array $q): bool => Str::contains($q['query'], 'order by', true),
        ));
        DB::disableQueryLog();

        return $queries;
    }

    public function test_a9_sql_text_contains_no_locale_literal(): void
    {
        $queries = $this->sortQueries('asc');

        self::assertNotEmpty($queries);
        foreach ($queries as $q) {
            foreach (["'uk'", '"uk"', '.uk', "'en'", '"en"', '.en'] as $literal) {
                self::assertStringNotContainsString($literal, $q['query']);
            }
        }
    }

    public function test_a9_locale_json_path_is_passed_as_binding(): void
    {
        $queries = $this->sortQueries('asc');

        $withLocaleBinding = array_filter(
            $queries,
            static fn(array $q): bool => array_filter(
                $q['bindings'],
                static fn(mixed $b): bool => is_string($b) && str_contains($b, 'uk'),
            ) !== [],
        );

        self::assertNotEmpty($withLocaleBinding);
    }

    public function test_a9_asc_orders_by_current_locale_value(): void
    {
        $result = $this->repository()->list($this->titleOnlyDefinition(), new Criteria(sort: [new Sort('title', 'asc')]));

        // Binary (code point) order on SQLite: 'І' (U+0406) < 'З' (U+0417).
        self::assertSame(['Інше', 'Звіт'], array_map(fn(EntityRecordContract $r): mixed => $r->get('title')['uk'], $result->items));
    }

    public function test_a9_desc_orders_by_current_locale_value(): void
    {
        $result = $this->repository()->list($this->titleOnlyDefinition(), new Criteria(sort: [new Sort('title', 'desc')]));

        self::assertSame(['Звіт', 'Інше'], array_map(fn(EntityRecordContract $r): mixed => $r->get('title')['uk'], $result->items));
    }

    public function test_a9_sort_locale_is_default_locale_not_runtime_current_locale(): void
    {
        $this->insertArticle(['en' => 'b', 'uk' => 'y']);
        $this->insertArticle(['en' => 'a', 'uk' => 'z']);
        app()->instance(LocaleProviderContract::class, new class implements LocaleProviderContract {
            public function availableLocales(): array
            {
                return ['uk', 'en'];
            }

            public function defaultLocale(): string
            {
                return 'uk';
            }

            public function currentLocale(): string
            {
                return 'en';
            }
        });

        $result = $this->repository()->list($this->titleOnlyDefinition(), new Criteria(sort: [new Sort('title', 'asc')]));

        self::assertSame(['y', 'z', 'Інше', 'Звіт'], array_map(fn(EntityRecordContract $r): mixed => $r->get('title')['uk'], $result->items));
    }
}
