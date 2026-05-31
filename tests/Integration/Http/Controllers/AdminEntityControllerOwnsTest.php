<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Http\Controllers;

use BlackParadise\CoreAdmin\Application\UseCases\Entity\ResolveEmbeddedRelationsUseCase;
use BlackParadise\CoreAdmin\Domain\Contracts\Auth\AuthorizationProviderContract;
use BlackParadise\CoreAdmin\Domain\Fields\BelongsToField;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\BootsBPAdmin;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestAuthor;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestAuthorDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestPost;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Mockery;

/**
 * OwnedAuthorPostsDefinition: TestPost with owned+embedded author.
 * This definition is local to this test file and registers as 'owned_test_post'
 * to avoid colliding with the 'test_post' definition used in EmbedTest.
 */
final class OwnedAuthorPostsDefinition extends EntityDefinition
{
    public string $model = TestPost::class;

    public function resolveName(): string
    {
        return 'owned_test_post';
    }

    public function fields(): array
    {
        return [
            TextField::make('title'),
            BelongsToField::make('author_id', TestAuthor::class)
                ->embed(TestAuthorDefinition::class)
                ->owns(),
        ];
    }
}

final class AdminEntityControllerOwnsTest extends TestCase
{
    use RefreshDatabase;
    use BootsBPAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpBPAdmin();

