<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Infrastructure\Persistence\Query;

/**
 * Builds LIKE patterns that treat user input literally. Pair every pattern with
 * `ESCAPE '!'` ({@see self::CLAUSE}).
 */
final class LikeEscape
{
    public const CLAUSE = "ESCAPE '!'";

    private const ESCAPE_CHAR = '!';

    /** Substring pattern (`%term%`). */
    public static function contains(string $term): string
    {
        return '%' . self::escape($term) . '%';
    }

    /** The term with LIKE metacharacters neutralised (no surrounding wildcards). */
    public static function escape(string $term): string
    {
        return str_replace(
            [self::ESCAPE_CHAR, '%', '_'],
            [self::ESCAPE_CHAR . self::ESCAPE_CHAR, self::ESCAPE_CHAR . '%', self::ESCAPE_CHAR . '_'],
            $term,
        );
    }
}
