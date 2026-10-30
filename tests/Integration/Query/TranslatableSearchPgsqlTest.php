<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Query;

use BlackParadise\LaravelAdmin\Tests\Integration\Query\Concerns\BuildsSearchableArticles;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;

/**
 * A10 (PostgreSQL half): A1–A8 on PostgreSQL 16+ (pg_input_is_valid guards legacy non-JSON rows).
 * Opt-in: group `pgsql`, skipped unless DB_CONNECTION=pgsql.
 */
#[Group('pgsql')]
final class TranslatableSearchPgsqlTest extends TestCase
{
    use BuildsSearchableArticles;

    private int $whitney;

    /**
     * {@inheritDoc}
     *
     * The base TestCase pins `testing` to in-memory SQLite; point it at the real PostgreSQL (16+) from env.
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        if (getenv('DB_CONNECTION') !== 'pgsql') {
            return;
        }

        $app['config']->set('database.connections.testing', [
            'driver'    => 'pgsql',
            'host'      => $this->env('DB_HOST', '127.0.0.1'),
            'port'      => $this->env('DB_PORT', '5432'),
            'database'  => $this->env('DB_DATABASE', 'testing'),
            'username'  => $this->env('DB_USERNAME', 'postgres'),
            'password'  => $this->env('DB_PASSWORD', ''),
            'charset'   => 'utf8',
            'prefix'    => '',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (getenv('DB_CONNECTION') !== 'pgsql') {
            self::markTestSkipped('PostgreSQL group: run with DB_CONNECTION=pgsql.');
        }

        $this->stubQueryHasher();
        config()->set('bpadmin.locales', ['en', 'uk']);
        Schema::dropIfExists('test_articles');
        Schema::create('test_articles', function (Blueprint $table): void {
            $table->id();
            $table->text('title')->nullable();
            $table->string('slug')->nullable();
            $table->timestamps();
        });
        $this->whitney = $this->insertArticle(['en' => 'Whitney Report', 'uk' => 'Звіт']);
        $this->insertArticle(['en' => 'Other', 'uk' => 'Інше']);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('test_articles');
        parent::tearDown();
    }

    private function env(string $key, string $default): string
    {
        $value = getenv($key);

        return is_string($value) && $value !== '' ? $value : $default;
    }

    public function test_a1_lowercase_term_matches(): void
    {
        self::assertSame([$this->whitney], $this->searchIds($this->titleOnlyDefinition(), 'whitney'));
    }

    public function test_a1_uppercase_term_matches(): void
    {
        self::assertSame([$this->whitney], $this->searchIds($this->titleOnlyDefinition(), 'WHITNEY'));
    }

    public function test_a2_cyrillic_term_matches_case_insensitively(): void
    {
        self::assertSame([$this->whitney], $this->searchIds($this->titleOnlyDefinition(), 'звіт'));
    }

    public function test_a3_locale_key_and_json_punctuation_match_no_rows(): void
    {
        $definition = $this->titleOnlyDefinition();

        self::assertSame([[], [], [], []], [
            $this->searchIds($definition, 'uk'),
            $this->searchIds($definition, 'en'),
            $this->searchIds($definition, ':'),
            $this->searchIds($definition, '"'),
        ]);
    }

    public function test_a4_percent_underscore_and_backslash_are_literal(): void
    {
        $pct = $this->insertArticle(['en' => '100%_real', 'uk' => 'x']);
        $bs = $this->insertArticle(['en' => 'back\\slash', 'uk' => 'y']);
        $definition = $this->titleOnlyDefinition();

        self::assertSame([[$pct], [$pct], [$bs]], [
            $this->searchIds($definition, '%'),
            $this->searchIds($definition, '_'),
            $this->searchIds($definition, '\\'),
        ]);
    }

    public function test_a5_row_with_only_non_primary_locale_value_is_found(): void
    {
        $ukOnly = $this->insertArticle(['en' => '', 'uk' => 'Унікальний']);

        self::assertSame([$ukOnly], $this->searchIds($this->titleOnlyDefinition(), 'унікальний'));
    }

    public function test_a6_union_of_translatable_and_plain_field_matches(): void
    {
        $byTitle = $this->insertArticle(['en' => 'Alpha', 'uk' => 'Альфа'], 'zzz');
        $bySlug = $this->insertArticle(['en' => 'Beta', 'uk' => 'Бета'], 'alpha-slug');

        self::assertSame([$byTitle, $bySlug], $this->searchIds($this->titleAndSlugDefinition(), 'alpha'));
    }

    public function test_a8_legacy_non_json_row_does_not_raise_invalid_json_error(): void
    {
        $legacy = $this->insertArticle('Legacy Plain Text');

        self::assertSame([$legacy], $this->searchIds($this->titleOnlyDefinition(), 'plain'));
    }
}
