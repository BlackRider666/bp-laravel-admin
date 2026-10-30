<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Query;

use BlackParadise\CoreAdmin\Domain\Contracts\LocaleProviderContract;
use BlackParadise\CoreAdmin\Domain\Fields\TranslatableField;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\Query\TranslatableQueryStrategy;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/** The sort locale closure (lazy provider factory) is resolved once per applySort call. */
final class TranslatableSortLocaleResolveOnceTest extends TestCase
{
    public function test_sort_resolves_locale_provider_once(): void
    {
        $calls    = 0;
        $provider = new class implements LocaleProviderContract {
            public function availableLocales(): array
            {
                return ['uk', 'en'];
            }

            public function defaultLocale(): string
            {
                return 'uk';
            }

            public function currentLocale(): string
            {
                return 'uk';
            }
        };

        $strategy = new TranslatableQueryStrategy(static function () use (&$calls, $provider): LocaleProviderContract {
            $calls++;

            return $provider;
        });

        $query = new Builder(DB::table('sort_once'));
        $strategy->applySort($query, TranslatableField::make('title'), 'asc');

        self::assertSame(1, $calls);
    }
}
