<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration;

use BlackParadise\LaravelAdmin\Tests\TestCase;

/**
 * A17 (B15): config/bpadmin.php must contain 'allowed_download_disks' => ['public'].
 *
 * Bug: the key is absent from the published config. The controller falls back to
 * an in-code default instead of reading from config — operators cannot whitelist
 * additional download disks.
 *
 * Fix: add 'allowed_download_disks' => ['public'] to config/bpadmin.php.
 */
final class ConfigAllowedDownloadDisksTest extends TestCase
{
    // ------------------------------------------------------------------
    // A17 — config key allowed_download_disks exists with default ['public']
    // ------------------------------------------------------------------

    /**
     * After the package config is loaded, config('bpadmin.allowed_download_disks')
     * must return ['public'].
     *
     * Currently FAILS: the key does not exist → config() returns null.
     */
    public function test_allowed_download_disks_config_key_exists_with_public_default(): void
    {
        $value = config('bpadmin.allowed_download_disks');

        self::assertNotNull($value, "'bpadmin.allowed_download_disks' config key must exist");
        self::assertSame(['public'], $value);
    }
}
