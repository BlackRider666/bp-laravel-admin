<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Feature\Http;

use BlackParadise\CoreAdmin\Domain\Contracts\Auth\AuthorizationProviderContract;
use BlackParadise\CoreAdmin\Domain\Fields\BelongsToField;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\BootsBPAdmin;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\StubsValueHasher;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

/**
 * A5 — BelongsTo stale FK results in HTTP 422, not 500.
 *
 * Model: BtFkPost belongs to BtFkCategory.
 * EntityDefinition registers category_id as a BelongsToField pointing at BtFkCategory.
 *
 * Pre-IMPL failure mode: UseCaseFactory::createRecord() passes 5 args to the
 * now-6-arg CreateRecordUseCase constructor → ArgumentCountError → HTTP 500,
 * not 422 → test assertion fails → FAIL → RED.
 *
 * Post-IMPL: marker resolved inside LaravelValidationProvider → exists:bt_fk_categories,id
 * → Laravel 422 on missing FK row.
 */
final class BelongsToFkValidationTest extends TestCase
{
    use RefreshDatabase;
    use BootsBPAdmin;
    use StubsValueHasher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stubValueHasher();

        $this->setUpBPAdmin();

        Schema::create('bt_fk_categories', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->timestamps();
        });

        Schema::create('bt_fk_posts', function (Blueprint $t): void {
            $t->id();
            $t->string('title');
            $t->unsignedBigInteger('category_id')->nullable();
            $t->timestamps();
        });

        $registry = resolve(EntityDefinitionRegistry::class);
        $registry->register(new BtFkPostDefinition());

        app()->bind(
            AuthorizationProviderContract::class,
            fn(): AuthorizationProviderContract => new class implements AuthorizationProviderContract {
                public function can(string $action, mixed $subject): bool
                {
                    return true;
                }
            },
        );

        $this->actingAsAdmin();
    }

    /**
     * A5 — POST create with a non-existent category_id (stale FK) must return 422.
     *
     * Pre-IMPL: ArgumentCountError → 500 → assertStatus(422) fails → FAIL → RED.
     */
    public function test_create_with_nonexistent_belongs_to_fk_returns_422(): void
    {
        $response = $this->postJson(
            route('bpadmin.entity.store', ['entity' => 'bt_fk_post']),
            [
                'title'       => 'My Post',
                'category_id' => 99999,
            ],
        );

        $response->assertStatus(422);

        $errors = $response->json('errors');
        self::assertNotNull($errors);
        self::assertArrayHasKey('category_id', $errors);
    }

    /**
     * A5 — POST create with an existing category_id must succeed (201, record in DB).
     *
     * Pre-IMPL: ArgumentCountError → 500 → assertCreated() fails → FAIL → RED.
     */
    public function test_create_with_existing_belongs_to_fk_creates_record(): void
    {
        $category = BtFkCategory::create(['name' => 'Tech']);

        $response = $this->postJson(
            route('bpadmin.entity.store', ['entity' => 'bt_fk_post']),
            [
                'title'       => 'My Post',
                'category_id' => $category->getKey(),
            ],
        );

        $response->assertCreated();
        $this->assertDatabaseHas('bt_fk_posts', ['title' => 'My Post']);
    }
}

// ---------------------------------------------------------------------------
// Inline Eloquent models and EntityDefinition fixture
// ---------------------------------------------------------------------------

final class BtFkCategory extends Model
{
    protected $table   = 'bt_fk_categories';

    protected $guarded = [];
}

final class BtFkPost extends Model
{
    protected $table   = 'bt_fk_posts';

    protected $guarded = [];
}

final class BtFkPostDefinition extends EntityDefinition
{
    public string $model = BtFkPost::class;

    public function resolveName(): string
    {
        return 'bt_fk_post';
    }

    public function fields(): array
    {
        return [
            TextField::make('title')->required(),
            BelongsToField::make('category_id', BtFkCategory::class)->required(),
        ];
    }
}
