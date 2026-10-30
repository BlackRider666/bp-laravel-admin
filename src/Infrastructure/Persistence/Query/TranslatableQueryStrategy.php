<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Infrastructure\Persistence\Query;

use BlackParadise\CoreAdmin\Domain\Contracts\Fields\FieldContract;
use BlackParadise\CoreAdmin\Domain\Contracts\LocaleProviderContract;
use BlackParadise\LaravelAdmin\Support\AvailableLocalesResolver;
use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * Translatable field stored as a JSON object `{locale: text}` in a single column.
 *
 * Search: case-insensitive match against the VALUE of every available locale (never the raw
 * JSON text, so locale keys / `:` / `"` cannot match). Sort: by the default-locale value.
 * Locale codes and JSON paths are bindings only.
 */
final readonly class TranslatableQueryStrategy implements FieldQueryStrategyContract
{
    /** @param Closure():LocaleProviderContract $localeProvider lazy, so booting never freezes the locale provider */
    public function __construct(private Closure $localeProvider) {}

    public function isQueryable(): bool
    {
        return true;
    }

    public function applySearch(Builder $query, FieldContract $field, string $term): void
    {
        $locales = $this->safeLocales(($this->localeProvider)()->availableLocales());
        if ($locales === []) {
            $query->orWhereRaw('0 = 1');

            return;
        }

        $driver  = TranslatableJsonPath::driver($query);
        $column  = $query->getGrammar()->wrap($field->name());
        $needle  = mb_strtolower(LikeEscape::contains($term));

        foreach ($locales as $locale) {
            $value = TranslatableJsonPath::lowered(
                $query,
                $driver,
                TranslatableJsonPath::valueExpression($driver, $column),
            );
            $query->orWhereRaw(
                "{$value} LIKE ? " . LikeEscape::CLAUSE,
                [TranslatableJsonPath::binding($driver, $locale), $needle],
            );
        }
    }

    public function applySort(Builder $query, FieldContract $field, string $direction): void
    {
        $driver = TranslatableJsonPath::driver($query);
        $column = $query->getGrammar()->wrap($field->name());
        $dir    = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';

        $query->orderByRaw(
            TranslatableJsonPath::valueExpression($driver, $column) . ' ' . $dir,
            [TranslatableJsonPath::binding($driver, $this->sortLocale())],
        );
    }

    public function projection(FieldContract $field): array
    {
        return [$field->name()];
    }

    /**
     * Default locale when offered, otherwise the first available one (prevents NULL-sort when
     * app.locale is not among the configured locales).
     */
    private function sortLocale(): string
    {
        $provider  = ($this->localeProvider)();
        $available = $this->safeLocales($provider->availableLocales());
        $default   = $provider->defaultLocale();

        if (in_array($default, $available, true)) {
            return $default;
        }

        return $available[0] ?? $default;
    }

    /**
     * @param array<int|string, mixed> $locales
     * @return list<string>
     */
    private function safeLocales(array $locales): array
    {
        $safe = [];
        foreach ($locales as $locale) {
            if (AvailableLocalesResolver::isValidCode($locale)) {
                $safe[] = $locale;
            }
        }

        return $safe;
    }
}
