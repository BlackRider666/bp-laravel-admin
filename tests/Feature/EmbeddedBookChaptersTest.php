<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Feature;

use BlackParadise\CoreAdmin\Domain\Contracts\Auth\AuthorizationProviderContract;
use BlackParadise\CoreAdmin\Domain\Fields\BelongsToField;
use BlackParadise\CoreAdmin\Domain\Fields\HasManyField;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\BootsBPAdmin;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\StubsValueHasher;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Mockery;

/**
 * Host model for this test suite.
 */
final class EmbeddedBook extends Model
{
    protected $table = 'embedded_books';
    protected $guarded = [];

    public function chapters(): HasMany
    {
        return $this->hasMany(EmbeddedChapter::class, 'embedded_book_id');
    }
}

/**
 * Child model with a back-FK 'embedded_book_id' pointing to the host.
 */
final class EmbeddedChapter extends Model
{
    protected $table = 'embedded_chapters';
    protected $guarded = [];

    public function book(): BelongsTo
    {
        return $this->belongsTo(EmbeddedBook::class, 'embedded_book_id');
    }
}

/**
 * Chapter definition that explicitly declares the back-FK as a required
 * BelongsToField. This is the problematic shape: without the skip-list fix,
 * submitting chapters without 'embedded_book_id' would fail validation
 * (required rule fires before the host id is known).
 */
final class EmbeddedChapterWithBackFkDefinition extends EntityDefinition
{
    public string $model = EmbeddedChapter::class;

    public function resolveName(): string
    {
        return 'embedded_chapter';
    }

    public function fields(): array
    {
        return [
            TextField::make('title')->required(),
            BelongsToField::make('embedded_book_id', EmbeddedBook::class)->required(),
        ];
    }
}

/**
 * Host definition: hasMany embed whose child definition carries the back-FK.
 */
final class EmbeddedBookWithChaptersDefinition extends EntityDefinition
{
    public string $model = EmbeddedBook::class;

    public function resolveName(): string
    {
        return 'embedded_book';
    }

    public function fields(): array
    {
        return [
            TextField::make('name')->required(),
            HasManyField::make('chapters', EmbeddedChapter::class)
                ->embed(EmbeddedChapterWithBackFkDefinition::class),
        ];
    }
}

/**
 * Bug #4 — embedded hasMany back-FK skip.
 *
 * ResolveEmbeddedRelationsUseCase (core B.1) passes a list<string> $skipFields
 * as the 3rd arg to the $validateRecord closure. The Laravel adapter must
 * forward those field names to LocaleAwareValidationWrapper, which drops them
 * from the rule set before validating the child.
 *
 * Without the fix: POST with embedded chapters that omit 'embedded_book_id'
 * (which is required in the chapter definition) returns HTTP 422 because the
 * validator sees a missing required field — even though the ORM will auto-assign
 * the FK when writing via $host->chapters()->create().
 *
 * With the fix: the skip list removes 'embedded_book_id' from the chapter rules
 * before validation, so the POST succeeds (201) and rows are persisted.
 *
 * A7 — create with children that omit back-FK → 201 + persisted rows.
 * A8 — edit with children that omit back-FK → 200 + persisted rows.
 * A9 — child with genuinely-invalid non-FK required field → 422 with key 'chapters.0.title'.
 */
final class EmbeddedBookChaptersTest extends TestCase
{
    use RefreshDatabase;
    use BootsBPAdmin;
    use StubsValueHasher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stubValueHasher(); // must be first

        $this->setUpBPAdmin();

        Schema::create('embedded_books', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->timestamps();
        });

        Schema::create('embedded_chapters', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('embedded_book_id');
            $t->string('title');
            $t->timestamps();
        });

        /** @var EntityDefinitionRegistry $registry */
        $registry = $this->app->make(EntityDefinitionRegistry::class);
        $registry->register(new EmbeddedBookWithChaptersDefinition());
        $registry->register(new EmbeddedChapterWithBackFkDefinition());

        $this->app->bind(AuthorizationProviderContract::class, function (): AuthorizationProviderContract {
            $mock = Mockery::mock(AuthorizationProviderContract::class);
            $mock->shouldReceive('can')->andReturn(true);
            return $mock;
        });

        $this->actingAsAdmin();
    }

    /**
     * A7 — POST host with children that omit the back-FK.
     * Back-FK is required in the child definition, but must be skipped during
     * child validation because the ORM assigns it automatically.
     */
    public function test_create_with_children_succeeds(): void
    {
        $response = $this->postJson(
            route('bpadmin.entity.store', ['entity' => 'embedded_book']),
            [
                'name'     => 'My Book',
                'chapters' => [
                    ['title' => 'Chapter One'],
                    ['title' => 'Chapter Two'],
                ],
            ],
        );

        $response->assertCreated();

        $book = EmbeddedBook::first();
        $this->assertNotNull($book);
        $this->assertDatabaseHas('embedded_books', ['name' => 'My Book']);
        $this->assertDatabaseCount('embedded_chapters', 2);
        $this->assertDatabaseHas('embedded_chapters', [
            'title'            => 'Chapter One',
            'embedded_book_id' => $book->id,
        ]);
    }

    /**
     * A9 — Child with a genuinely-required non-FK field missing → 422 with
     * prefixed error key 'chapters.0.title'.
     */
    public function test_other_child_error_still_422(): void
    {
        $response = $this->postJson(
            route('bpadmin.entity.store', ['entity' => 'embedded_book']),
            [
                'name'     => 'My Book',
                'chapters' => [
                    ['title' => ''],  // required field empty
                ],
            ],
        );

        $response->assertStatus(422);
        $errors = $response->json('errors');
        $this->assertNotNull($errors, 'Response must contain errors key');
        $this->assertArrayHasKey('chapters.0.title', $errors);
    }
}
