<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Unit\Infrastructure\Persistence\Query;

use BlackParadise\LaravelAdmin\Infrastructure\Persistence\Query\TranslatableJsonPath;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TranslatableJsonPathTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function drivers(): array
    {
        return [
            'mysql' => ['mysql', '`title`'],
            'mariadb' => ['mariadb', '`title`'],
            'pgsql' => ['pgsql', '"title"'],
            'sqlite' => ['sqlite', '"title"'],
        ];
    }

    #[DataProvider('drivers')]
    public function test_value_expression_has_single_placeholder_and_no_locale_literal(string $driver, string $column): void
    {
        $sql = TranslatableJsonPath::valueExpression($driver, $column);

        self::assertSame(1, substr_count($sql, '?'));
        self::assertStringNotContainsString('uk_UA', $sql);
        self::assertStringNotContainsString('$."', $sql);
    }

    #[DataProvider('drivers')]
    public function test_value_expression_wraps_the_given_column(string $driver, string $column): void
    {
        $sql = TranslatableJsonPath::valueExpression($driver, $column);

        self::assertStringContainsString($column, $sql);
    }

    #[DataProvider('drivers')]
    public function test_value_expression_falls_back_to_raw_column_for_non_json(string $driver, string $column): void
    {
        $sql = TranslatableJsonPath::valueExpression($driver, $column);

        self::assertStringContainsString('ELSE ' . $column, $sql);
    }

    #[DataProvider('drivers')]
    public function test_value_expression_guards_with_case_when(string $driver, string $column): void
    {
        $sql = TranslatableJsonPath::valueExpression($driver, $column);

        self::assertStringStartsWith('(CASE WHEN ', $sql);
    }

    public function test_mysql_guard_uses_json_valid(): void
    {
        $sql = TranslatableJsonPath::valueExpression('mysql', '`title`');

        self::assertStringContainsString('JSON_VALID(`title`)', $sql);
        self::assertStringContainsString('JSON_UNQUOTE(JSON_EXTRACT(`title`, ?))', $sql);
    }

    public function test_mariadb_guard_matches_mysql(): void
    {
        self::assertSame(
            TranslatableJsonPath::valueExpression('mysql', '`title`'),
            TranslatableJsonPath::valueExpression('mariadb', '`title`'),
        );
    }

    public function test_sqlite_guard_uses_json_valid_and_json_extract(): void
    {
        $sql = TranslatableJsonPath::valueExpression('sqlite', '"title"');

        self::assertStringContainsString('json_valid("title")', $sql);
        self::assertStringContainsString('json_extract("title", ?)', $sql);
    }

    public function test_pgsql_guard_uses_pg_input_is_valid_jsonb(): void
    {
        $sql = TranslatableJsonPath::valueExpression('pgsql', '"title"');

        self::assertStringContainsString("pg_input_is_valid(\"title\"::text, 'jsonb')", $sql);
    }

    public function test_pgsql_extracts_jsonb_text_only_inside_the_guarded_branch(): void
    {
        $sql = TranslatableJsonPath::valueExpression('pgsql', '"title"');

        self::assertSame(
            "(CASE WHEN pg_input_is_valid(\"title\"::text, 'jsonb') THEN (\"title\"::jsonb ->> ?) ELSE \"title\"::text END)",
            $sql,
        );
    }

    #[DataProvider('allDrivers')]
    public function test_binding_carries_the_locale(string $driver): void
    {
        $binding = TranslatableJsonPath::binding($driver, 'uk_UA');

        self::assertStringContainsString('uk_UA', $binding);
    }

    #[DataProvider('mysqlLikeDrivers')]
    public function test_binding_for_json_path_drivers_is_quoted_json_path(string $driver): void
    {
        self::assertSame('$."uk_UA"', TranslatableJsonPath::binding($driver, 'uk_UA'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function allDrivers(): array
    {
        return ['mysql' => ['mysql'], 'mariadb' => ['mariadb'], 'pgsql' => ['pgsql'], 'sqlite' => ['sqlite']];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function mysqlLikeDrivers(): array
    {
        return ['mysql' => ['mysql'], 'mariadb' => ['mariadb'], 'sqlite' => ['sqlite']];
    }

    public function test_pgsql_binding_is_the_bare_locale_key(): void
    {
        self::assertSame('uk_UA', TranslatableJsonPath::binding('pgsql', 'uk_UA'));
    }
}
