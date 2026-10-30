<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Providers;

use BlackParadise\LaravelAdmin\Console\CacheEntitiesCommand;
use BlackParadise\LaravelAdmin\Console\DoctorCommand;
use BlackParadise\LaravelAdmin\Console\GenerateTranslationCommand;
use BlackParadise\LaravelAdmin\Console\InstallCommand;
use Illuminate\Support\ServiceProvider;

/**
 * Registers artisan commands (console only).
 */
final class ConsoleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                GenerateTranslationCommand::class,
                CacheEntitiesCommand::class,
                DoctorCommand::class,
            ]);
        }
    }
}
