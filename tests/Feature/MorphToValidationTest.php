<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Feature;

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
use Mockery;
use stdClass;

/**
 * A morph target model for validation tests — a simple "Page" entity.
 */
final class MorphValidationPage extends Model
{
    protected $table = 'morph_validation_pages';
    protected $guarded = [];
}

/**
 * A comment model for validation tests.
 */
final class MorphValidationComment extends Model
{
    protected $table = 'morph_validation_comments';
    protected $guarded = [];

    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }
}

/**
 * EntityDefinition for MorphValidationComment with a required MorphToField.
 */
final class MorphValidationCommentDefinition extends EntityDefinition
{
    public string $model = MorphValidationComment::class;

    public function resolveName(): string
    {
        return 'morph_validation_comment';
    }

    public function fields(): array
    {
        return [
            TextField::make('body')->required(),
            MorphToField::make('commentable', MorphValidationPage::class)
                ->morphTypes([MorphValidationPage::class => ['label' => 'Page', 'display' => 'title']])
                ->required(),
        ];
    }
}

/**
 * Task C.5 — MorphTo server-side validation: type membership + id existence.
 *
 * A13 — type must be in the allowed morph classes; unknown type → 422.
 * A13 — id must reference an existing row of the chosen type's table; missing → 422.
 * A14 — required morph field with both columns absent → 422 (regression guard).
 * regression — valid type + existing id must pass validation.
 */
final class MorphToValidationTest extends TestCase
{
    use RefreshDatabase;
    use BootsBPAdmin;
    use StubsValueHasher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stubValueHasher();

        $this->setUpBPAdmin();

        Schema::create('morph_validation_pages', function (Blueprint $t): void {
            $t->id();
            $t->string('title');
            $t->timestamps();
        });

        Schema::create('morph_validation_comments', function (Blueprint $t): void {
            $t->id();
            $t->string('body');
            $t->morphs('commentable');
            $t->timestamps();
        });

        /** @var EntityDefinitionRegistry $registry */
        $registry = resolve(EntityDefinitionRegistry::class);
        $registry->register(new MorphValidationCommentDefinition());

        $this->app->bind(AuthorizationProviderContract::class, function (): AuthorizationProviderContract {
            $mock = Mockery::mock(AuthorizationProviderContract::class);
            $mock->shouldReceive('can')->andReturn(true);
            return $mock;
        });

        $this->actingAsAdmin();
    }

    /**
     * A13 — Submitting a type that is NOT in the field's morphTypes must fail with
     * a 422 validation error on commentable_type.
     */
    public function test_disallowed_type_422(): void
    {
        $response = $this->postJson(
            route('bpadmin.entity.store', ['entity' => 'morph_validation_comment']),
            [
                'body'             => 'Hello',
                'commentable_type' => stdClass::class,
                'commentable_id'   => 1,
            ],
        );

        $response->assertStatus(422);
        $errors = $response->json('errors');
        self::assertNotNull($errors);
        self::assertArrayHasKey('commentable_type', $errors);
    }

    /**
     * A13 — Submitting a valid type but a non-existent id must fail with a 422
     * validation error on commentable_id.
     */
    public function test_nonexistent_id_422(): void
    {
        $response = $this->postJson(
            route('bpadmin.entity.store', ['entity' => 'morph_validation_comment']),
            [
                'body'             => 'Hello',
                'commentable_type' => MorphValidationPage::class,
                'commentable_id'   => 999999,
            ],
        );

        $response->assertStatus(422);
        $errors = $response->json('errors');
        self::assertNotNull($errors);
        self::assertArrayHasKey('commentable_id', $errors);
    }

    /**
     * A14 — Omitting both morph columns on a required MorphToField must fail with a
     * 422 validation error on commentable_type (required).
     */
    public function test_required_empty_422(): void
    {
        $response = $this->postJson(
            route('bpadmin.entity.store', ['entity' => 'morph_validation_comment']),
            [
                'body' => 'Hello',
                // commentable_type and commentable_id intentionally omitted
            ],
        );

        $response->assertStatus(422);
        $errors = $response->json('errors');
        self::assertNotNull($errors);
        self::assertArrayHasKey('commentable_type', $errors);
    }

    /**
     * Regression — a valid type + existing id must pass validation and create the record.
     */
    public function test_valid_morph_passes(): void
    {
        $page = MorphValidationPage::create(['title' => 'My Page']);

        $response = $this->postJson(
            route('bpadmin.entity.store', ['entity' => 'morph_validation_comment']),
            [
                'body'             => 'Hello',
                'commentable_type' => MorphValidationPage::class,
                'commentable_id'   => $page->id,
            ],
        );

        $response->assertSessionHasNoErrors();
        $response->assertCreated();
    }
}
