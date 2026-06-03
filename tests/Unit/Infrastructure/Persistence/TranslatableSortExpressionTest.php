<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Unit\Infrastructure\Persistence;

use BlackParadise\CoreAdmin\Domain\Contracts\LocaleProviderContract;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\EloquentEntityRepository;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Unit tests for EloquentEntityRepository::translatableSortExpression().
 *
 * The method is private and side-effect-free, which makes it ideal for
 * white-box testing via Reflection. This test suite pins the exact SQL
 * expression emitted for each database driver so any regression
 * (such as the pgsql $.locale bug) is caught without a live database.
 *
 * CI runs on SQLite; PostgreSQL is never available in CI, so this is the
 * only reliable way to cover the pgsql branch.
 */
final class TranslatableSortExpressionTest extends TestCase
{
    private ReflectionMethod $method;
    private EloquentEntityRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $localeProvider = new class implements LocaleProviderContract {
            public function availableLocales(): array
            {
                return ['en'];
            }

            public function defaultLocale(): string
            {
                return 'en';
            }
        };

        $this->repository = new EloquentEntityRepository($localeProvider);

        $this->method = new ReflectionMethod(
            EloquentEntityRepository::class,
            'translatableSortExpression',
        );
    }

    private function invoke(string $driver, string $column, string $locale, string $direction): string
    {
        return (string) $this->method->invoke($this->repository, $driver, $column, $locale, $direction);
    }

    // -------------------------------------------------------------------------
    // MySQL
    // -------------------------------------------------------------------------

    public function test_mysql_expression_uses_json_unquote_and_json_extract(): void
    {
        $expr = $this->invoke('mysql', 'title', 'en', 'ASC');

        self::assertSame(
            'JSON_UNQUOTE(JSON_EXTRACT(`title`, \'$."en"\')) ASC',
            $expr,
        );
    }

    public function test_mysql_expression_with_desc_direction(): void
    {
        $expr = $this->invoke('mysql', 'name', 'uk', 'DESC');

        self::assertSame(
            'JSON_UNQUOTE(JSON_EXTRACT(`name`, \'$."uk"\')) DESC',
            $expr,
        );
    }

    // -------------------------------------------------------------------------
    // SQLite (default branch)
    // -------------------------------------------------------------------------

    public function test_sqlite_expression_uses_json_extract_without_json_unquote(): void
    {
        $expr = $this->invoke('sqlite', 'title', 'en', 'ASC');

        self::assertSame(
            'json_extract("title", \'$."en"\') ASC',
            $expr,
        );
    }

    public function test_unknown_driver_falls_back_to_sqlite_expression(): void
    {
        $expr = $this->invoke('unknown', 'title', 'en', 'ASC');

        self::assertSame(
            'json_extract("title", \'$."en"\') ASC',
            $expr,
        );
    }

    // -------------------------------------------------------------------------
    // PostgreSQL — critical: must NOT contain the $. path prefix
    // -------------------------------------------------------------------------

    public function test_pgsql_expression_uses_arrow_operator_with_plain_locale_key(): void
    {
        $expr = $this->invoke('pgsql', 'title', 'en', 'ASC');

        self::assertSame(
            '"title"->>\'en\' ASC',
            $expr,
            'PostgreSQL ->> expects a plain key name, not a JSON-path $. prefix.',
        );
    }

    public function test_pgsql_expression_does_not_contain_dollar_dot_prefix(): void
    {
        $expr = $this->invoke('pgsql', 'title', 'en', 'ASC');

        self::assertStringNotContainsString(
            '$.',
            $expr,
            'The $. prefix is MySQL/SQLite JSON-path syntax and must not appear in the PostgreSQL expression. '
            . 'Using it would cause ->> to search for a literal key named "$.en", returning NULL for every row.',
        );
    }

    public function test_pgsql_expression_contains_locale_key_directly(): void
    {
        $expr = $this->invoke('pgsql', 'title', 'uk', 'DESC');

        self::assertSame(
            '"title"->>\'uk\' DESC',
            $expr,
        );
    }

    public function test_pgsql_expression_quotes_column_name(): void
    {
        $expr = $this->invoke('pgsql', 'my_column', 'en', 'ASC');

        self::assertStringStartsWith('"my_column"', $expr);
    }
}
