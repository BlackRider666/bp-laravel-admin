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
final class AvailableLocalesResolver
{
    /**
     * Memoized result of list().
     *
     * null means "not yet resolved". Empty-array is never a valid resolved
     * value (list() always falls back to ['en']), so null-sentinel is safe.
     * NOTE: if future logic ever legitimately returns [] here, switch to a
     * bool $resolved flag instead of relying on null-as-unset.
     *
     * @var string[]|null
     */
    private ?array $cache = null;

    public function __construct(
        private readonly mixed $configured,
        private readonly string $langDir,
    ) {}

    /**
     * @return string[]
     */
    public function list(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        if (is_array($this->configured) && $this->configured !== []) {
            return $this->cache = array_values($this->configured);
        }

        if (!is_dir($this->langDir)) {
            return $this->cache = ['en'];
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

        return $this->cache = ($locales === [] ? ['en'] : $locales);
    }
}
