<?php


namespace BlackParadise\LaravelAdmin;

use BlackParadise\LaravelAdmin\Http\Middleware\EntityExistMiddleware;
use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Http\Kernel;
use  Illuminate\View\Middleware\ShareErrorsFromSession;
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
    }

    /**
     * Register console commands
     */
    protected function registerCommands()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateTranslation::class,
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
            __DIR__ . '/../public' => public_path('bpadmin'),
            __DIR__ . '/../config' => config_path('bpadmin'),
            __DIR__ . '/../resources/lang' => resource_path('lang/vendor/bpadmin'),
        ], 'bpadmin::all');

        $this->publishes([
            __DIR__ . '/../public' => public_path('bpadmin'),
            __DIR__ . '/../config' => config_path('bpadmin'),
        ], 'bpadmin::min');
    }

    /**
     * Load dashboard translations
     */
    protected function loadTranslations()
    {
        $this->loadTranslationsFrom(__DIR__ . './resources/lang', 'bpadmin');
    }
}
