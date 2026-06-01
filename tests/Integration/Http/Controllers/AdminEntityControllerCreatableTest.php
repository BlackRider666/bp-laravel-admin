<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Http\Controllers;

use BlackParadise\CoreAdmin\Domain\Contracts\Auth\AuthorizationProviderContract;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\BootsBPAdmin;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItem;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Mockery;

/** Default behaviour: creatable. */
final class CreatableItemDefinition extends EntityDefinition
{
    public string $model = TestItem::class;

    public function resolveName(): string
    {
        return 'creatable_item';
    }

    public function fields(): array
    {
        return [TextField::make('name')];
    }
}

/** Embed-only supertype: must not be creatable standalone. */
final class NonCreatableItemDefinition extends EntityDefinition
{
    public bool $creatable = false;

    public string $model = TestItem::class;

    public function resolveName(): string
    {
        return 'embed_only_item';
    }

    public function fields(): array
    {
        return [TextField::make('name')];
    }
}

/**
 * Regression test for ADMIN-V3-BUGS #5: embed-only supertypes (Publications,
 * PublicEvents) stay registered for embedding but must not be creatable
 * standalone — a direct create used to 500 on an empty discriminator. A
 * not-creatable definition must 404 its create form and store endpoint.
 */
final class AdminEntityControllerCreatableTest extends TestCase
{
    use RefreshDatabase;
    use BootsBPAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpBPAdmin();

        Schema::create('test_items', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->timestamps();
        });

        /** @var EntityDefinitionRegistry $registry */
        $registry = $this->app->make(EntityDefinitionRegistry::class);
        $registry->register(new CreatableItemDefinition());
        $registry->register(new NonCreatableItemDefinition());

        $this->app->bind(AuthorizationProviderContract::class, function (): AuthorizationProviderContract {
            $mock = Mockery::mock(AuthorizationProviderContract::class);
            $mock->shouldReceive('can')->andReturn(true);
            return $mock;
        });

        $this->actingAsAdmin();
    }

    public function test_store_on_non_creatable_entity_returns_not_found(): void
    {
        $response = $this->postJson(
            route('bpadmin.entity.store', ['entity' => 'embed_only_item']),
            ['name' => 'Should not persist'],
        );

        $response->assertNotFound();
        $this->assertDatabaseCount('test_items', 0);
    }

    public function test_create_form_on_non_creatable_entity_returns_not_found(): void
    {
        $response = $this->getJson(
            route('bpadmin.entity.create', ['entity' => 'embed_only_item']),
        );

        $response->assertNotFound();
    }

    public function test_store_on_creatable_entity_still_succeeds(): void
    {
        $response = $this->postJson(
            route('bpadmin.entity.store', ['entity' => 'creatable_item']),
            ['name' => 'Persisted'],
        );

        $response->assertCreated();
        $this->assertDatabaseHas('test_items', ['name' => 'Persisted']);
    }
}
