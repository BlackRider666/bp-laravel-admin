<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Http\Controllers;

use BlackParadise\CoreAdmin\Domain\Contracts\Auth\AuthorizationProviderContract;
use BlackParadise\CoreAdmin\Domain\Fields\HasOneField;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\BootsBPAdmin;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\CreatesRelationFixtures;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItem;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestProfile;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestProfileDefinition;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Mockery;

/**
 * EntityDefinition for TestItem with a hasOne-embedded profile.
 * Registers as 'test_item_with_profile' to avoid name collisions.
 */
final class TestItemWithEmbeddedProfileDefinition extends EntityDefinition
{
    public string $model = TestItem::class;

    public function resolveName(): string
    {
        return 'test_item_with_profile';
    }

    public function fields(): array
    {
        return [
            TextField::make('name'),
            HasOneField::make('profile', TestProfile::class)
                ->embed(TestProfileDefinition::class),
        ];
    }
}

/**
 * Integration tests for PUT /admin/{entity}/{id} when the entity has a
 * hasOne-embedded relation.
 *
 * Covers the bug fix where a hasOne embed with no existing child record was
 * silently dropped instead of being deferred and created after the host update.
 */
final class AdminEntityControllerHasOneEmbedUpdateTest extends TestCase
{
    use RefreshDatabase;
    use BootsBPAdmin;
    use CreatesRelationFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpBPAdmin();

        Schema::create('test_items', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->string('email')->nullable();
            $t->timestamps();
        });

        // Creates test_profiles (with test_item_id + bio) among others.
        $this->setUpRelationFixtures();

        /** @var EntityDefinitionRegistry $registry */
        $registry = $this->app->make(EntityDefinitionRegistry::class);
        $registry->register(new TestItemWithEmbeddedProfileDefinition());
        $registry->register(new TestProfileDefinition());

        $this->app->bind(AuthorizationProviderContract::class, function (): AuthorizationProviderContract {
            $mock = Mockery::mock(AuthorizationProviderContract::class);
            $mock->shouldReceive('can')->andReturn(true);
            return $mock;
        });

        $this->actingAsAdmin();
    }

    /**
     * Core regression test for the bug: PUT with a hasOne embed payload when the
     * host has NO existing child must create the child, not silently discard it.
     */
    public function test_update_creates_new_has_one_embedded_child_when_none_exists(): void
    {
        $item = TestItem::create(['name' => 'Alice', 'email' => 'alice@example.com']);

        // Precondition: no profile exists yet.
        $this->assertDatabaseEmpty('test_profiles');

        $response = $this->putJson(
            route('bpadmin.entity.update', ['entity' => 'test_item_with_profile', 'id' => $item->id]),
            [
                'name'    => 'Alice Updated',
                'profile' => ['bio' => 'first bio'],
            ],
        );

        $response->assertOk();

        // Host was updated.
        $this->assertDatabaseHas('test_items', ['id' => $item->id, 'name' => 'Alice Updated']);

        // Child was created with the correct FK and payload.
        $this->assertDatabaseHas('test_profiles', [
            'test_item_id' => $item->id,
            'bio'          => 'first bio',
        ]);
        $this->assertDatabaseCount('test_profiles', 1);
    }

    /**
     * Existing-child branch: PUT with a hasOne embed payload when the host ALREADY
     * has a child must update it in place (regression guard).
     */
    public function test_update_updates_existing_has_one_embedded_child(): void
    {
        $item    = TestItem::create(['name' => 'Bob', 'email' => 'bob@example.com']);
        $profile = TestProfile::create(['test_item_id' => $item->id, 'bio' => 'old bio']);

        $response = $this->putJson(
            route('bpadmin.entity.update', ['entity' => 'test_item_with_profile', 'id' => $item->id]),
            [
                'name'    => 'Bob Updated',
                'profile' => ['bio' => 'new bio'],
            ],
        );

        $response->assertOk();

        // Host was updated.
        $this->assertDatabaseHas('test_items', ['id' => $item->id, 'name' => 'Bob Updated']);

        // Child was updated in place — same id, new bio.
        $this->assertDatabaseHas('test_profiles', ['id' => $profile->id, 'bio' => 'new bio']);
        $this->assertDatabaseCount('test_profiles', 1);
    }
}
