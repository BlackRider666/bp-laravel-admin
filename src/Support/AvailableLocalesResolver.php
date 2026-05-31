<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Support;

/**
 * Resolves the list of locales offered by the admin panel.
 *
 * Priority:
 *   1. Non-empty array injected as $configured — used as-is.
 *   2. Scan $langDir for subdirectories.
 *   3. Fallback ['en'] — package's bundled baseline.
 */
final readonly class AvailableLocalesResolver
{
    public function __construct(
        private mixed $configured,
        private string $langDir,
    ) {}

    /**
     * @return string[]
     */
    public function list(): array
    {
        if (is_array($this->configured) && $this->configured !== []) {
            return array_values($this->configured);
        }

        if (!is_dir($this->langDir)) {
            return ['en'];
        }

        $locales = [];

        foreach (scandir($this->langDir) ?: [] as $entry) {
            if ($entry === '.') {
                continue;
            }
            if ($entry === '..') {
                continue;
            }
            if (is_dir($this->langDir . DIRECTORY_SEPARATOR . $entry)) {
                $locales[] = $entry;
            }
        }

        return $locales === [] ? ['en'] : $locales;
    }
}
