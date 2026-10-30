<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Unit\Support;

use BlackParadise\LaravelAdmin\Support\Psr4ClassLocator;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Composer\Autoload\ClassLoader;

/** The merged Composer PSR-4 map is computed once per locator instance, not per call. */
final class Psr4ClassLocatorCacheTest extends TestCase
{
    public function test_prefix_registered_after_first_call_is_seen_only_by_a_new_instance(): void
    {
        $dir = sys_get_temp_dir() . '/bp_psr4_' . bin2hex(random_bytes(4));
        mkdir($dir);
        $namespace = 'BpPsr4Cache' . bin2hex(random_bytes(3));
        $file      = $dir . '/Probe.php';
        file_put_contents($file, "<?php\nnamespace {$namespace};\nfinal class Probe {}\n");

        try {
            $first = new Psr4ClassLocator();
            self::assertSame(self::class, $first->classFromPath((string) realpath(__FILE__)));

            $loaders = ClassLoader::getRegisteredLoaders();
            self::assertNotEmpty($loaders);
            $loader = reset($loaders);
            self::assertInstanceOf(ClassLoader::class, $loader);
            $loader->addPsr4($namespace . '\\', $dir);

            $realFile = (string) realpath($file);
            self::assertNull($first->classFromPath($realFile), 'same instance reuses its cached map');
            self::assertSame($namespace . '\\Probe', (new Psr4ClassLocator())->classFromPath($realFile));
        } finally {
            @unlink($file);
            @rmdir($dir);
        }
    }
}
