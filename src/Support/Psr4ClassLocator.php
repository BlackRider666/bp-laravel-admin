<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Support;

use Composer\Autoload\ClassLoader;

/**
 * Derives the fully-qualified class name of an entity definition file.
 *
 * Resolution strategy:
 *   1. Match the file against the longest PSR-4 prefix in the application's composer.json.
 *   2. Fall back to the legacy `app/` → `App\` convention.
 *   3. Log a warning when the file is outside any known prefix.
 */
final class Psr4ClassLocator
{
    /** @var array<string, list<string>>|null */
    private static ?array $map = null;

    /** @var array<string, list<string>>|null Merged (composer.json + runtime loader) map, built once per instance. */
    private ?array $mergedMap = null;

    public function classFromPath(string $filePath): ?string
    {
        $byPsr4 = $this->classFromPsr4($filePath);
        if ($byPsr4 !== null) {
            return $byPsr4;
        }

        $appPath = realpath(app_path());
        if ($appPath !== false && str_starts_with($filePath, $appPath . DIRECTORY_SEPARATOR)) {
            $relative = ltrim(str_replace($appPath, '', $filePath), DIRECTORY_SEPARATOR);
            $relative = str_replace(['/', '\\'], '\\', $relative);
            return 'App\\' . substr($relative, 0, -4);
        }

        if (function_exists('logger')) {
            logger()->warning(
                'BPAdmin: cannot derive FQCN for entity file outside known PSR-4 prefixes',
                ['path' => $filePath],
            );
        }
        return null;
    }

    /**
     * Picks the LONGEST matching prefix so nested namespaces win over their
     * parents. The composer.json map is cached per process.
     */
    private function classFromPsr4(string $filePath): ?string
    {
        self::$map ??= $this->loadPsr4Map();
        $map = $this->mergedMap ??= $this->withComposerLoaderMap(self::$map);

        $bestDir = null;
        $bestNs  = '';
        foreach ($map as $namespace => $dirs) {
            foreach ($dirs as $dir) {
                $real = realpath($dir);
                if ($real === false) {
                    continue;
                }
                $prefix = $real . DIRECTORY_SEPARATOR;
                if (str_starts_with($filePath, $prefix) && strlen($real) > strlen($bestDir ?? '')) {
                    $bestDir = $real;
                    $bestNs  = $namespace;
                }
            }
        }

        if ($bestDir === null) {
            return null;
        }

        $relative = substr($filePath, strlen($bestDir) + 1);
        $relative = str_replace(['/', '\\'], '\\', $relative);
        $class    = rtrim($bestNs, '\\') . '\\' . substr($relative, 0, -4);

        return class_exists($class) ? $class : null;
    }

    /**
     * Merges the PSR-4 prefixes Composer's runtime autoloader actually knows (covers packages and
     * autoload-dev namespaces that the app's own composer.json does not declare).
     *
     * @param array<string, list<string>> $map
     * @return array<string, list<string>>
     */
    private function withComposerLoaderMap(array $map): array
    {
        if (!class_exists(ClassLoader::class)) {
            return $map;
        }

        foreach (ClassLoader::getRegisteredLoaders() as $loader) {
            foreach ($loader->getPrefixesPsr4() as $namespace => $dirs) {
                $map[$namespace] = array_values(array_unique([...($map[$namespace] ?? []), ...$dirs]));
            }
        }

        return $map;
    }

    /**
     * @return array<string, list<string>>
     */
    private function loadPsr4Map(): array
    {
        $composerPath = base_path('composer.json');
        if (!is_file($composerPath)) {
            return [];
        }
        $raw = file_get_contents($composerPath);
        if ($raw === false) {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }
        $psr4 = $decoded['autoload']['psr-4'] ?? [];
        if (!is_array($psr4)) {
            return [];
        }
        $normalized = [];
        foreach ($psr4 as $namespace => $dirs) {
            $absDirs = [];
            foreach ((array) $dirs as $dir) {
                $absDirs[] = base_path((string) $dir);
            }
            $normalized[(string) $namespace] = $absDirs;
        }
        return $normalized;
    }
}
