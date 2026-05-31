<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Infrastructure\Locale;

use BlackParadise\CoreAdmin\Domain\Contracts\LocaleProviderContract;

final class ConfigLocaleProvider implements LocaleProviderContract
{
    public function availableLocales(): array
    {
        return config('bpadmin.languages', [config('app.locale', 'en')]);
    }

    public function defaultLocale(): string
    {
        return config('app.locale', 'en');
    }
}
