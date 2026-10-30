<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Query;

use BlackParadise\CoreAdmin\Domain\Contracts\LocaleProviderContract;
use BlackParadise\LaravelAdmin\Tests\TestCase;

/**
 * Task 2.4 ruling: the container-resolved locale provider must not invent bundled package
 * locales when `bpadmin.locales` is unset; it falls back to the app locale only.
 */
final class ConfigLocaleProviderFallbackTest extends TestCase
{
    public function test_available_locales_with_null_config_returns_only_app_locale(): void
    {
        config()->set('bpadmin.locales');
        config()->set('app.locale', 'de');

        $provider = app()->make(LocaleProviderContract::class);

        self::assertSame(['de'], $provider->availableLocales());
    }

    public function test_available_locales_with_empty_config_returns_only_app_locale(): void
    {
        config()->set('bpadmin.locales', []);
        config()->set('app.locale', 'de');

        $provider = app()->make(LocaleProviderContract::class);

        self::assertSame(['de'], $provider->availableLocales());
    }

    public function test_available_locales_with_configured_list_returns_it(): void
    {
        config()->set('bpadmin.locales', ['uk', 'en']);

        $provider = app()->make(LocaleProviderContract::class);

        self::assertSame(['uk', 'en'], $provider->availableLocales());
    }
}
