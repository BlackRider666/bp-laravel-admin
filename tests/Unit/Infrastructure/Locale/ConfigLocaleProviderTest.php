<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Unit\Infrastructure\Locale;

use BlackParadise\LaravelAdmin\Infrastructure\Locale\ConfigLocaleProvider;
use BlackParadise\LaravelAdmin\Tests\TestCase;

/**
 * Unit tests for ConfigLocaleProvider (Bug #10 fix).
 *
 * Verifies canonical config key is 'bpadmin.locales' (not the old 'bpadmin.languages')
 * and that null / empty-array values correctly fall back to app.locale.
 */
final class ConfigLocaleProviderTest extends TestCase
{
    private ConfigLocaleProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new ConfigLocaleProvider();
    }

    public function test_returns_locales_from_bpadmin_locales_config(): void
    {
        config()->set('bpadmin.locales', ['en', 'uk', 'de']);

        $locales = $this->provider->availableLocales();

        self::assertSame(['en', 'uk', 'de'], $locales);
    }

    public function test_falls_back_to_app_locale_when_locales_is_null(): void
    {
        config()->set('bpadmin.locales');
        config()->set('app.locale', 'uk');

        $locales = $this->provider->availableLocales();

        self::assertSame(['uk'], $locales);
    }

    public function test_falls_back_to_app_locale_when_locales_is_empty_array(): void
    {
        config()->set('bpadmin.locales', []);
        config()->set('app.locale', 'fr');

        $locales = $this->provider->availableLocales();

        self::assertSame(['fr'], $locales);
    }

    public function test_does_not_read_old_bpadmin_languages_key(): void
    {
        // Simulate old config where only 'languages' key was set (wrong key).
        config()->set('bpadmin.locales');
        config()->set('bpadmin.languages', ['fr', 'de']);
        config()->set('app.locale', 'en');

        $locales = $this->provider->availableLocales();

        // Must NOT return ['fr', 'de'] — those are the old-key values.
        self::assertSame(['en'], $locales);
    }

    public function test_default_locale_reads_app_locale(): void
    {
        config()->set('app.locale', 'uk');

        self::assertSame('uk', $this->provider->defaultLocale());
    }

    public function test_falls_back_to_en_when_app_locale_missing(): void
    {
        config()->set('bpadmin.locales');
        config()->set('app.locale');

        $locales = $this->provider->availableLocales();

        self::assertSame(['en'], $locales);
    }

    public function test_current_locale_reflects_runtime(): void
    {
        app()->setLocale('de');
        self::assertSame('de', (new ConfigLocaleProvider())->currentLocale());
    }

    public function test_current_locale_falls_back_to_default_when_runtime_empty(): void
    {
        config(['app.locale' => 'en']);
        app()->setLocale('');
        self::assertSame('en', (new ConfigLocaleProvider())->currentLocale());
    }
}
