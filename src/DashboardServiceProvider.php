<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin;

use BlackParadise\CoreAdmin\Domain\Contracts\Auth\AuthenticationProviderContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Auth\AuthorizationProviderContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Entity\RelationOptionsProviderContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Events\EventDispatcherContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Files\FileStorageProviderContract;
use BlackParadise\CoreAdmin\Domain\Contracts\LocaleProviderContract;
use BlackParadise\CoreAdmin\Domain\Contracts\TransactionContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Validation\ValidationProviderContract;
use BlackParadise\CoreAdmin\Domain\Contracts\ValueHasherContract;
use BlackParadise\CoreAdmin\Domain\Fields\BelongsToField;
use BlackParadise\CoreAdmin\Domain\Fields\BelongsToManyField;
use BlackParadise\CoreAdmin\Domain\Fields\BooleanField;
use BlackParadise\CoreAdmin\Domain\Fields\DateField;
use BlackParadise\CoreAdmin\Domain\Fields\DateTimeField;
use BlackParadise\CoreAdmin\Domain\Fields\EditorField;
use BlackParadise\CoreAdmin\Domain\Fields\EnumField;
use BlackParadise\CoreAdmin\Domain\Fields\FileField;
use BlackParadise\CoreAdmin\Domain\Fields\HashedField;
use BlackParadise\CoreAdmin\Domain\Fields\HasManyField;
use BlackParadise\CoreAdmin\Domain\Fields\HasOneField;
use BlackParadise\CoreAdmin\Domain\Fields\HiddenField;
use BlackParadise\CoreAdmin\Domain\Fields\ImageField;
use BlackParadise\CoreAdmin\Domain\Fields\MorphFileField;
use BlackParadise\CoreAdmin\Domain\Fields\MorphManyField;
use BlackParadise\CoreAdmin\Domain\Fields\MorphToField;
use BlackParadise\CoreAdmin\Domain\Fields\NumberField;
use BlackParadise\CoreAdmin\Domain\Fields\PhoneField;
use BlackParadise\CoreAdmin\Domain\Fields\RelationPathField;
use BlackParadise\CoreAdmin\Domain\Fields\TextareaField;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\CoreAdmin\Domain\Fields\TranslatableField;
use BlackParadise\CoreAdmin\Domain\Mutators\EntityMutatorInterface;
use BlackParadise\CoreAdmin\Domain\Repositories\EntityRepositoryInterface;
use BlackParadise\LaravelAdmin\Console\CacheEntitiesCommand;
use BlackParadise\LaravelAdmin\Console\GenerateTranslationCommand;
use BlackParadise\LaravelAdmin\Console\InstallCommand;
use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use BlackParadise\LaravelAdmin\Core\FieldTypeRegistry;
use BlackParadise\LaravelAdmin\Http\Presenters\AuthPresenterInterface;
use BlackParadise\LaravelAdmin\Http\Presenters\DashboardPresenterInterface;
use BlackParadise\LaravelAdmin\Http\Presenters\EntityPresenterInterface;
use BlackParadise\LaravelAdmin\Http\Presenters\Json\JsonAuthPresenter;
use BlackParadise\LaravelAdmin\Http\Presenters\Json\JsonDashboardPresenter;
use BlackParadise\LaravelAdmin\Http\Presenters\Json\JsonEntityPresenter;
use BlackParadise\LaravelAdmin\Infrastructure\Auth\LaravelAuthorizationProvider;
use BlackParadise\LaravelAdmin\Infrastructure\Auth\LaravelAuthProvider;
use BlackParadise\LaravelAdmin\Infrastructure\Events\LaravelEventDispatcher;
use BlackParadise\LaravelAdmin\Infrastructure\Files\LaravelFileStorage;
use BlackParadise\LaravelAdmin\Infrastructure\Hashing\LaravelValueHasher;
use BlackParadise\LaravelAdmin\Infrastructure\Locale\ConfigLocaleProvider;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\EloquentEntityMutator;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\EloquentEntityRepository;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\EloquentRelationOptionsProvider;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\LaravelTransaction;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\MorphFilePersister;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\RelationWriter;
use BlackParadise\LaravelAdmin\Infrastructure\Validation\LaravelValidationProvider;
use BlackParadise\LaravelAdmin\Routing\BPAdminRouteRegistrar;
use BlackParadise\LaravelAdmin\Support\AvailableLocalesResolver;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Main service provider for the BPAdmin Laravel adapter.
 *
 * Responsibilities:
 * - Merges package config
 * - Binds core contracts to their Laravel infrastructure implementations
 * - Registers the singleton EntityDefinitionRegistry and FieldTypeRegistry
 * - Loads routes and publishes config
 * - Registers console commands
 * - Populates the EntityDefinitionRegistry from config at boot time
 */
