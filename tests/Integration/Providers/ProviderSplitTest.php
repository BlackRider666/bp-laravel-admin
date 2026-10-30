<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Providers;

use BlackParadise\LaravelAdmin\Console\CacheEntitiesCommand;
use BlackParadise\LaravelAdmin\Console\GenerateTranslationCommand;
use BlackParadise\LaravelAdmin\Console\InstallCommand;
use BlackParadise\LaravelAdmin\Core\FieldTypeRegistry;
use BlackParadise\LaravelAdmin\DashboardServiceProvider;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\FieldPersisters\FieldPersisterRegistry;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\FieldPersisters\HashedPersister;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\Query\FieldQueryRegistry;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\Query\NotQueryableStrategy;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\CustomPersister\SlugField;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Console\Application as ConsoleApplication;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;

/**
 * A44: DashboardServiceProvider is the only discovered provider and aggregates five sub-providers.
 */
final class ProviderSplitTest extends TestCase
{
    private const NS = 'BlackParadise\\LaravelAdmin\\Providers\\';

    private const SUB_PROVIDERS = [
        'PersistenceServiceProvider',
        'FieldTypeServiceProvider',
        'EntityDiscoveryServiceProvider',
        'RoutingServiceProvider',
        'ConsoleServiceProvider',
    ];

    public function test_composer_discovers_only_dashboard_service_provider(): void
    {
        $composer = json_decode((string) file_get_contents(dirname(__DIR__, 3) . '/composer.json'), true);

        self::assertSame([DashboardServiceProvider::class], $composer['extra']['laravel']['providers']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function subProviders(): array
    {
        $cases = [];
        foreach (self::SUB_PROVIDERS as $name) {
            $cases[$name] = [self::NS . $name];
        }

        return $cases;
    }

    #[DataProvider('subProviders')]
    public function test_sub_provider_class_exists(string $class): void
    {
        self::assertTrue(class_exists($class), "{$class} must exist.");
    }

    #[DataProvider('subProviders')]
    public function test_sub_provider_is_registered_when_dashboard_provider_boots(string $class): void
    {
        self::assertNotNull(app()->getProvider($class), "{$class} must be registered by DashboardServiceProvider.");
    }

    public function test_dashboard_provider_still_resolves_all_registries_as_singletons(): void
    {
        self::assertSame(app()->make(FieldTypeRegistry::class), app()->make(FieldTypeRegistry::class));
        self::assertSame(app()->make(FieldPersisterRegistry::class), app()->make(FieldPersisterRegistry::class));
        self::assertSame(app()->make(FieldQueryRegistry::class), app()->make(FieldQueryRegistry::class));
    }

    public function test_console_commands_are_registered_when_running_in_console(): void
    {
        $commands = array_keys(Artisan::all());

        self::assertContains('bpadmin:install', $commands);
        self::assertContains('bpadmin:cache', $commands);
        self::assertContains('bpadmin:translations', $commands);
    }

    public function test_console_provider_registers_no_commands_when_not_running_in_console(): void
    {
        $class = self::NS . 'ConsoleServiceProvider';
        self::assertTrue(class_exists($class), "{$class} must exist.");
        self::assertTrue(is_subclass_of($class, ServiceProvider::class));

        $app = new class (base_path()) extends Application {
            public function runningInConsole(): bool
            {
                return false;
            }
        };
        $before = $this->bootstrapperCount();

        $this->bootProvider($class, $app);

        self::assertSame($before, $this->bootstrapperCount());
    }

    public function test_console_provider_registers_commands_when_running_in_console(): void
    {
        $class = self::NS . 'ConsoleServiceProvider';
        self::assertTrue(class_exists($class), "{$class} must exist.");
        self::assertTrue(is_subclass_of($class, ServiceProvider::class));

        $app = new class (base_path()) extends Application {
            public function runningInConsole(): bool
            {
                return true;
            }
        };
        $before = $this->bootstrapperCount();

        $this->bootProvider($class, $app);

        self::assertGreaterThan($before, $this->bootstrapperCount());
        self::assertTrue(class_exists(InstallCommand::class) && class_exists(GenerateTranslationCommand::class) && class_exists(CacheEntitiesCommand::class));
    }

    public function test_field_type_registry_register_accepts_persister_and_query_arguments(): void
    {
        $params = (new ReflectionMethod(FieldTypeRegistry::class, 'register'))->getParameters();

        self::assertCount(4, $params);
        self::assertSame(['type', 'fieldClass', 'persister', 'query'], array_map(static fn(ReflectionParameter $p): string => $p->getName(), $params));
        foreach ([2, 3] as $i) {
            self::assertTrue($params[$i]->isOptional(), "Parameter #{$i} must be optional.");
            $type = $params[$i]->getType();
            self::assertInstanceOf(ReflectionNamedType::class, $type);
            self::assertTrue($type->allowsNull());
        }
    }

    public function test_custom_type_registered_with_persister_and_query_is_persisted_and_queried(): void
    {
        $persister = app()->make(HashedPersister::class);
        $query = app()->make(NotQueryableStrategy::class);
        $field = SlugField::make('slug');

        // Positional on purpose: a 2-arg legacy signature silently drops the extras, so this fails by assertion.
        $this->callRegister(['slug', SlugField::class, $persister, $query]);

        self::assertSame(SlugField::class, app()->make(FieldTypeRegistry::class)->resolve('slug'));
        self::assertSame($persister, app()->make(FieldPersisterRegistry::class)->for($field));
        self::assertSame($query, app()->make(FieldQueryRegistry::class)->for($field));
    }

    public function test_custom_type_registered_without_persister_and_query_keeps_fallbacks(): void
    {
        $field = SlugField::make('slug');
        $persisters = app()->make(FieldPersisterRegistry::class);
        $queries = app()->make(FieldQueryRegistry::class);
        $persisterBefore = $persisters->for($field);
        $queryBefore = $queries->for($field);

        $this->callRegister(['slug', SlugField::class, null, null]);

        self::assertSame($persisterBefore, $persisters->for($field));
        self::assertSame($queryBefore, $queries->for($field));
    }

    /**
     * @param class-string<ServiceProvider> $class
     */
    private function bootProvider(string $class, Application $app): void
    {
        $provider = new $class($app);
        $provider->register();
        (new ReflectionMethod($provider, 'boot'))->invoke($provider);
    }

    /**
     * Reflection keeps the call valid against the legacy 2-arg signature (fails by assertion, not by static analysis).
     *
     * @param list<mixed> $args
     */
    private function callRegister(array $args): void
    {
        (new ReflectionMethod(FieldTypeRegistry::class, 'register'))->invokeArgs(app()->make(FieldTypeRegistry::class), $args);
    }

    private function bootstrapperCount(): int
    {
        $prop = new ReflectionProperty(ConsoleApplication::class, 'bootstrappers');

        return count($prop->getValue());
    }
}
