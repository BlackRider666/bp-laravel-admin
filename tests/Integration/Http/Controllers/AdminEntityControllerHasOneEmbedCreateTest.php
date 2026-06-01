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
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Mockery;

/**
 * Embedded definition for TestProfile that DOES NOT declare the FK column
 * (test_item_id) as a field. This is the realistic shape of an embedded
 * definition — the back-reference FK is an infrastructure detail the admin
 * author should not have to expose. The writer must inject the FK itself.
 */
final class TestProfileNoFkDefinition extends EntityDefinition
{
    public string $model = TestProfile::class;

    public function resolveName(): string
    {
        return 'test_profile_no_fk';
    }

    public function fields(): array
    {
        return [
            TextField::make('bio'),
        ];
    }
}

/**
 * Host definition with a hasOne-embedded profile whose embedded definition
 * omits the FK field.
 */
final class TestItemWithEmbeddedProfileNoFkDefinition extends EntityDefinition
{
    public string $model = TestItem::class;

    public function resolveName(): string
    {
        return 'test_item_with_profile_no_fk';
    }

    public function fields(): array
    {
        return [
            TextField::make('name'),
            HasOneField::make('profile', TestProfile::class)
                ->embed(TestProfileNoFkDefinition::class),
        ];
    }
}

/**
 * Regression test for ADMIN-V3-BUGS #2: hasOne-embed CREATE.
 *
 * When the embedded definition does not declare the FK column as a field,
 * filterAttributes used to strip the writer-injected FK, breaking the child
 * insert (NOT NULL violation). The writer must persist the child through the
 * host relation so Eloquent assigns the FK automatically.
 */
final class AdminEntityControllerHasOneEmbedCreateTest extends TestCase
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

        // Creates test_profiles (test_item_id is NOT NULL) among others.
        $this->setUpRelationFixtures();

        /** @var EntityDefinitionRegistry $registry */
        $registry = $this->app->make(EntityDefinitionRegistry::class);
        $registry->register(new TestItemWithEmbeddedProfileNoFkDefinition());
        $registry->register(new TestProfileNoFkDefinition());

        $this->app->bind(AuthorizationProviderContract::class, function (): AuthorizationProviderContract {
            $mock = Mockery::mock(AuthorizationProviderContract::class);
            $mock->shouldReceive('can')->andReturn(true);
            return $mock;
        });

        $this->actingAsAdmin();
    }

    public function test_store_creates_has_one_embedded_child_with_injected_fk(): void
    {
        $response = $this->postJson(
            route('bpadmin.entity.store', ['entity' => 'test_item_with_profile_no_fk']),
            [
                'name'    => 'Alice',
                'profile' => ['bio' => 'first bio'],
            ],
        );

        $response->assertCreated();

        $item = TestItem::first();
        $this->assertNotNull($item);

        // Child created with the correct FK even though the embedded definition
        // never declared the FK column as a field.
        $this->assertDatabaseHas('test_profiles', [
            'test_item_id' => $item->id,
            'bio'          => 'first bio',
        ]);
        $this->assertDatabaseCount('test_profiles', 1);
    }
}
