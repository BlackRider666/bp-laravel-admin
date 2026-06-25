<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration;

use BlackParadise\LaravelAdmin\Console\CacheEntitiesCommand;
use BlackParadise\LaravelAdmin\DashboardServiceProvider;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Support\Facades\Log;

/**
 * Verifies that DashboardServiceProvider emits a boot-time warning
 * when running in production without a pre-built entity manifest.
 *
 * The warning should fire once when BOTH conditions hold:
 *   (a) the manifest file is absent, AND
 *   (b) the application environment is 'production'.
 *
 * It should NOT fire in non-production environments (local, testing, etc.),
 * and it should NOT fire when the manifest exists (even in production).
 */
final class ProductionManifestWarningTest extends TestCase
{
    private string $manifestPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manifestPath = CacheEntitiesCommand::manifestPath();
        // Ensure no stale manifest influences tests.
        if (is_file($this->manifestPath)) {
            unlink($this->manifestPath);
        }
    }

    protected function tearDown(): void
    {
        if (is_file($this->manifestPath)) {
            unlink($this->manifestPath);
        }
        parent::tearDown();
    }

    /**
     * When the app is in 'production' and the manifest is absent,
     * DashboardServiceProvider must emit exactly one warning.
     */
    public function test_warns_on_production_when_manifest_missing(): void
    {
        // Boot a fresh application in 'production' mode.
        $app = $this->createApplication();
        $app['env'] = 'production';
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
        // Empty discovery_paths so the scan runs but registers nothing.
        $app['config']->set('bpadmin.discovery_paths', []);

        // Spy AFTER createApplication() so the facade root is already wired.
        Log::spy();

        // The manifest must not exist before booting the provider.
        self::assertFileDoesNotExist($this->manifestPath);

        $provider = new DashboardServiceProvider($app);
        $provider->register();
        $provider->boot();

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(static fn(string $message, array $context): bool => str_contains($message, 'bpadmin:cache')
                && isset($context['manifest']));
    }

    /**
     * When the environment is NOT 'production' (e.g. 'testing' or 'local'),
     * no warning must be logged even if the manifest is absent.
     */
    public function test_does_not_warn_when_not_production(): void
    {
        $app = $this->createApplication();
        $app['env'] = 'testing';
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
        $app['config']->set('bpadmin.discovery_paths', []);

        // Spy AFTER createApplication() so the facade root is already wired.
        Log::spy();

        self::assertFileDoesNotExist($this->manifestPath);

        $provider = new DashboardServiceProvider($app);
        $provider->register();
        $provider->boot();

        Log::shouldNotHaveReceived('warning');
    }

    /**
     * When in 'production' but the manifest already exists,
     * no warning must be logged (the fast path is taken).
     */
    public function test_does_not_warn_when_manifest_present_in_production(): void
    {
        // Create a minimal valid manifest.
        $dir = dirname($this->manifestPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($this->manifestPath, "<?php\n\nreturn [];\n");

        $app = $this->createApplication();
        $app['env'] = 'production';
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
        $app['config']->set('bpadmin.discovery_paths', []);

        // Spy AFTER createApplication() so the facade root is already wired.
        Log::spy();

        self::assertFileExists($this->manifestPath);

        $provider = new DashboardServiceProvider($app);
        $provider->register();
        $provider->boot();

        Log::shouldNotHaveReceived('warning');
    }
}
