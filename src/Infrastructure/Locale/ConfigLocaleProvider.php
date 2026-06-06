<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Infrastructure\Locale;

use BlackParadise\CoreAdmin\Domain\Contracts\LocaleProviderContract;

final class ConfigLocaleProvider implements LocaleProviderContract
{
    public function availableLocales(): array
    {
        $locales = config('bpadmin.locales');

        if (!is_array($locales) || $locales === []) {
            $appLocale = config('app.locale');
            return [is_string($appLocale) && $appLocale !== '' ? $appLocale : 'en'];
        }

        return $locales;
    }

    public function defaultLocale(): string
    {
        $locale = config('app.locale');
        return is_string($locale) && $locale !== '' ? $locale : 'en';
    }

    public function currentLocale(): string
    {
        $locale = app()->getLocale();

        return $locale !== '' ? $locale : $this->defaultLocale();
    }
}
