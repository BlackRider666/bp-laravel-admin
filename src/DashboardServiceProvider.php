<?php


namespace BlackParadise\LaravelAdmin;

use BlackParadise\LaravelAdmin\Console\Install;
use BlackParadise\LaravelAdmin\Http\Actions\Entity\{CreateEntityAction,
    DeleteEntityAction,
    EditEntityAction,
    IndexEntityAction,
    Interface\CreateEntityInterface,
    Interface\DeleteEntityInterface,
    Interface\EditEntityInterface,
    Interface\IndexEntityInterface,
    Interface\ShowEntityInterface,
    Interface\StoreEntityInterface,
    Interface\UpdateEntityInterface,
    ShowEntityAction,
    StoreEntityAction,
    UpdateEntityAction};
use BlackParadise\LaravelAdmin\Http\Middleware\AdminAuth;
use BlackParadise\LaravelAdmin\Http\Middleware\EntityExistMiddleware;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Illuminate\Routing\Router;
use BlackParadise\LaravelAdmin\Console\GenerateTranslation;

class DashboardServiceProvider extends ServiceProvider
{
    protected $configPath = 'bpadmin';
    /**
     * Boot
     */
    public function boot()
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', $this->configPath);

        $this->registerPublishes();

        $this->registerCommands();

        $this->loadTranslations();
        //web routes
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('exists', EntityExistMiddleware::class);
        $router->aliasMiddleware('admin-auth', AdminAuth::class);
    }

    /**
     * Register
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/dashboard.php',
            $this->configPath
        );

        $this->bindInterfaces();
    }

    /**
     * Register console commands
     */
    protected function registerCommands()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateTranslation::class,
                Install::class,
            ]);
        }
    }

    /**
     * Register all publishes
     */
    protected function registerPublishes()
    {
        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/bpadmin'),
            __DIR__ . '/../public' => public_path('/'),
            __DIR__ . '/../config/dashboard.php' => config_path('bpadmin.php'),
            __DIR__ . '/../resources/lang' => resource_path('lang/vendor/bpadmin'),
            __DIR__ . '/../resources/js' => resource_path('js/vendor/bpadmin'),
        ], 'bpadmin::all');

        $this->publishes([
            __DIR__ . '/../public' => public_path('/'),
            __DIR__ . '/../config/dashboard.php' => config_path('bpadmin.php'),
            __DIR__ . '/../resources/lang' => resource_path('lang/vendor/bpadmin'),
        ], 'bpadmin::min');
    }

    /**
     * Load dashboard translations
     */
    protected function loadTranslations()
    {
        $this->loadTranslationsFrom(__DIR__ . './resources/lang', 'bpadmin');
    }

    private function bindInterfaces()
    {
        $this->app->bind(IndexEntityInterface::class, function ($app) {
            return $this->resolveAction($app, 'index', IndexEntityAction::class);
        });

        $this->app->bind(CreateEntityInterface::class, function ($app) {
            return $this->resolveAction($app, 'create', CreateEntityAction::class);
        });

        $this->app->bind(StoreEntityInterface::class, function ($app) {
            return $this->resolveAction($app, 'store', StoreEntityAction::class);
        });

        $this->app->bind(ShowEntityInterface::class, function ($app) {
            return $this->resolveAction($app, 'show', ShowEntityAction::class);
        });

        $this->app->bind(EditEntityInterface::class, function ($app) {
            return $this->resolveAction($app, 'edit', EditEntityAction::class);
        });

        $this->app->bind(UpdateEntityInterface::class, function ($app) {
            return $this->resolveAction($app, 'update', UpdateEntityAction::class);
        });

        $this->app->bind(DeleteEntityInterface::class, function ($app) {
            return $this->resolveAction($app, 'destroy', DeleteEntityAction::class);
        });
    }

    private function resolveAction($app, string $action, string $defaultClass)
    {
        $request = $app->make('request');
        $entityName = $request->query('entity_name');

        $customAction = config("bpadmin.custom_actions.$entityName.$action");

        if ($customAction && class_exists($customAction)) {
            return $app->make($customAction);
        }

        return $app->make($defaultClass);
    }
}
