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
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Mockery;

/**
 * A morph target model — a simple "Post" that can be commented on.
 */
final class MorphTargetPost extends Model
{
    protected $table = 'morph_target_posts';
    protected $guarded = [];
}

/**
 * A comment model with a polymorphic commentable relation.
 */
final class MorphComment extends Model
{
    protected $table = 'morph_comments';
    protected $guarded = [];

    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }
}

/**
 * EntityDefinition for MorphComment with a MorphToField.
 */
final class MorphCommentDefinition extends EntityDefinition
{
    public string $model = MorphComment::class;

    public function resolveName(): string
    {
        return 'morph_comment';
    }

    public function fields(): array
    {
        return [
            TextField::make('body')->required(),
            MorphToField::make('commentable', MorphTargetPost::class)
                ->morphTypes([MorphTargetPost::class => ['label' => 'Post', 'display' => 'title']])
                ->required(),
        ];
    }
}

/**
 * Bug #8 — MorphTo columns admitted into the write path.
 *
 * A MorphToField named 'commentable' writes two real columns:
 * 'commentable_type' and 'commentable_id'. Because the whitelist in
 * EntityWriteRequest::attributesForWrite() and the column-filter in
 * EloquentEntityMutator::filterAttributes() key off FIELD NAMES (not column
 * names), these columns are stripped — causing NOT NULL constraint failures.
 *
 * A11 — POST with morph columns persists both _type and _id.
 * A12 — morph _type value is normalised through getMorphClass() when a morph
 *        map is registered (alias stored instead of FQCN).
 */
final class MorphToCreateTest extends TestCase
{
    use RefreshDatabase;
    use BootsBPAdmin;
    use StubsValueHasher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stubValueHasher(); // must be first

        $this->setUpBPAdmin();

        Schema::create('morph_target_posts', function (Blueprint $t): void {
            $t->id();
            $t->string('title');
            $t->timestamps();
        });

        Schema::create('morph_comments', function (Blueprint $t): void {
            $t->id();
            $t->string('body');
            $t->morphs('commentable');
            $t->timestamps();
        });

        /** @var EntityDefinitionRegistry $registry */
        $registry = $this->app->make(EntityDefinitionRegistry::class);
        $registry->register(new MorphCommentDefinition());

        $this->app->bind(AuthorizationProviderContract::class, function (): AuthorizationProviderContract {
            $mock = Mockery::mock(AuthorizationProviderContract::class);
            $mock->shouldReceive('can')->andReturn(true);
            return $mock;
        });

        $this->actingAsAdmin();
    }

    /**
     * A11 — Creating a comment with morph columns persists both _type and _id.
     *
     * Without the fix: commentable_type / commentable_id are stripped from the
     * write path (they are not field names), causing a NOT NULL DB failure (500).
     * With the fix: both columns are admitted and the record is created (201).
     */
    public function test_create_with_morph_target_persists_columns(): void
    {
        $post = MorphTargetPost::create(['title' => 'Hello World']);

        $response = $this->postJson(
            route('bpadmin.entity.store', ['entity' => 'morph_comment']),
            [
                'body'             => 'Nice post!',
                'commentable_type' => MorphTargetPost::class,
                'commentable_id'   => $post->id,
            ],
        );

        $response->assertSessionHasNoErrors();
        $response->assertCreated();

        $this->assertDatabaseHas('morph_comments', [
            'body'             => 'Nice post!',
            'commentable_id'   => $post->id,
            'commentable_type' => MorphTargetPost::class,
        ]);
    }

    /**
     * A12 — When a morph map is registered, the stored _type must be the alias.
     *
     * getMorphClass() on the model returns the alias when a morph map entry
     * exists. The mutator must normalise the raw FQCN coming from the form
     * to the alias before saving.
     */
    public function test_create_normalises_morph_type_to_morph_map_alias(): void
    {
        Relation::morphMap([
            'post' => MorphTargetPost::class,
        ]);

        $post = MorphTargetPost::create(['title' => 'Aliased Post']);

        $response = $this->postJson(
            route('bpadmin.entity.store', ['entity' => 'morph_comment']),
            [
                'body'             => 'Aliased comment',
                'commentable_type' => MorphTargetPost::class, // raw FQCN from picker
                'commentable_id'   => $post->id,
            ],
        );

        $response->assertSessionHasNoErrors();
        $response->assertCreated();

        // Stored type must be the alias, not the raw FQCN.
        $this->assertDatabaseHas('morph_comments', [
            'body'             => 'Aliased comment',
            'commentable_id'   => $post->id,
            'commentable_type' => 'post',
        ]);

        // Cleanup morph map to avoid leaking state.
        Relation::morphMap([], false);
    }
}
