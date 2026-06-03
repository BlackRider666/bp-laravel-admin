<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Console;

use BlackParadise\LaravelAdmin\EntityDefinition;
use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Build (or clear) the entity-definition manifest cache.
 *
 * Without this cache, every request walks every configured discovery path,
 * loads every PHP file, and reflects to find EntityDefinition subclasses.
 * The manifest reduces that to a single `require` of an array of class
 * strings, which the service provider then instantiates lazily.
 *
 * Signatures:
 *  - `bpadmin:cache`         — generate manifest
 *  - `bpadmin:cache:clear`   — remove manifest
 *
 * Manifest path: `bootstrap/cache/bpadmin-entities.php`.
 */
final class CacheEntitiesCommand extends Command
{
    protected $signature = 'bpadmin:cache {--clear : Remove the manifest instead of regenerating it}';

    protected $description = 'Generate (or clear with --clear) the BPAdmin entity-definition manifest cache.';

    public const MANIFEST_FILENAME = 'bpadmin-entities.php';

    public function handle(): int
    {
        $manifestPath = self::manifestPath();

        if ((bool) $this->option('clear')) {
            return $this->clearManifest($manifestPath);
        }

        $paths = (array) config('bpadmin.discovery_paths', [app_path('BPAdmin')]);

        $classes = [];
        foreach ($paths as $directory) {
            foreach ($this->discoverInDirectory((string) $directory) as $class) {
                $classes[$class] = true;
            }
        }
        $classes = array_keys($classes);
        sort($classes);

        $this->writeManifest($manifestPath, $classes);
        $this->info(sprintf('Cached %d entity definition(s) to %s', count($classes), $manifestPath));

        return self::SUCCESS;
    }

    /**
     * @return list<class-string>
     */
    private function discoverInDirectory(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $found = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)) as $file) {
            if (!$file->isFile()) {
                continue;
            }
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $real = $file->getRealPath();
            if ($real === false) {
                continue;
            }
            $class = $this->classFromPath($real);
            if ($class === null) {
                continue;
            }
            if (!class_exists($class)) {
                continue;
            }
            if (!is_subclass_of($class, EntityDefinition::class)) {
                continue;
            }
            $found[] = $class;
        }
        return $found;
    }

    /**
     * Best-effort PSR-4 path → FQCN resolver.
     *
     * Strategy:
     *  1. Read composer.json psr-4 map; pick the longest prefix that contains
     *     the file path. Strip prefix, replace separators with `\`, drop `.php`.
     *  2. If that fails, fall back to the legacy `app/` → `App\` rule.
     */
    private function classFromPath(string $filePath): ?string
    {
        $byPsr4 = $this->classFromPsr4($filePath);
        if ($byPsr4 !== null) {
            return $byPsr4;
        }

        $appPath = realpath(app_path());
        if ($appPath === false) {
            return null;
        }
        if (!str_starts_with($filePath, $appPath . DIRECTORY_SEPARATOR)) {
            return null;
        }
        $relative = ltrim(str_replace($appPath, '', $filePath), DIRECTORY_SEPARATOR);
        $relative = str_replace(['/', '\\'], '\\', $relative);
        return 'App\\' . substr($relative, 0, -4);
    }

    private function classFromPsr4(string $filePath): ?string
    {
        static $map = null;

        if ($map === null) {
            $map = $this->loadPsr4Map();
        }

        $best   = null;
        $bestNs = '';
        foreach ($map as $namespace => $dirs) {
            foreach ($dirs as $dir) {
                $real = realpath($dir);
                if ($real === false) {
                    continue;
                }
                $prefix = $real . DIRECTORY_SEPARATOR;
                if (str_starts_with($filePath, $prefix) && strlen($real) > strlen($best ?? '')) {
                    $best   = $real;
                    $bestNs = $namespace;
                }
            }
        }

        if ($best === null) {
            return null;
        }

        $relative = substr($filePath, strlen($best) + 1);
        $relative = str_replace(['/', '\\'], '\\', $relative);
        return rtrim($bestNs, '\\') . '\\' . substr($relative, 0, -4);
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
            $dirs = (array) $dirs;
            $absDirs = [];
            foreach ($dirs as $dir) {
                $absDirs[] = base_path((string) $dir);
            }
            $normalized[(string) $namespace] = $absDirs;
        }
        return $normalized;
    }

    /**
     * @param list<class-string> $classes
     */
    private function writeManifest(string $path, array $classes): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $body = "<?php\n\nreturn " . var_export($classes, true) . ";\n";
        file_put_contents($path, $body);
    }

    private function clearManifest(string $path): int
    {
        if (is_file($path)) {
            unlink($path);
            $this->info("Removed manifest at {$path}");
            return self::SUCCESS;
        }
        $this->info('No manifest to remove.');
        return self::SUCCESS;
    }

    public static function manifestPath(): string
    {
        return rtrim(app()->bootstrapPath('cache'), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . self::MANIFEST_FILENAME;
    }
}