        Schema::create('test_authors', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->string('email');
            $t->timestamps();
        });
        Schema::create('test_posts', function (Blueprint $t): void {
            $t->id();
            $t->string('title');
            $t->unsignedBigInteger('author_id')->nullable();
            $t->timestamps();
        });

        /** @var EntityDefinitionRegistry $registry */
        $registry = $this->app->make(EntityDefinitionRegistry::class);
        $registry->register(new OwnedAuthorPostsDefinition());
        $registry->register(new TestAuthorDefinition());

        $this->app->bind(AuthorizationProviderContract::class, function (): AuthorizationProviderContract {
            $mock = Mockery::mock(AuthorizationProviderContract::class);
            $mock->shouldReceive('can')->andReturn(true);
            return $mock;
        });

        $this->actingAsAdmin();
    }

    // -------------------------------------------------------------------------
    // UPDATE: reject scalar FK write on owned relation
    // -------------------------------------------------------------------------

    public function test_update_rejects_scalar_fk_write_on_owned_relation(): void
    {
        $originalAuthor = TestAuthor::create(['name' => 'Original', 'email' => 'orig@e.com']);
        $otherAuthor    = TestAuthor::create(['name' => 'Other',    'email' => 'other@e.com']);
        $post           = TestPost::create(['title' => 'My Post', 'author_id' => $originalAuthor->id]);

        $response = $this->putJson(
            route('bpadmin.entity.update', ['entity' => 'owned_test_post', 'id' => $post->id]),
            [
                'title'     => 'My Post',
                'author_id' => $otherAuthor->id,  // scalar FK — must be rejected
            ],
        );

        $response->assertStatus(422);
        $errors = $response->json('errors');
        $this->assertArrayHasKey('author_id', $errors);

        // The user-visible error must NOT contain the raw i18n: sentinel —
        // translateErrorKeys() must have stripped it and called __() on the key.
        foreach ($errors['author_id'] as $msg) {
            $this->assertStringNotContainsString(
                ResolveEmbeddedRelationsUseCase::I18N_SENTINEL,
                $msg,
                'Raw sentinel must not appear in the response — controller must translate it.',
            );
        }

        // FK must remain unchanged in DB
        $this->assertDatabaseHas('test_posts', [
            'id'        => $post->id,
            'author_id' => $originalAuthor->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // UPDATE: nested embed payload on owned relation — should succeed
    // -------------------------------------------------------------------------

    public function test_update_accepts_nested_embed_payload_on_owned_relation(): void
    {
        $author = TestAuthor::create(['name' => 'InitName', 'email' => 'init@e.com']);
        $post   = TestPost::create(['title' => 'Original Title', 'author_id' => $author->id]);

        $response = $this->putJson(
            route('bpadmin.entity.update', ['entity' => 'owned_test_post', 'id' => $post->id]),
            [
                'title'     => 'Updated Title',
                'author_id' => ['name' => 'New Name', 'email' => 'new@e.com'],  // nested array
            ],
        );

        $response->assertOk();

        $this->assertDatabaseHas('test_posts', ['id' => $post->id, 'title' => 'Updated Title']);
        $this->assertDatabaseHas('test_authors', ['id' => $author->id, 'name' => 'New Name']);
    }

    // -------------------------------------------------------------------------
    // STORE: reject scalar FK write on owned relation
    // -------------------------------------------------------------------------

    public function test_store_rejects_scalar_fk_write_on_owned_relation(): void
    {
        $existingAuthor = TestAuthor::create(['name' => 'Existing', 'email' => 'ex@e.com']);

        $response = $this->postJson(
            route('bpadmin.entity.store', ['entity' => 'owned_test_post']),
            [
                'title'     => 'New Post',
                'author_id' => $existingAuthor->id,  // scalar FK — must be rejected
            ],
        );

        $response->assertStatus(422);
        $errors = $response->json('errors');
        $this->assertArrayHasKey('author_id', $errors);

        // The user-visible error must NOT contain the raw i18n: sentinel.
        foreach ($errors['author_id'] as $msg) {
            $this->assertStringNotContainsString(
                ResolveEmbeddedRelationsUseCase::I18N_SENTINEL,
                $msg,
                'Raw sentinel must not appear in the response — controller must translate it.',
            );
        }

        // No post should have been created
        $this->assertDatabaseEmpty('test_posts');
    }

    // -------------------------------------------------------------------------
    // BULK DESTROY: cascade owned+embedded relations
    // -------------------------------------------------------------------------

    public function test_bulk_destroy_cascades_owned_embedded_records(): void
    {
        $author1 = TestAuthor::create(['name' => 'Author One', 'email' => 'a1@e.com']);
        $author2 = TestAuthor::create(['name' => 'Author Two', 'email' => 'a2@e.com']);
        $post1   = TestPost::create(['title' => 'Post One', 'author_id' => $author1->id]);
        $post2   = TestPost::create(['title' => 'Post Two', 'author_id' => $author2->id]);

        $response = $this->post(
            route('bpadmin.entity.bulk-destroy', ['entity' => 'owned_test_post']),
            ['ids' => [$post1->id, $post2->id]],
        );

        // JsonEntityPresenter (default in tests) returns a 200 JSON envelope with
        // the per-id outcome breakdown; BladeEntityPresenter would redirect.
        $response->assertOk()
            ->assertJson(['deleted' => 2, 'failed_ids' => [], 'not_found_ids' => []]);

        $this->assertDatabaseEmpty('test_posts');
        $this->assertDatabaseEmpty('test_authors');
    }

    public function test_bulk_destroy_does_not_touch_unrelated_owned_records(): void
    {
        $author1 = TestAuthor::create(['name' => 'Author One', 'email' => 'a1@e.com']);
        $author2 = TestAuthor::create(['name' => 'Author Two', 'email' => 'a2@e.com']);
        $orphan  = TestAuthor::create(['name' => 'Orphan',     'email' => 'orphan@e.com']);

        $post1 = TestPost::create(['title' => 'Post One', 'author_id' => $author1->id]);
        $post2 = TestPost::create(['title' => 'Post Two', 'author_id' => $author2->id]);

        $response = $this->post(
            route('bpadmin.entity.bulk-destroy', ['entity' => 'owned_test_post']),
            ['ids' => [$post1->id, $post2->id]],
        );

        $response->assertOk()
            ->assertJson(['deleted' => 2, 'failed_ids' => [], 'not_found_ids' => []]);

        $this->assertDatabaseEmpty('test_posts');
        $this->assertDatabaseMissing('test_authors', ['id' => $author1->id]);
        $this->assertDatabaseMissing('test_authors', ['id' => $author2->id]);
        $this->assertDatabaseHas('test_authors', ['id' => $orphan->id]);
    }
}
