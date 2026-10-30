<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin;

use BlackParadise\CoreAdmin\Domain\Contracts\Auth\AuthenticationProviderContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Auth\AuthorizationProviderContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Events\EventDispatcherContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Files\FileStorageProviderContract;
use BlackParadise\CoreAdmin\Domain\Contracts\LocaleProviderContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Validation\ValidationProviderContract;
use BlackParadise\CoreAdmin\Domain\Contracts\ValueHasherContract;
use BlackParadise\LaravelAdmin\Infrastructure\Auth\LaravelAuthorizationProvider;
use BlackParadise\LaravelAdmin\Infrastructure\Auth\LaravelAuthProvider;
use BlackParadise\LaravelAdmin\Infrastructure\Events\LaravelEventDispatcher;
use BlackParadise\LaravelAdmin\Infrastructure\Files\LaravelFileStorage;
use BlackParadise\LaravelAdmin\Infrastructure\Hashing\LaravelValueHasher;
use BlackParadise\LaravelAdmin\Infrastructure\Locale\ConfigLocaleProvider;
use BlackParadise\LaravelAdmin\Infrastructure\Validation\LaravelValidationProvider;
use BlackParadise\LaravelAdmin\Providers\ConsoleServiceProvider;
use BlackParadise\LaravelAdmin\Providers\EntityDiscoveryServiceProvider;
use BlackParadise\LaravelAdmin\Providers\FieldTypeServiceProvider;
use BlackParadise\LaravelAdmin\Providers\PersistenceServiceProvider;
use BlackParadise\LaravelAdmin\Providers\RoutingServiceProvider;
use BlackParadise\LaravelAdmin\Support\AvailableLocalesResolver;
use Illuminate\Support\ServiceProvider;

/**
 * Main (and only auto-discovered) service provider for the BPAdmin Laravel adapter.
 *
 * Merges package config, binds the remaining core contracts and aggregates the sub-providers:
 * persistence, field types, entity discovery, routing and console.
 */
final class DashboardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/bpadmin.php', 'bpadmin');

        $this->app->singleton(AvailableLocalesResolver::class, fn($app): AvailableLocalesResolver => new AvailableLocalesResolver(
            $app['config']->get('bpadmin.locales'),
            $app->langPath('vendor/bpadmin'),
        ));

        $this->app->bind(AuthenticationProviderContract::class, LaravelAuthProvider::class);
        $this->app->bind(AuthorizationProviderContract::class, LaravelAuthorizationProvider::class);
        $this->app->bind(FileStorageProviderContract::class, LaravelFileStorage::class);
        $this->app->bind(ValidationProviderContract::class, LaravelValidationProvider::class);
        // One shared, scope-aware dispatcher: every use case must record into the same write scope.
        $this->app->scoped(LaravelEventDispatcher::class);
        $this->app->bind(
            EventDispatcherContract::class,
            static fn($app): LaravelEventDispatcher => $app->make(LaravelEventDispatcher::class),
        );
        $this->app->bind(ValueHasherContract::class, LaravelValueHasher::class);
        $this->app->bind(LocaleProviderContract::class, ConfigLocaleProvider::class);

        // force=true keeps register() re-runnable: sub-providers are always (re)built and booted
        // by the aggregator. Order matters for boot: persistence, field types, discovery, routing, console.
        $this->app->register(PersistenceServiceProvider::class, true);
        $this->app->register(FieldTypeServiceProvider::class, true);
        $this->app->register(EntityDiscoveryServiceProvider::class, true);
        $this->app->register(RoutingServiceProvider::class, true);
        $this->app->register(ConsoleServiceProvider::class, true);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/bpadmin.php' => config_path('bpadmin.php'),
        ], 'bpadmin-config');
    }
}
