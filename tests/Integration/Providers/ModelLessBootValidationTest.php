<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Providers;

use BlackParadise\CoreAdmin\Domain\Exceptions\InvalidDefinitionException;
use BlackParadise\LaravelAdmin\Console\CacheEntitiesCommand;
use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Providers\EntityDiscoveryServiceProvider;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ModelLess\BootClean\FullyCustom;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ModelLess\BootClean\ReadOnlyNoMutator;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ModelLess\BootEmbeddedChild\ChildSource;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ModelLess\BootEmbeddedChild\HostWithEmbeddedChild;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ModelLess\BootRelationTarget\LookupSource;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ModelLess\BootRelationTarget\OrderWithLookup;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ModelLess\BootWritable\WritableNoMutator;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Foundation\Application;
use ReflectionProperty;

/**
 * A33/A34 boot validation. Definitions are fed to the discovery provider into the registry directly and the provider's boot() is re-run in a web context (no manifest).
 *
 * Contract for the impl (Task 4.5): boot() throws {@see InvalidDefinitionException} whose message
 * names the offending definition(s) (entity names) and, for the model-less cases, contains "model-less".
 */
final class ModelLessBootValidationTest extends TestCase
{
    protected function tearDown(): void
    {
        @unlink(CacheEntitiesCommand::manifestPath());
        parent::tearDown();
    }

    /** @param list<class-string<EntityDefinition>> $classes */
    private function bootAsWeb(array $classes): void
    {
        $property = new ReflectionProperty(Application::class, 'isRunningInConsole');
        $property->setValue(app(), false);

        $registry = resolve(EntityDefinitionRegistry::class);
        foreach ($classes as $class) {
            $registry->register(new $class());
        }

        (new EntityDiscoveryServiceProvider(app()))->boot();
    }

    public function test_boot_fails_for_writable_model_less_definition_without_mutator(): void
    {
        $this->expectException(InvalidDefinitionException::class);
        $this->expectExceptionMessageMatches('/writable_no_mutator/');
        $this->expectExceptionMessageMatches('/mutator/i');

        $this->bootAsWeb([WritableNoMutator::class]);
    }

    public function test_boot_fails_when_an_eloquent_relation_targets_a_model_less_definition(): void
    {
        $this->expectException(InvalidDefinitionException::class);
        $this->expectExceptionMessageMatches('/order_with_lookup/');
        $this->expectExceptionMessageMatches('/lookup_source/');
        $this->expectExceptionMessageMatches('/model-less/i');

        $this->bootAsWeb([LookupSource::class, OrderWithLookup::class]);
    }

    public function test_boot_fails_when_a_model_less_definition_is_embedded_as_a_child(): void
    {
        $this->expectException(InvalidDefinitionException::class);
        $this->expectExceptionMessageMatches('/host_with_embedded_child/');
        $this->expectExceptionMessageMatches('/child_source/');
        $this->expectExceptionMessageMatches('/model-less/i');

        $this->bootAsWeb([ChildSource::class, HostWithEmbeddedChild::class]);
    }

    public function test_boot_accepts_read_only_and_fully_custom_model_less_definitions(): void
    {
        $this->bootAsWeb([ReadOnlyNoMutator::class, FullyCustom::class]);

        $registry = app()->make(EntityDefinitionRegistry::class);
        self::assertTrue($registry->has('read_only_no_mutator') && $registry->has('fully_custom'));
    }
}
