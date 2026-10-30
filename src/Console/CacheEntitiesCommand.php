<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Console;

use BlackParadise\LaravelAdmin\Core\DefinitionValidator;
use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Support\Psr4ClassLocator;
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

        $violations = $this->validateDiscovered($classes);
        if ($violations !== []) {
            $this->error(sprintf('%d definition violation(s); manifest NOT written:', count($violations)));
            foreach ($violations as $violation) {
                $this->line(sprintf('  [%s] %s', $violation['rule'], $violation['message']));
            }

            return self::FAILURE;
        }

        $this->writeManifest($manifestPath, $classes);
        $this->info(sprintf('Cached %d entity definition(s) to %s', count($classes), $manifestPath));

        return self::SUCCESS;
    }

    /**
     * @param list<class-string> $classes
     * @return list<array{definition: string, subject: string, rule: string, message: string}>
     */
    private function validateDiscovered(array $classes): array
    {
        $registry = new EntityDefinitionRegistry();
        foreach ($classes as $class) {
            $registry->register($this->laravel->make($class));
        }

        return $this->laravel->make(DefinitionValidator::class)->validate($registry);
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
            $class = $this->laravel->make(Psr4ClassLocator::class)->classFromPath($real);
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
