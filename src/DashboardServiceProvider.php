<?php


namespace BlackParadise\Admin;

use Illuminate\Support\ServiceProvider;
use PackageVersions\Versions;
use Illuminate\Contracts\Http\Kernel;
use  Illuminate\View\Middleware\ShareErrorsFromSession;

class DashboardServiceProvider
{
    protected $configPath = 'bpadmin';
    /**
     * Boot
     */
    public function boot()
    {
        $this->loadViewsFrom(__DIR__ . './resources/views', $this->configPath);

        $this->registerPublishes();

        $this->registerCommands();

        $this->loadTranslations();

        $this->loadRoutesFrom(__DIR__ . './routes/');
    }

    /**
     * Register
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . './config/dashboard.php',
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
                //
            ]);
        }
    }

    /**
     * Register all publishes
     */
    protected function registerPublishes()
    {
        $this->publishes([
            __DIR__ . './resources/views' => resource_path('views/vendor/bpadmin'),
            __DIR__ . './public' => public_path('bpadmin'),
            __DIR__ . './config' => config_path('bpadmin'),
            __DIR__ . './resources/lang' => resource_path('lang/vendor/bpadmin'),
        ], 'bpadmin::all');

        $this->publishes([
            __DIR__ . './public' => public_path('bpadmin'),
            __DIR__ . './config' => config_path('bpadmin'),
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