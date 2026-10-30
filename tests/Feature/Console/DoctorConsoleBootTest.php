<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Feature\Console;

use BlackParadise\LaravelAdmin\Console\CacheEntitiesCommand;
use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;

/**
 * Final-review I-1: in console the app must BOOT with an invalid discovered definition, so
 * `bpadmin:doctor` / `bpadmin:cache` stay reachable (web boot still throws - see DoctorBootValidationTest).
 * The app here is booted (setUp) with discovery pointing at a directory holding one invalid definition.
 */
final class DoctorConsoleBootTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('bpadmin.discovery_paths', [__DIR__ . '/Fixtures/DoctorBoot']);
    }

    protected function tearDown(): void
    {
        @unlink(CacheEntitiesCommand::manifestPath());
        parent::tearDown();
    }

    public function test_console_boot_with_invalid_definition_does_not_throw_and_registers_it(): void
    {
        self::assertTrue(resolve(EntityDefinitionRegistry::class)->has('doctor_boot_invalid'));
    }

    public function test_doctor_command_with_invalid_definition_lists_violation_and_exits_one(): void
    {
        $exit = Artisan::call('bpadmin:doctor');

        self::assertSame(1, $exit);
        self::assertStringContainsString('non-queryable-field', Artisan::output());
    }

    public function test_cache_command_with_invalid_definition_exits_one_and_lists_violation(): void
    {
        $exit = Artisan::call('bpadmin:cache');

        self::assertSame(1, $exit);
        self::assertStringContainsString('non-queryable-field', Artisan::output());
    }

    public function test_cache_command_with_invalid_definition_writes_no_manifest(): void
    {
        Artisan::call('bpadmin:cache');

        self::assertFileDoesNotExist(CacheEntitiesCommand::manifestPath());
    }
}
