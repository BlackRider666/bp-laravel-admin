<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Feature\Console;

use BlackParadise\LaravelAdmin\Console\CacheEntitiesCommand;
use BlackParadise\LaravelAdmin\Tests\TestCase;

/**
 * Exercises the bpadmin:cache + bpadmin:cache --clear lifecycle.
 *
 * The discovery directory is intentionally empty (TestCase pins
 * `bpadmin.discovery_paths` to []), so the manifest produced contains
 * zero entries — sufficient to verify file creation, format, and removal.
 */
final class CacheEntitiesCommandTest extends TestCase
{
    private string $manifest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manifest = CacheEntitiesCommand::manifestPath();
        if (is_file($this->manifest)) {
            unlink($this->manifest);
        }
    }

    protected function tearDown(): void
    {
        if (is_file($this->manifest)) {
            unlink($this->manifest);
        }
        parent::tearDown();
    }

    public function test_cache_creates_manifest_file(): void
    {
        $this->artisan('bpadmin:cache')->assertExitCode(0);

        self::assertFileExists($this->manifest);
        $loaded = require $this->manifest;
        self::assertIsArray($loaded);
    }

    public function test_clear_removes_manifest_file(): void
    {
        // Pre-create.
        $this->artisan('bpadmin:cache')->assertExitCode(0);
        self::assertFileExists($this->manifest);

        $this->artisan('bpadmin:cache', ['--clear' => true])->assertExitCode(0);
        self::assertFileDoesNotExist($this->manifest);
    }

    public function test_clear_noop_when_no_manifest(): void
    {
        // Already removed in setUp; ensure clear does not fail.
        $this->artisan('bpadmin:cache', ['--clear' => true])->assertExitCode(0);
        self::assertFileDoesNotExist($this->manifest);
    }
}