final class DashboardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/bpadmin.php', 'bpadmin');

        $this->app->singleton(EntityDefinitionRegistry::class);

        $this->app->singleton(FieldTypeRegistry::class, function (): FieldTypeRegistry {
            $registry = new FieldTypeRegistry();
            $this->registerDefaultFieldTypes($registry);
            return $registry;
        });

        $this->app->singleton(AvailableLocalesResolver::class, fn($app): AvailableLocalesResolver => new AvailableLocalesResolver(
            $app['config']->get('bpadmin.locales'),
            $app->langPath('vendor/bpadmin'),
        ));

        $this->app->bind(AuthenticationProviderContract::class, LaravelAuthProvider::class);
        $this->app->bind(AuthorizationProviderContract::class, LaravelAuthorizationProvider::class);
        $this->app->bind(EntityRepositoryInterface::class, EloquentEntityRepository::class);
        $this->app->bind(TransactionContract::class, LaravelTransaction::class);
        $this->app->singleton(RelationOptionsProviderContract::class, EloquentRelationOptionsProvider::class);

        // EloquentEntityMutator collaborators — singletons since both are stateless.
        // Container auto-resolves them into the mutator's constructor.
        $this->app->singleton(MorphFilePersister::class);
        $this->app->singleton(RelationWriter::class);
        $this->app->bind(EntityMutatorInterface::class, EloquentEntityMutator::class);
        $this->app->bind(FileStorageProviderContract::class, LaravelFileStorage::class);
        $this->app->bind(ValidationProviderContract::class, LaravelValidationProvider::class);
        $this->app->bind(EventDispatcherContract::class, LaravelEventDispatcher::class);
        $this->app->bind(ValueHasherContract::class, LaravelValueHasher::class);
        $this->app->bind(LocaleProviderContract::class, ConfigLocaleProvider::class);

        // Default presenter bindings (JSON/API mode).
        // bindIf() ensures UI packages registered earlier (e.g. Blade, Inertia)
        // are not overwritten by these defaults.
        $this->app->bindIf(EntityPresenterInterface::class, JsonEntityPresenter::class);
        $this->app->bindIf(AuthPresenterInterface::class, JsonAuthPresenter::class);
        $this->app->bindIf(DashboardPresenterInterface::class, JsonDashboardPresenter::class);
    }

    public function boot(): void
    {
        $config = $this->app['config'];

        if (!$this->app->routesAreCached()) {
            (new BPAdminRouteRegistrar(
                $this->app->make(Router::class),
                $config->get('bpadmin.prefix', 'admin'),
                $config->get('bpadmin.middleware', ['web']),
            ))->register();
        }

        $this->publishes([
            __DIR__ . '/../config/bpadmin.php' => config_path('bpadmin.php'),
        ], 'bpadmin-config');

        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'bpadmin');

        $this->publishes([
            __DIR__ . '/../resources/lang' => $this->app->langPath('vendor/bpadmin'),
        ], 'bpadmin-lang');

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                GenerateTranslationCommand::class,
                CacheEntitiesCommand::class,
            ]);
        }

        $this->registerEntitiesFromConfig();
    }

    /**
     * Register entity definitions either from a pre-built manifest
     * (`bootstrap/cache/bpadmin-entities.php`) or by recursively scanning
     * the configured discovery paths.
     *
     * The manifest is the cheap path: a single `require` followed by
     * `$this->app->make($class)` per entry. The filesystem walk only runs
     * when no manifest exists.
     */
    private function registerEntitiesFromConfig(): void
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
            return;
        }

        $paths = $this->app['config']->get('bpadmin.discovery_paths', [app_path('BPAdmin')]);

        foreach ($paths as $directory) {
            $this->discoverEntities($directory, $registry);
        }
    }

    private function discoverEntities(string $directory, EntityDefinitionRegistry $registry): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)) as $file) {
            if (!$file->isFile()) {
                continue;
            }
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $class = $this->classFromPath($file->getRealPath());
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

    /**
     * Derive the fully-qualified class name from an absolute file path.
     *
     * Resolution strategy:
     *   1. Match the file against the longest PSR-4 prefix in composer.json.
     *   2. Fall back to the legacy `app/` → `App\` convention.
     *   3. Log a warning when the file is outside any known prefix.
     *
     * @return class-string|null
     */
    private function classFromPath(string $filePath): ?string
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
     * Resolve FQCN from the application's composer.json psr-4 autoload map.
     *
     * Picks the LONGEST matching prefix so nested namespaces win over their
     * parents. Cached per-process via a static.
     *
     * @return class-string|null
     */
    private function classFromPsr4(string $filePath): ?string
    {
        static $map = null;
        if ($map === null) {
            $map = $this->loadPsr4Map();
        }

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
        /** @var class-string */
        return rtrim($bestNs, '\\') . '\\' . substr($relative, 0, -4);
    }

    /**
     * Read composer.json once and return the normalized psr-4 map with
     * absolute directories.
     *
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
     * Register the default field type → class mappings.
     * Only registers a type when the target class actually exists,
     * so the package is safe to use without bp-admin-core field classes present.
     */
    private function registerDefaultFieldTypes(FieldTypeRegistry $registry): void
    {
        /** @var array<string, class-string> $fields */
        $fields = [
            'text'          => TextField::class,
            'number'        => NumberField::class,
            'boolean'       => BooleanField::class,
            'datetime'      => DateTimeField::class,
            'email'         => TextField::class,
            'image'         => ImageField::class,
            'file'          => FileField::class,
            'belongs_to'    => BelongsToField::class,
            'belongs_to_many' => BelongsToManyField::class,
            'has_many'      => HasManyField::class,
            'has_one'       => HasOneField::class,
            'morph_to'      => MorphToField::class,
            'morph_many'    => MorphManyField::class,
            'morph_file'    => MorphFileField::class,
            'enum'          => EnumField::class,
            'editor'        => EditorField::class,
            'hashed'        => HashedField::class,
            'textarea'      => TextareaField::class,
            'date'          => DateField::class,
            'phone'         => PhoneField::class,
            'hidden'        => HiddenField::class,
            'translatable'  => TranslatableField::class,
            'relation_path' => RelationPathField::class,
        ];

        foreach ($fields as $type => $class) {
            if (class_exists($class)) {
                $registry->register($type, $class);
            }
        }
    }
}
