<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Concerns;

use BlackParadise\CoreAdmin\Domain\Contracts\Auth\AuthorizationProviderContract;
use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItemDefinition;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Schema;
use Mockery;

/**
 * Shared test infrastructure for integration tests that exercise entity CRUD flows.
 *
 * Provides:
 *   - A `test_items` table via in-memory SQLite migration
 *   - A `users` table for authentication
 *   - Registration of TestItemDefinition in the EntityDefinitionRegistry
 *   - A permissive AuthorizationProviderContract mock (always returns true)
 *   - A helper to create and authenticate a User via actingAs()
 */
trait CreatesTestItemTable
{
    /**
     * Create the `test_items` and `users` tables, register the entity definition,
     * and bind a permissive authorization mock.
     *
     * Must be called from setUp() in each test class.
     */
    protected function setUpTestItemFixtures(): void
    {
        Schema::create('test_items', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        /** @var EntityDefinitionRegistry $registry */
        $registry = $this->app->make(EntityDefinitionRegistry::class);
        $registry->register(new TestItemDefinition());

        // Bind a permissive authorization provider so all use-case auth checks pass.
        $this->app->bind(AuthorizationProviderContract::class, function (): AuthorizationProviderContract {
            $mock = Mockery::mock(AuthorizationProviderContract::class);
            $mock->shouldReceive('can')->andReturn(true);
            return $mock;
        });
    }

    /**
     * Create a minimal User model, persist it, and authenticate the test request.
     */
    protected function loginAsUser(): User
    {
        $user = new User();
        $user->name = 'Test Admin';
        $user->email = 'admin@example.com';
        $user->password = bcrypt('password');
        $user->save();

        $this->actingAs($user);

        return $user;
    }
}
