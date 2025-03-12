<?php


namespace BlackParadise\LaravelAdmin;

use BlackParadise\LaravelAdmin\Console\GenerateTranslation;
use BlackParadise\LaravelAdmin\Console\Install;
use BlackParadise\LaravelAdmin\Http\Actions\Auth\{LoginPageAction,
    LoginAction,
    LogoutAction};
use BlackParadise\LaravelAdmin\Http\Actions\Interfaces\Auth\{LoginPageActionInterface,
    LoginActionInterface,
    LogoutActionInterface};
use BlackParadise\LaravelAdmin\Http\Actions\Entity\{CreateEntityAction,
    DeleteEntityAction,
    EditEntityAction,
    IndexEntityAction,
    ShowEntityAction,
    StoreEntityAction,
    UpdateEntityAction};
use BlackParadise\LaravelAdmin\Http\Actions\Interfaces\Entity\{CreateEntityInterface,
    DeleteEntityInterface,
    EditEntityInterface,
    IndexEntityInterface,
    ShowEntityInterface,
    StoreEntityInterface,
    UpdateEntityInterface};
use BlackParadise\LaravelAdmin\Http\Middleware\AdminAuth;
use BlackParadise\LaravelAdmin\Http\Middleware\EntityExistMiddleware;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class DashboardServiceProvider extends ServiceProvider
{
    protected $configPath = 'bpadmin';
    /**
     * Boot
     */
    public function boot()
    {
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
            __DIR__ . '/../config/dashboard.php' => config_path('bpadmin.php'),
            __DIR__ . '/../resources/lang' => resource_path('lang/vendor/bpadmin'),
        ], 'bpadmin::core-min');
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

        $this->app->bind(LoginPageActionInterface::class, function ($app) {
            return $this->resolveAuthAction($app, 'loginPage', LoginPageAction::class);
        });

        $this->app->bind(LoginActionInterface::class, function ($app) {
            return $this->resolveAuthAction($app, 'login', LoginAction::class);
        });

        $this->app->bind(LogoutActionInterface::class, function ($app) {
            return $this->resolveAuthAction($app, 'logout', LogoutAction::class);
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

    private function resolveAuthAction($app, string $action, string $defaultClass)
    {
        $customAction = config("bpadmin.auth.custom_actions.$action");

        if ($customAction && class_exists($customAction)) {
            return $app->make($customAction);
        }

        return $app->make($defaultClass);
    }
}
