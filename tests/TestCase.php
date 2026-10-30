<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests;

use BlackParadise\LaravelAdmin\DashboardServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\ParallelTesting;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

/**
 * Base test case for Laravel adapter integration and feature tests.
 *
 * Bootstraps the full Laravel application via Orchestra Testbench
 * and registers the DashboardServiceProvider.
 */
abstract class TestCase extends OrchestraTestCase
{
    /**
     * {@inheritDoc}
     */
    protected function getPackageProviders($app): array
    {
        return [
            DashboardServiceProvider::class,
        ];
    }

    /**
     * Storage::fake() cleans a fixed shared dir (storage/framework/testing/disks/<disk>) on every call,
     * so concurrent pest processes (stop-hook QA, manual runs) wipe each other's files. Give each
     * process its own root via Laravel's parallel-testing token, and remove it on exit.
     */
    protected function setUp(): void
    {
        $token = 'p' . getmypid();
        parent::setUp();

        static $registered = false;
        if (! $registered) {
            $registered = true;
            $dir = storage_path('framework/testing/disks');
            register_shutdown_function(static function () use ($dir, $token): void {
                $leftovers = glob($dir . '/*_test_' . $token);
                foreach ($leftovers === false ? [] : $leftovers as $leftover) {
                    (new Filesystem())->deleteDirectory($leftover);
                }
            });
        }

        ParallelTesting::resolveTokenUsing(static fn(): string => $token);
    }

    /**
     * {@inheritDoc}
     */
    protected function defineEnvironment($app): void
    {
        // Per-process bootstrap/cache: tests write/delete the entities manifest there, which would
        // otherwise be shared (and raced) between concurrent pest processes.
        $bootstrap = sys_get_temp_dir() . '/bpadmin-laravel-admin-bootstrap-' . getmypid();
        if (! is_dir($bootstrap . '/cache')) {
            mkdir($bootstrap . '/cache', 0755, true);
            register_shutdown_function(static function () use ($bootstrap): void {
                (new Filesystem())->deleteDirectory($bootstrap);
            });
        }
        $app->useBootstrapPath($bootstrap);
        // Same for lang/ (bpadmin:translations writes lang/vendor/bpadmin/*).
        $app->useLangPath($bootstrap . '/lang');

        // Required by Laravel's session/cookie infrastructure when running HTTP tests.
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));

        // Use an in-memory SQLite database for integration tests.
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        // Disable entity auto-discovery so tests control registration explicitly.
        $app['config']->set('bpadmin.discovery_paths', []);
    }
}
