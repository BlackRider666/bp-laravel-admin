<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Infrastructure\Persistence\Query;

use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use PDO;
use WeakMap;

/**
 * Driver-specific SQL for reading one locale's value out of a JSON-encoded translatable column.
 *
 * The locale/path is always returned as a binding (never interpolated). Legacy rows whose column
 * holds non-JSON text fall back to the raw column value instead of raising a JSON error.
 */
final class TranslatableJsonPath
{
    /** @var WeakMap<PDO, true>|null */
    private static ?WeakMap $registered = null;

    /**
     * @param Builder<Model> $query
     */
    public static function driver(Builder $query): string
    {
        $connection = $query->getConnection();

        return method_exists($connection, 'getDriverName') ? (string) $connection->getDriverName() : 'unknown';
    }

    /**
     * Binding for the JSON-path placeholder of {@see self::valueExpression()}.
     */
    public static function binding(string $driver, string $locale): string
    {
        return $driver === 'pgsql' ? $locale : '$."' . $locale . '"';
    }

    /**
     * SQL (with one `?` for the path binding) yielding the locale's text value, or the raw column
     * for non-JSON legacy rows.
     *
     * PostgreSQL 16+ is REQUIRED for translatable queries on pgsql: `pg_input_is_valid` does not
     * exist before 16, so on older servers every row/query fails.
     */
    public static function valueExpression(string $driver, string $wrappedColumn): string
    {
        return match ($driver) {
            'mysql', 'mariadb' => "(CASE WHEN JSON_VALID({$wrappedColumn}) THEN JSON_UNQUOTE(JSON_EXTRACT({$wrappedColumn}, ?)) ELSE {$wrappedColumn} END)",
            'pgsql' => "(CASE WHEN pg_input_is_valid({$wrappedColumn}::text, 'jsonb') THEN ({$wrappedColumn}::jsonb ->> ?) ELSE {$wrappedColumn}::text END)",
            default => "(CASE WHEN json_valid({$wrappedColumn}) THEN json_extract({$wrappedColumn}, ?) ELSE {$wrappedColumn} END)",
        };
    }

    /**
     * SQL expression lower-casing $expression, Unicode-aware on every driver.
     *
     * SQLite's LOWER()/LIKE only fold ASCII, so a PHP UDF (mb_strtolower) is registered on the
     * connection. MySQL/PostgreSQL LOWER() is Unicode-aware with utf8mb4 / UTF-8 collations.
     *
     * @param Builder<Model> $query
     */
    public static function lowered(Builder $query, string $driver, string $expression): string
    {
        if ($driver === 'sqlite') {
            self::registerSqliteLower($query->getConnection());

            return "bp_lower({$expression})";
        }

        return "LOWER({$expression})";
    }

    private static function registerSqliteLower(mixed $connection): void
    {
        if (!$connection instanceof Connection) {
            return;
        }

        self::$registered ??= new WeakMap();

        foreach ([$connection->getPdo(), $connection->getReadPdo()] as $pdo) {
            if (!$pdo instanceof PDO) {
                continue;
            }
            if (isset(self::$registered[$pdo])) {
                continue;
            }
            $lower = static fn(mixed $v): ?string => $v === null ? null : mb_strtolower((string) $v);
            if (method_exists($pdo, 'sqliteCreateFunction')) {
                $pdo->sqliteCreateFunction('bp_lower', $lower, 1);
            } elseif (method_exists($pdo, 'createFunction')) {
                $pdo->createFunction('bp_lower', $lower, 1);
            }
            self::$registered[$pdo] = true;
        }
    }
}
