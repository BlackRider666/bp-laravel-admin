<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Providers;

use BlackParadise\LaravelAdmin\Http\Presenters\AuthPresenterInterface;
use BlackParadise\LaravelAdmin\Http\Presenters\DashboardPresenterInterface;
use BlackParadise\LaravelAdmin\Http\Presenters\EntityPresenterInterface;
use BlackParadise\LaravelAdmin\Http\Presenters\Json\JsonAuthPresenter;
use BlackParadise\LaravelAdmin\Http\Presenters\Json\JsonDashboardPresenter;
use BlackParadise\LaravelAdmin\Http\Presenters\Json\JsonEntityPresenter;
use BlackParadise\LaravelAdmin\Routing\BPAdminRouteRegistrar;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

/**
 * Routes, publishable assets, translations and default (JSON) presenter bindings.
 */
final class RoutingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // bindIf() ensures UI packages registered earlier (e.g. Blade, Inertia)
        // are not overwritten by these defaults.
        $this->app->bindIf(EntityPresenterInterface::class, JsonEntityPresenter::class);
        $this->app->bindIf(AuthPresenterInterface::class, JsonAuthPresenter::class);
        $this->app->bindIf(DashboardPresenterInterface::class, JsonDashboardPresenter::class);
    }

    public function boot(): void
    {
        $config = $this->app->make(Repository::class);

        if (!($this->app instanceof Application && is_file($this->app->getCachedRoutesPath()))) {
            (new BPAdminRouteRegistrar(
                $this->app->make(Router::class),
                $config->get('bpadmin.prefix', 'admin'),
                $config->get('bpadmin.middleware', ['web']),
            ))->register();
        }

        $this->loadTranslationsFrom(__DIR__ . '/../../resources/lang', 'bpadmin');

        $this->publishes([
            __DIR__ . '/../../resources/lang' => $this->app->langPath('vendor/bpadmin'),
        ], 'bpadmin-lang');
    }
}
