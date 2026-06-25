<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Unit\Support;

use BlackParadise\LaravelAdmin\Support\AvailableLocalesResolver;
use BlackParadise\LaravelAdmin\Tests\TestCase;

final class AvailableLocalesResolverTest extends TestCase
{
    public function test_uses_config_list_when_set(): void
    {
        $resolver = new AvailableLocalesResolver(['en', 'uk'], '/nonexistent');
        self::assertSame(['en', 'uk'], $resolver->list());
    }

    public function test_scans_directory_when_config_is_null(): void
    {
        $dir = sys_get_temp_dir() . '/bpadmin_resolver_test_' . uniqid();
        @mkdir($dir . '/uk', 0777, true);
        @mkdir($dir . '/de', 0777, true);

        try {
            $resolver = new AvailableLocalesResolver(null, $dir);
            $result = $resolver->list();
            sort($result);
            self::assertSame(['de', 'uk'], $result);
        } finally {
            @rmdir($dir . '/uk');
            @rmdir($dir . '/de');
            @rmdir($dir);
        }
    }

    public function test_falls_back_to_en_when_no_config_and_directory_missing(): void
    {
        $resolver = new AvailableLocalesResolver(null, '/nonexistent-' . uniqid());
        self::assertSame(['en'], $resolver->list());
    }

    public function test_ignores_empty_config_list_and_falls_back(): void
    {
        $resolver = new AvailableLocalesResolver([], '/nonexistent-' . uniqid());
        self::assertSame(['en'], $resolver->list());
    }

    public function test_scans_filesystem_only_once(): void
    {
        $dir = sys_get_temp_dir() . '/bpadmin_resolver_memo_' . uniqid();
        @mkdir($dir . '/fr', 0777, true);

        try {
            $resolver = new AvailableLocalesResolver(null, $dir);

            $first = $resolver->list();
            self::assertSame(['fr'], $first);

            // Add a second locale directory AFTER the first call.
            @mkdir($dir . '/es', 0777, true);

            // Second call must return the cached result — NOT ['es', 'fr'].
            $second = $resolver->list();
            self::assertSame($first, $second, 'list() must return cached result; filesystem must not be re-scanned');
        } finally {
            @rmdir($dir . '/fr');
            @rmdir($dir . '/es');
            @rmdir($dir);
        }
    }
}
