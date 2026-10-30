<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ModelLess;

/** Process-wide in-memory row store backing the model-less fixture repository / mutator. */
final class ArrayStore
{
    /** @var array<int, array<string, mixed>> */
    public static array $rows = [];

    /** @param array<int, array<string, mixed>> $rows */
    public static function reset(array $rows = []): void
    {
        self::$rows = $rows;
    }

    public static function nextId(): int
    {
        return self::$rows === [] ? 1 : max(array_keys(self::$rows)) + 1;
    }
}
