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
}
