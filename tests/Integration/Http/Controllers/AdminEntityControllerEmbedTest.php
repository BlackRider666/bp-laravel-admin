<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Http\Controllers;

use BlackParadise\CoreAdmin\Domain\Contracts\Auth\AuthorizationProviderContract;
use BlackParadise\CoreAdmin\Domain\Fields\BelongsToField;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\BootsBPAdmin;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestAuthor;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestAuthorDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestPost;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestPostWithEmbeddedAuthorDefinition;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Mockery;

final class AdminEntityControllerEmbedTest extends TestCase
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
        $registry->register(new TestPostWithEmbeddedAuthorDefinition());
        $registry->register(new TestAuthorDefinition());

        $this->app->bind(AuthorizationProviderContract::class, function (): AuthorizationProviderContract {
            $mock = Mockery::mock(AuthorizationProviderContract::class);
            $mock->shouldReceive('can')->andReturn(true);
            return $mock;
        });

        $this->actingAsAdmin();
    }

    public function test_store_creates_embedded_author_and_attaches_to_post(): void
    {
        $response = $this->postJson(route('bpadmin.entity.store', ['entity' => 'test_post']), [
            'title' => 'My first post',
            'author_id' => [
                'name'  => 'Alice Writer',
                'email' => 'alice@w.com',
            ],
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('test_authors', ['name' => 'Alice Writer', 'email' => 'alice@w.com']);
        $author = TestAuthor::first();
        $this->assertDatabaseHas('test_posts', ['title' => 'My first post', 'author_id' => $author->id]);
    }

    public function test_destroy_removes_owned_embedded_author(): void
    {
        $author = TestAuthor::create(['name' => 'Bob', 'email' => 'bob@e.com']);
        $post   = TestPost::create(['title' => 'P', 'author_id' => $author->id]);

        $response = $this->deleteJson(route('bpadmin.entity.destroy', ['entity' => 'test_post', 'id' => $post->id]));

        $response->assertNoContent();
        $this->assertDatabaseMissing('test_posts', ['id' => $post->id]);
        $this->assertDatabaseMissing('test_authors', ['id' => $author->id]);
    }

    public function test_update_updates_embedded_author_in_place(): void
    {
        $author = TestAuthor::create(['name' => 'Init', 'email' => 'i@e.com']);
        $post   = TestPost::create(['title' => 'Original', 'author_id' => $author->id]);

        $this->putJson(route('bpadmin.entity.update', ['entity' => 'test_post', 'id' => $post->id]), [
            'title' => 'Updated',
            'author_id' => [
                'name'  => 'Renamed',
                'email' => 'i@e.com',
            ],
        ])->assertOk();

        $this->assertDatabaseHas('test_posts', ['id' => $post->id, 'title' => 'Updated']);
        $this->assertDatabaseHas('test_authors', ['id' => $author->id, 'name' => 'Renamed']);
        $this->assertDatabaseCount('test_authors', 1);
    }

    public function test_destroy_without_owns_leaves_related_author_intact(): void
    {
        // Override definition without ->owns()
        $defWithoutOwns = new class extends EntityDefinition {
            public string $model = TestPost::class;
            public function resolveName(): string
            {
                return 'test_post';
            }
            public function fields(): array
            {
                return [
                    TextField::make('title'),
                    BelongsToField::make('author_id', TestAuthor::class)
                        ->embed(TestAuthorDefinition::class),
                ];
            }
        };
        $this->app->make(EntityDefinitionRegistry::class)->register($defWithoutOwns);

        $author = TestAuthor::create(['name' => 'Carol', 'email' => 'c@e.com']);
        $post   = TestPost::create(['title' => 'Q', 'author_id' => $author->id]);

        $this->delete(route('bpadmin.entity.destroy', ['entity' => 'test_post', 'id' => $post->id]));

        $this->assertDatabaseMissing('test_posts', ['id' => $post->id]);
        $this->assertDatabaseHas('test_authors', ['id' => $author->id]);
    }
}
