<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Feature\Http;

use BlackParadise\CoreAdmin\Domain\Contracts\Auth\AuthorizationProviderContract;
use BlackParadise\CoreAdmin\Domain\Fields\MorphToField;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\BootsBPAdmin;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\StubsValueHasher;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use stdClass;

/**
 * A6 — MorphTo marker validation results in HTTP 422, not 500.
 *
 * Pins the morph_type_in / morph_exists marker resolution path that Task 1.4
 * must implement inside LaravelValidationProvider.
 *
 * Pre-IMPL failure mode: UseCaseFactory::createRecord() passes 5 args to the
 * now-6-arg CreateRecordUseCase constructor → ArgumentCountError → HTTP 500,
 * not 422 → test assertions fail → FAIL → RED.
 *
 * Post-IMPL: markers resolved inside LaravelValidationProvider →
 *   morph_type_in:<FQCN> → in:alias,FQCN
 *   morph_exists         → closure checking row existence
 * → Laravel 422 for invalid type or non-existent id.
 */
final class MorphToMarkerValidationTest extends TestCase
{
    use RefreshDatabase;
    use BootsBPAdmin;
    use StubsValueHasher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stubValueHasher();

        $this->setUpBPAdmin();

        Schema::create('morph_marker_pages', function (Blueprint $t): void {
            $t->id();
            $t->string('title');
            $t->timestamps();
        });

        Schema::create('morph_marker_notes', function (Blueprint $t): void {
            $t->id();
            $t->string('body');
            $t->morphs('notable');
            $t->timestamps();
        });

        $registry = resolve(EntityDefinitionRegistry::class);
        $registry->register(new MorphMarkerNoteDefinition());

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
     * A6 — Submitting a morph type NOT in the morphTypes allowlist must return 422.
     *
     * morph_type_in:<FQCN> marker must resolve to an in: rule that rejects stdClass.
     *
     * Pre-IMPL: ArgumentCountError → 500 → assertStatus(422) fails → FAIL → RED.
     */
    public function test_create_with_invalid_morph_type_returns_422(): void
    {
        $response = $this->postJson(
            route('bpadmin.entity.store', ['entity' => 'morph_marker_note']),
            [
                'body'         => 'A note',
                'notable_type' => stdClass::class,
                'notable_id'   => 1,
            ],
        );

        $response->assertStatus(422);

        $errors = $response->json('errors');
        self::assertNotNull($errors);
        self::assertArrayHasKey('notable_type', $errors);
    }

    /**
     * A6 — Submitting a valid morph type but a non-existent id must return 422.
     *
     * morph_exists marker must resolve to a closure that verifies row existence.
     *
     * Pre-IMPL: ArgumentCountError → 500 → assertStatus(422) fails → FAIL → RED.
     */
    public function test_create_with_nonexistent_morph_id_returns_422(): void
    {
        $response = $this->postJson(
            route('bpadmin.entity.store', ['entity' => 'morph_marker_note']),
            [
                'body'         => 'A note',
                'notable_type' => MorphMarkerPage::class,
                'notable_id'   => 99999,
            ],
        );

        $response->assertStatus(422);

        $errors = $response->json('errors');
        self::assertNotNull($errors);
        self::assertArrayHasKey('notable_id', $errors);
    }

    /**
     * A6 — Valid morph type + existing id must succeed (201, record persisted).
     *
     * Pre-IMPL: ArgumentCountError → 500 → assertCreated() fails → FAIL → RED.
     */
    public function test_create_with_valid_morph_type_and_existing_id_creates_record(): void
    {
        $page = MorphMarkerPage::create(['title' => 'My Page']);

        $response = $this->postJson(
            route('bpadmin.entity.store', ['entity' => 'morph_marker_note']),
            [
                'body'         => 'A note',
                'notable_type' => MorphMarkerPage::class,
                'notable_id'   => $page->getKey(),
            ],
        );

        $response->assertCreated();
        $this->assertDatabaseHas('morph_marker_notes', ['body' => 'A note']);
    }
}

// ---------------------------------------------------------------------------
// Inline Eloquent models and EntityDefinition fixture
// ---------------------------------------------------------------------------

final class MorphMarkerPage extends Model
{
    protected $table   = 'morph_marker_pages';

    protected $guarded = [];
}

final class MorphMarkerNote extends Model
{
    protected $table   = 'morph_marker_notes';

    protected $guarded = [];

    public function notable(): MorphTo
    {
        return $this->morphTo();
    }
}

final class MorphMarkerNoteDefinition extends EntityDefinition
{
    public string $model = MorphMarkerNote::class;

    public function resolveName(): string
    {
        return 'morph_marker_note';
    }

    public function fields(): array
    {
        return [
            TextField::make('body')->required(),
            MorphToField::make('notable', MorphMarkerPage::class)
                ->morphTypes([MorphMarkerPage::class => ['label' => 'Page', 'display' => 'title']])
                ->required(),
        ];
    }
}
