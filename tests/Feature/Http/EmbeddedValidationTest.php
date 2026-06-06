<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Feature\Http;

use BlackParadise\CoreAdmin\Domain\Contracts\Auth\AuthorizationProviderContract;
use BlackParadise\CoreAdmin\Domain\Fields\BelongsToField;
use BlackParadise\CoreAdmin\Domain\Fields\HasManyField;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\BootsBPAdmin;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\StubsValueHasher;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestAuthor;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestAuthorDefinition;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Mockery;

/**
 * Fixture: HasMany child with a required 'body' field.
 */
final class TestChapter extends Model
{
    protected $table = 'test_chapters';
    protected $guarded = [];
}

final class TestBook extends Model
{
    protected $table = 'test_books';
    protected $guarded = [];

    public function chapters(): HasMany
    {
        return $this->hasMany(TestChapter::class, 'book_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(TestAuthor::class, 'author_id');
    }
}

/**
 * Chapter definition: 'body' is required.
 */
final class TestChapterDefinition extends EntityDefinition
{
    public string $model = TestChapter::class;

    public function resolveName(): string
    {
        return 'test_chapter';
    }

    public function fields(): array
    {
        return [
            TextField::make('body')->required(),
        ];
    }
}

/**
 * Book definition: embeds a hasMany chapters + a belongsTo author (also embedded).
 */
final class TestBookWithChaptersDefinition extends EntityDefinition
{
    public string $model = TestBook::class;

    public function resolveName(): string
    {
        return 'test_book';
    }

    public function fields(): array
    {
        return [
            TextField::make('title'),
            HasManyField::make('chapters', TestChapter::class)
                ->embed(TestChapterDefinition::class),
            BelongsToField::make('author_id', TestAuthor::class)
                ->embed(TestAuthorDefinition::class),
        ];
    }
}

/**
 * A5 (B21a) / wiring: EmbeddedValidation HTTP feature tests.
 *
 * Bug: POST with an invalid hasMany child results in HTTP 500 (SQLSTATE 23000 NOT NULL)
 *      rather than HTTP 422 with field-level errors.
 *
 * Bug: POST with an invalid embedded belongsTo results in HTTP 500 or silent 302
 *      rather than HTTP 422 with '<fk>.<childField>' error keys.
 *
 * Fix: UseCaseFactory::resolveEmbeddedRelations() must wire the $validateRecord
 *      closure so children are validated BEFORE host persistence — turning
 *      potential DB constraint errors into proper validation 422s.
 */
final class EmbeddedValidationTest extends TestCase
{
    use RefreshDatabase;
    use BootsBPAdmin;
    use StubsValueHasher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stubValueHasher(); // must be first — prevents LaravelValueHasher fatal

        $this->setUpBPAdmin();

        // test_books table
        Schema::create('test_books', function (Blueprint $t): void {
            $t->id();
            $t->string('title');
            $t->unsignedBigInteger('author_id')->nullable();
            $t->timestamps();
        });

        // test_chapters: 'body' is NOT NULL to force failure when empty
        Schema::create('test_chapters', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('book_id');
            $t->string('body'); // NOT NULL — empty body → DB constraint
            $t->timestamps();
        });

        // test_authors table (used by belongsTo embed test)
        Schema::create('test_authors', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->string('email');
            $t->timestamps();
        });

        /** @var EntityDefinitionRegistry $registry */
        $registry = $this->app->make(EntityDefinitionRegistry::class);
        $registry->register(new TestBookWithChaptersDefinition());
        $registry->register(new TestChapterDefinition());
        $registry->register(new TestAuthorDefinition());

        $this->app->bind(AuthorizationProviderContract::class, function (): AuthorizationProviderContract {
            $mock = Mockery::mock(AuthorizationProviderContract::class);
            $mock->shouldReceive('can')->andReturn(true);
            return $mock;
        });

        $this->actingAsAdmin();
    }

    // ------------------------------------------------------------------
    // A5 — hasMany child with missing required field → HTTP 422, not 500
    // ------------------------------------------------------------------

    /**
     * POST host with an embedded hasMany child that has an empty required 'body' field
     * must return HTTP 422 (not 500), and the errors must contain the key
     * 'chapters.0.body'.
     *
     * Currently FAILS: the empty string bypasses validation, the DB insert
     * fails with a NOT NULL constraint (SQLite doesn't enforce NOT NULL on
     * empty string), or the validation isn't wired at all → 500.
     *
     * With the fix (validateRecord wired in UseCaseFactory), the child is
     * validated before any DB write and returns 422 with proper keys.
     */
    public function test_store_with_hasMany_child_missing_required_field_returns_422(): void
    {
        $response = $this->postJson(
            route('bpadmin.entity.store', ['entity' => 'test_book']),
            [
                'title'    => 'My Book',
                'chapters' => [
                    ['body' => ''],  // empty required field
                ],
            ],
        );

        $response->assertStatus(422);
        $errors = $response->json('errors');
        $this->assertNotNull($errors, 'Response must contain errors key');
        $this->assertArrayHasKey('chapters.0.body', $errors);
    }

    // ------------------------------------------------------------------
    // A5 — hasMany child with valid data → HTTP 201 (happy path)
    // ------------------------------------------------------------------

    public function test_store_with_valid_hasMany_children_returns_201(): void
    {
        $response = $this->postJson(
            route('bpadmin.entity.store', ['entity' => 'test_book']),
            [
                'title'    => 'Good Book',
                'chapters' => [
                    ['body' => 'First chapter content'],
                    ['body' => 'Second chapter content'],
                ],
            ],
        );

        $response->assertStatus(201);
        $this->assertDatabaseHas('test_books', ['title' => 'Good Book']);
        $this->assertDatabaseCount('test_chapters', 2);
    }

    // ------------------------------------------------------------------
    // A5 — belongsTo embed child with missing required field → 422 with fk.field key
    // ------------------------------------------------------------------

    /**
     * POST with an embedded belongsTo child that has empty required fields must
     * return HTTP 422 with error key '<fk>.<childField>' (e.g. 'author_id.name').
     *
     * Currently FAILS: either 500 or silent success with null FK.
     */
    public function test_store_with_belongsTo_embed_missing_required_field_returns_422_with_fk_key(): void
    {
        $response = $this->postJson(
            route('bpadmin.entity.store', ['entity' => 'test_book']),
            [
                'title'     => 'Book With Bad Author',
                'author_id' => [
                    'name'  => '',      // required field missing
                    'email' => 'a@b.c',
                ],
            ],
        );

        $response->assertStatus(422);
        $errors = $response->json('errors');
        $this->assertNotNull($errors);
        $this->assertArrayHasKey('author_id.name', $errors);
    }
}
