<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Query;

use BlackParadise\CoreAdmin\Domain\Contracts\LocaleProviderContract;
use BlackParadise\LaravelAdmin\Tests\Integration\Query\Concerns\BuildsSearchableArticles;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Task 2.3 — A1–A8 (+A10 SQLite half): translatable search on SQLite in-memory.
 *
 * Scenario row set (ids returned by insertArticle):
 *   $whitney = {"en":"Whitney Report","uk":"Звіт"}
 *   $other   = {"en":"Other","uk":"Інше"}
 */
final class TranslatableSearchTest extends TestCase
{
    use RefreshDatabase;
    use BuildsSearchableArticles;

    private int $whitney;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stubQueryHasher();
        config()->set('bpadmin.locales', ['en', 'uk']);
        $this->createArticlesTable();
        $this->whitney = $this->insertArticle(['en' => 'Whitney Report', 'uk' => 'Звіт']);
        $this->insertArticle(['en' => 'Other', 'uk' => 'Інше']);
    }

    // A1
    public function test_a1_search_is_case_insensitive_for_lowercase_term(): void
    {
        self::assertSame([$this->whitney], $this->searchIds($this->titleOnlyDefinition(), 'whitney'));
    }

    public function test_a1_search_is_case_insensitive_for_uppercase_term(): void
    {
        self::assertSame([$this->whitney], $this->searchIds($this->titleOnlyDefinition(), 'WHITNEY'));
    }

    // A2
    public function test_a2_search_matches_cyrillic_value_case_insensitively(): void
    {
        self::assertSame([$this->whitney], $this->searchIds($this->titleOnlyDefinition(), 'звіт'));
    }

    // A3
    public function test_a3_locale_key_uk_matches_no_rows(): void
    {
        self::assertSame([], $this->searchIds($this->titleOnlyDefinition(), 'uk'));
    }

    public function test_a3_locale_key_en_matches_no_rows(): void
    {
        self::assertSame([], $this->searchIds($this->titleOnlyDefinition(), 'en'));
    }

    public function test_a3_json_colon_matches_no_rows(): void
    {
        self::assertSame([], $this->searchIds($this->titleOnlyDefinition(), ':'));
    }

    public function test_a3_json_quote_matches_no_rows(): void
    {
        self::assertSame([], $this->searchIds($this->titleOnlyDefinition(), '"'));
    }

    public function test_a3_term_that_occurs_in_a_value_still_matches_only_that_row(): void
    {
        $green = $this->insertArticle(['en' => 'Green', 'uk' => 'Зелений']);

        self::assertSame([$green], $this->searchIds($this->titleOnlyDefinition(), 'en'));
    }

    // A4 (translatable)
    public function test_a4_percent_is_literal(): void
    {
        $literal = $this->insertArticle(['en' => '100%_real', 'uk' => 'x']);
        $this->insertArticle(['en' => '1000xreal', 'uk' => 'y']);

        self::assertSame([$literal], $this->searchIds($this->titleOnlyDefinition(), '%'));
    }

    public function test_a4_underscore_is_literal(): void
    {
        $literal = $this->insertArticle(['en' => '100%_real', 'uk' => 'x']);
        $this->insertArticle(['en' => '1000xreal', 'uk' => 'y']);

        self::assertSame([$literal], $this->searchIds($this->titleOnlyDefinition(), '_'));
    }

    public function test_a4_percent_is_not_a_wildcard_inside_a_term(): void
    {
        $this->insertArticle(['en' => '100%_real', 'uk' => 'x']);

        self::assertSame([], $this->searchIds($this->titleOnlyDefinition(), 'Whit%ey'));
    }

    public function test_a4_backslash_is_literal(): void
    {
        $literal = $this->insertArticle(['en' => 'back\\slash', 'uk' => 'x']);

        self::assertSame([$literal], $this->searchIds($this->titleOnlyDefinition(), '\\'));
    }

    // A5
    public function test_a5_row_with_only_non_primary_locale_value_is_found(): void
    {
        $ukOnly = $this->insertArticle(['en' => '', 'uk' => 'Унікальний']);

        self::assertSame([$ukOnly], $this->searchIds($this->titleOnlyDefinition(), 'унікальний'));
    }

    // A6
    public function test_a6_union_of_translatable_and_plain_field_matches(): void
    {
        $byTitle = $this->insertArticle(['en' => 'Alpha', 'uk' => 'Альфа'], 'zzz');
        $bySlug = $this->insertArticle(['en' => 'Beta', 'uk' => 'Бета'], 'alpha-slug');
        $this->insertArticle(['en' => 'Gamma', 'uk' => 'Гама'], 'nothing');

        self::assertSame([$byTitle, $bySlug], $this->searchIds($this->titleAndSlugDefinition(), 'alpha'));
    }

    // A7
    public function test_a7_zero_locales_returns_no_rows_and_no_error(): void
    {
        app()->instance(LocaleProviderContract::class, new class implements LocaleProviderContract {
            public function availableLocales(): array
            {
                return [];
            }

            public function defaultLocale(): string
            {
                return 'en';
            }

            public function currentLocale(): string
            {
                return 'en';
            }
        });

        self::assertSame([], $this->searchIds($this->titleOnlyDefinition(), 'whitney'));
    }

    // A8
    public function test_a8_legacy_non_json_row_does_not_raise_and_is_matched_by_like(): void
    {
        $legacy = $this->insertArticle('Legacy Plain Text');

        self::assertSame([$legacy], $this->searchIds($this->titleOnlyDefinition(), 'plain'));
    }

    public function test_a8_legacy_non_json_row_does_not_break_search_for_json_rows(): void
    {
        $this->insertArticle('Legacy Plain Text');

        self::assertSame([$this->whitney], $this->searchIds($this->titleOnlyDefinition(), 'whitney'));
    }

    public function test_a8_legacy_non_json_row_is_not_returned_for_unrelated_term(): void
    {
        $this->insertArticle('Legacy Plain Text');

        self::assertSame([], $this->searchIds($this->titleOnlyDefinition(), 'zzzz'));
    }
}
