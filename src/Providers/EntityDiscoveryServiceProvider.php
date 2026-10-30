<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Providers;

use BlackParadise\CoreAdmin\Domain\Exceptions\InvalidDefinitionException;
use BlackParadise\LaravelAdmin\Console\CacheEntitiesCommand;
use BlackParadise\LaravelAdmin\Core\DefinitionValidator;
use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\Query\FieldQueryRegistry;
use BlackParadise\LaravelAdmin\Support\Psr4ClassLocator;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Populates the EntityDefinitionRegistry from the manifest or by scanning discovery paths.
 */
final class EntityDiscoveryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EntityDefinitionRegistry::class);
        $this->app->singleton(Psr4ClassLocator::class);
        $this->app->singleton(
            DefinitionValidator::class,
            static fn($app): DefinitionValidator => new DefinitionValidator(
                static fn(): FieldQueryRegistry => $app->make(FieldQueryRegistry::class),
            ),
        );
    }

    public function boot(): void
    {
        $manifestExists = $this->registerEntitiesFromConfig();

        // Console: never abort boot (artisan, incl. `bpadmin:doctor`, must stay reachable; doctor reports).
        // Manifest present: `bpadmin:cache` already validated it, so skip the per-request walk.
        if ($manifestExists || $this->app->runningInConsole()) {
            return;
        }

        $this->validateDefinitions($this->app->make(EntityDefinitionRegistry::class));
    }

    /**
     * Boot-time guard (see {@see DefinitionValidator}): model-less misuse, non-queryable
     * searchable/sortable/filterable fields and embedding of unregistered definitions.
     *
     * @throws InvalidDefinitionException listing every violation
     */
    private function validateDefinitions(EntityDefinitionRegistry $registry): void
    {
        $validator = $this->app->make(DefinitionValidator::class);
        $violations = $validator->bootViolations($registry);

        if ($violations !== []) {
            throw new InvalidDefinitionException(implode("\n", array_column($violations, 'message')));
        }
    }

    /**
     * Register entity definitions either from a pre-built manifest
     * (`bootstrap/cache/bpadmin-entities.php`) or by recursively scanning
     * the configured discovery paths.
     *
     * The manifest is the cheap path: a single `require` followed by
     * `$this->app->make($class)` per entry. The filesystem walk only runs
     * when no manifest exists.
     *
     * @return bool whether the manifest was used
     */
    private function registerEntitiesFromConfig(): bool
    {
        $registry = $this->app->make(EntityDefinitionRegistry::class);

        $manifestPath = CacheEntitiesCommand::manifestPath();
        if (is_file($manifestPath)) {
            /** @var array<int|string, mixed> $classes */
            $classes = (array) require $manifestPath;
            foreach ($classes as $class) {
                if (!is_string($class)) {
                    continue;
                }
                if (!class_exists($class)) {
                    continue;
                }
                if (!is_subclass_of($class, EntityDefinition::class)) {
                    continue;
                }
                $registry->register($this->app->make($class));
            }
            return true;
        }

        if (!$this->app->environment(['local', 'testing'])) {
            Log::warning(
                'BPAdmin: entity manifest missing — every request scans the filesystem. '
                . 'Run "php artisan bpadmin:cache" during deploy (analogous to route:cache).',
                ['manifest' => $manifestPath],
            );
        }

        $paths = $this->app->make(Repository::class)->get('bpadmin.discovery_paths', [app_path('BPAdmin')]);

        foreach ($paths as $directory) {
            $this->discoverEntities($directory, $registry);
        }

        return false;
    }

    private function discoverEntities(string $directory, EntityDefinitionRegistry $registry): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $locator = $this->app->make(Psr4ClassLocator::class);

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)) as $file) {
            if (!$file->isFile()) {
                continue;
            }
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $class = $locator->classFromPath($file->getRealPath());
            if ($class === null) {
                continue;
            }
            if (!class_exists($class)) {
                continue;
            }

            if (!is_subclass_of($class, EntityDefinition::class)) {
                continue;
            }

            $registry->register($this->app->make($class));
        }
    }
}
