<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests;

use BlackParadise\LaravelAdmin\DashboardServiceProvider;
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
     * {@inheritDoc}
     */
    protected function defineEnvironment($app): void
    {
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
