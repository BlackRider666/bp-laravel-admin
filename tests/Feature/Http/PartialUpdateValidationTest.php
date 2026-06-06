<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Feature\Http;

use BlackParadise\CoreAdmin\Domain\Contracts\Auth\AuthorizationProviderContract;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\BootsBPAdmin;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\StubsValueHasher;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItem;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Mockery;

/**
 * A7 (B17): Partial update — omitting a required() field that already has a DB value
 * must NOT fail validation.
 *
 * Bug: PATCH /admin/{entity}/{id} with a payload that omits a required() field
 * fails with "required" validation error even though the field already has a value
 * in the database. For partial updates, absent keys should be treated as "no change"
 * (i.e. 'sometimes' semantic), not re-validated as required.
 *
 * Fix: RuleBuilder in 'update' context softens required → sometimes for keys absent
 *      from the payload (presentKeys).
 */
final class PartialUpdateValidationTest extends TestCase
{
    use RefreshDatabase;
    use BootsBPAdmin;
    use StubsValueHasher;

    /**
     * A definition that has two required fields: 'name' and 'email'.
     */
    private function requiredFieldDefinition(): EntityDefinition
    {
        return new class extends EntityDefinition {
            public string $model = TestItem::class;

            public function resolveName(): string
            {
                return 'test_item';
            }

            public function fields(): array
            {
                return [
                    TextField::make('name')->required(),
                    TextField::make('email')->required(),
                ];
            }
        };
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->stubValueHasher(); // must be first — prevents LaravelValueHasher fatal

        $this->setUpBPAdmin();

        Schema::create('test_items', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->string('email');
            $t->timestamps();
        });

        /** @var EntityDefinitionRegistry $registry */
        $registry = $this->app->make(EntityDefinitionRegistry::class);
        $registry->register($this->requiredFieldDefinition());

        $this->app->bind(AuthorizationProviderContract::class, function (): AuthorizationProviderContract {
            $mock = Mockery::mock(AuthorizationProviderContract::class);
            $mock->shouldReceive('can')->andReturn(true);
            return $mock;
        });

        $this->actingAsAdmin();
    }

    // ------------------------------------------------------------------
    // A7 — PATCH omitting a required field passes when field has DB value
    // ------------------------------------------------------------------

    /**
     * Update an existing record by sending only 'name' — 'email' is required but
     * already has a value in the DB and is NOT present in the payload. This must
     * pass validation (HTTP 200), not fail with "email is required".
     *
     * Currently FAILS: the update use case rebuilds full validation rules including
     * required for 'email', which is absent from the payload → 422.
     */
    public function test_patch_omitting_required_field_that_has_db_value_passes_validation(): void
    {
        $item = TestItem::create(['name' => 'Alice', 'email' => 'alice@example.com']);

        $response = $this->putJson(
            route('bpadmin.entity.update', ['entity' => 'test_item', 'id' => $item->id]),
            [
                'name' => 'Alice Updated',
                // 'email' intentionally omitted — already has value in DB
            ],
        );

        $response->assertOk();
        $this->assertDatabaseHas('test_items', [
            'id'   => $item->id,
            'name' => 'Alice Updated',
        ]);
    }

    // ------------------------------------------------------------------
    // A7 — full update (both fields present) still works
    // ------------------------------------------------------------------

    public function test_update_with_all_required_fields_present_passes_validation(): void
    {
        $item = TestItem::create(['name' => 'Bob', 'email' => 'bob@example.com']);

        $response = $this->putJson(
            route('bpadmin.entity.update', ['entity' => 'test_item', 'id' => $item->id]),
            [
                'name'  => 'Bob Updated',
                'email' => 'bob-updated@example.com',
            ],
        );

        $response->assertOk();
    }
}
