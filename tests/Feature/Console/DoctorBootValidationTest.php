<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Feature\Console;

use BlackParadise\CoreAdmin\Domain\Exceptions\InvalidDefinitionException;
use BlackParadise\LaravelAdmin\Console\CacheEntitiesCommand;
use BlackParadise\LaravelAdmin\Core\DefinitionValidator;
use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Providers\EntityDiscoveryServiceProvider;
use BlackParadise\LaravelAdmin\Tests\Feature\Console\Fixtures\Doctor\DoctorCleanItem;
use BlackParadise\LaravelAdmin\Tests\Feature\Console\Fixtures\Doctor\DoctorFieldsCounter;
use BlackParadise\LaravelAdmin\Tests\Feature\Console\Fixtures\Doctor\DoctorHostWithUnregisteredChild;
use BlackParadise\LaravelAdmin\Tests\Feature\Console\Fixtures\Doctor\DoctorSearchableRelation;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Foundation\Application;
use ReflectionProperty;

/**
 * Review 6.2 finding 1 / A43: app BOOT (EntityDiscoveryServiceProvider) fails with InvalidDefinitionException
 * for non-queryable searchable fields and unregistered embedded definitions (not only `bpadmin:doctor`).
 * Regression guard (existing, must stay green): TranslatableSearchTest a7 - boot validation must not
 * freeze the locale provider.
 */
final class DoctorBootValidationTest extends TestCase
{
    protected function tearDown(): void
    {
        @unlink(CacheEntitiesCommand::manifestPath());
        parent::tearDown();
    }

    /** Simulates a web request: boot validation only throws outside the console. */
    private function runAsWeb(): void
    {
        $property = new ReflectionProperty(Application::class, 'isRunningInConsole');
        $property->setValue(app(), false);
    }

    /** @param list<EntityDefinition> $definitions */
    private function bootWith(array $definitions): void
    {
        $this->runAsWeb();
        foreach ($definitions as $definition) {
            resolve(EntityDefinitionRegistry::class)->register($definition);
        }

        (new EntityDiscoveryServiceProvider(app()))->boot();
    }

    public function test_boot_fails_naming_the_field_when_a_non_queryable_field_is_searchable(): void
    {
        $this->expectException(InvalidDefinitionException::class);
        $this->expectExceptionMessageMatches('/comments/');
        $this->expectExceptionMessageMatches('/doctor_searchable_relation/');

        $this->bootWith([new DoctorSearchableRelation()]);
    }

    public function test_boot_fails_naming_the_class_when_an_unregistered_definition_is_embedded(): void
    {
        $this->expectException(InvalidDefinitionException::class);
        $this->expectExceptionMessageMatches('/doctor_host_with_unregistered_child/');
        $this->expectExceptionMessageMatches('/DoctorUnregisteredChild/');

        $this->bootWith([new DoctorHostWithUnregisteredChild()]);
    }

    public function test_boot_accepts_a_clean_definition(): void
    {
        $this->bootWith([new DoctorCleanItem()]);

        self::assertTrue(resolve(EntityDefinitionRegistry::class)->has('doctor_clean_item'));
    }

    public function test_web_boot_with_invalid_definition_still_throws(): void
    {
        $this->expectException(InvalidDefinitionException::class);

        $this->bootWith([new DoctorSearchableRelation()]);
    }

    public function test_boot_with_manifest_present_skips_validation(): void
    {
        $this->runAsWeb();
        $path = CacheEntitiesCommand::manifestPath();
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        file_put_contents($path, '<?php return ' . var_export([DoctorSearchableRelation::class], true) . ';');

        (new EntityDiscoveryServiceProvider(app()))->boot();

        self::assertTrue(resolve(EntityDefinitionRegistry::class)->has('doctor_searchable_relation'));
    }

    public function test_validation_builds_fields_once_per_definition(): void
    {
        $registry = resolve(EntityDefinitionRegistry::class);
        $registry->register(new DoctorFieldsCounter());
        DoctorFieldsCounter::$fieldsCalls = 0;

        resolve(DefinitionValidator::class)->validate($registry);

        self::assertSame(1, DoctorFieldsCounter::$fieldsCalls);
    }
}
