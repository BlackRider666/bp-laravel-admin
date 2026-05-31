<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration;

use BlackParadise\CoreAdmin\Domain\Contracts\Auth\AuthenticationProviderContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Auth\AuthorizationProviderContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Entity\RelationOptionsProviderContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Events\EventDispatcherContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Files\FileStorageProviderContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Validation\ValidationProviderContract;
use BlackParadise\CoreAdmin\Domain\Mutators\EntityMutatorInterface;
use BlackParadise\CoreAdmin\Domain\Repositories\EntityRepositoryInterface;
use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use BlackParadise\LaravelAdmin\Core\FieldTypeRegistry;
use BlackParadise\LaravelAdmin\Http\Presenters\AuthPresenterInterface;
use BlackParadise\LaravelAdmin\Http\Presenters\DashboardPresenterInterface;
use BlackParadise\LaravelAdmin\Http\Presenters\EntityPresenterInterface;
use BlackParadise\LaravelAdmin\Http\Presenters\Json\JsonAuthPresenter;
use BlackParadise\LaravelAdmin\Http\Presenters\Json\JsonDashboardPresenter;
use BlackParadise\LaravelAdmin\Http\Presenters\Json\JsonEntityPresenter;
use BlackParadise\LaravelAdmin\Infrastructure\Auth\LaravelAuthorizationProvider;
use BlackParadise\LaravelAdmin\Infrastructure\Auth\LaravelAuthProvider;
use BlackParadise\LaravelAdmin\Infrastructure\Events\LaravelEventDispatcher;
use BlackParadise\LaravelAdmin\Infrastructure\Files\LaravelFileStorage;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\EloquentEntityMutator;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\EloquentEntityRepository;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\EloquentRelationOptionsProvider;
use BlackParadise\LaravelAdmin\Infrastructure\Validation\LaravelValidationProvider;
use BlackParadise\LaravelAdmin\Tests\TestCase;

/**
 * Integration tests for DashboardServiceProvider.
 *
 * Verifies that all contracts are bound to the correct concrete implementations,
 * singletons are singletons, and default field types are registered.
 */
final class DashboardServiceProviderTest extends TestCase
{
    // ------------------------------------------------------------------
    // Core contract bindings
    // ------------------------------------------------------------------

    public function test_entity_repository_resolves_to_eloquent_implementation(): void
    {
        $this->assertInstanceOf(
            EloquentEntityRepository::class,
            $this->app->make(EntityRepositoryInterface::class),
        );
    }

    public function test_entity_mutator_resolves_to_eloquent_implementation(): void
    {
        $this->assertInstanceOf(
            EloquentEntityMutator::class,
            $this->app->make(EntityMutatorInterface::class),
        );
    }

    public function test_authentication_provider_resolves_to_laravel_implementation(): void
    {
        $this->assertInstanceOf(
            LaravelAuthProvider::class,
            $this->app->make(AuthenticationProviderContract::class),
        );
    }

    public function test_authorization_provider_resolves_to_laravel_implementation(): void
    {
        $this->assertInstanceOf(
            LaravelAuthorizationProvider::class,
            $this->app->make(AuthorizationProviderContract::class),
        );
    }

    public function test_validation_provider_resolves_to_laravel_implementation(): void
    {
        $this->assertInstanceOf(
            LaravelValidationProvider::class,
            $this->app->make(ValidationProviderContract::class),
        );
    }

    public function test_event_dispatcher_resolves_to_laravel_implementation(): void
    {
        $this->assertInstanceOf(
            LaravelEventDispatcher::class,
            $this->app->make(EventDispatcherContract::class),
        );
    }

    public function test_file_storage_resolves_to_laravel_implementation(): void
    {
        $this->assertInstanceOf(
            LaravelFileStorage::class,
            $this->app->make(FileStorageProviderContract::class),
        );
    }

    public function test_relation_options_provider_resolves_to_eloquent_implementation(): void
    {
        $this->assertInstanceOf(
            EloquentRelationOptionsProvider::class,
            $this->app->make(RelationOptionsProviderContract::class),
        );
    }

    public function test_relation_options_provider_is_a_singleton(): void
    {
        $first  = $this->app->make(RelationOptionsProviderContract::class);
        $second = $this->app->make(RelationOptionsProviderContract::class);

        $this->assertSame($first, $second);
    }

    // ------------------------------------------------------------------
    // Default presenter bindings (JSON / API mode)
    // ------------------------------------------------------------------

    public function test_entity_presenter_defaults_to_json_presenter(): void
    {
        $this->assertInstanceOf(
            JsonEntityPresenter::class,
            $this->app->make(EntityPresenterInterface::class),
        );
    }

    public function test_auth_presenter_defaults_to_json_presenter(): void
    {
        $this->assertInstanceOf(
            JsonAuthPresenter::class,
            $this->app->make(AuthPresenterInterface::class),
        );
    }

    public function test_dashboard_presenter_defaults_to_json_presenter(): void
    {
        $this->assertInstanceOf(
            JsonDashboardPresenter::class,
            $this->app->make(DashboardPresenterInterface::class),
        );
    }

    // ------------------------------------------------------------------
    // Singleton behaviour
    // ------------------------------------------------------------------

    public function test_entity_definition_registry_is_a_singleton(): void
    {
        $first  = $this->app->make(EntityDefinitionRegistry::class);
        $second = $this->app->make(EntityDefinitionRegistry::class);

        $this->assertSame($first, $second);
    }

    public function test_field_type_registry_is_a_singleton(): void
    {
        $first  = $this->app->make(FieldTypeRegistry::class);
        $second = $this->app->make(FieldTypeRegistry::class);

        $this->assertSame($first, $second);
    }

    // ------------------------------------------------------------------
    // Field type registry — default registrations
    // ------------------------------------------------------------------

    public function test_field_type_registry_has_text_type_registered(): void
    {
        $registry = $this->app->make(FieldTypeRegistry::class);

        $this->assertTrue($registry->has('text'));
    }

    public function test_field_type_registry_has_number_type_registered(): void
    {
        $registry = $this->app->make(FieldTypeRegistry::class);

        $this->assertTrue($registry->has('number'));
    }

    public function test_field_type_registry_has_boolean_type_registered(): void
    {
        $registry = $this->app->make(FieldTypeRegistry::class);

        $this->assertTrue($registry->has('boolean'));
    }

    public function test_field_type_registry_has_datetime_type_registered(): void
    {
        $registry = $this->app->make(FieldTypeRegistry::class);

        $this->assertTrue($registry->has('datetime'));
    }

    public function test_field_type_registry_has_email_type_registered(): void
    {
        $registry = $this->app->make(FieldTypeRegistry::class);

        $this->assertTrue($registry->has('email'));
    }

    // ------------------------------------------------------------------
    // Entity auto-discovery — empty paths (configured in TestCase)
    // ------------------------------------------------------------------

    public function test_entity_registry_is_empty_when_discovery_paths_is_empty(): void
    {
        $registry = $this->app->make(EntityDefinitionRegistry::class);

        $this->assertSame(0, $registry->count());
    }
}
