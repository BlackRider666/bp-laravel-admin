<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Persistence;

use BlackParadise\CoreAdmin\Domain\Contracts\Auth\AuthorizationProviderContract;
use BlackParadise\CoreAdmin\Domain\Fields\ImageField;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\CoreAdmin\Domain\Mutators\EntityMutatorInterface;
use BlackParadise\CoreAdmin\Domain\ValueObjects\EntityKey;
use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Mockery;

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

final class SoftDeletePost extends Model
{
    use SoftDeletes;

    protected $table = 'soft_delete_posts';
    protected $guarded = [];
}

final class SoftDeletePostDefinition extends EntityDefinition
{
    public string $model = SoftDeletePost::class;

    public function resolveName(): string
    {
        return 'soft_delete_post';
    }

    public function fields(): array
    {
        return [
            TextField::make('title'),
            ImageField::make('cover_image')->disk('public'),
        ];
    }
}

// ---------------------------------------------------------------------------
// Test
// ---------------------------------------------------------------------------

/**
 * E.2 + E.3 — soft-delete file lifecycle (bug #6).
 *
 * A20 / E.2: soft delete must NOT remove files from disk — row stays, file stays.
 * A22 / E.2: same behaviour via the bulk path (shares the same delete() branch).
 * A21 / E.3: forceDelete must remove files from disk (no orphans after prune).
 */
final class SoftDeleteFileLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private EntityMutatorInterface $mutator;
    private SoftDeletePostDefinition $definition;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        Schema::create('soft_delete_posts', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('cover_image')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        // Permissive auth.
        $this->app->bind(AuthorizationProviderContract::class, function (): AuthorizationProviderContract {
            $mock = Mockery::mock(AuthorizationProviderContract::class);
            $mock->shouldReceive('can')->andReturn(true);
            return $mock;
        });

        $this->definition = new SoftDeletePostDefinition();

        /** @var EntityDefinitionRegistry $registry */
        $registry = $this->app->make(EntityDefinitionRegistry::class);
        $registry->register($this->definition);

        $this->mutator = resolve(EntityMutatorInterface::class);
    }

    // ------------------------------------------------------------------
    // A20 / E.2 — soft delete keeps file on disk
    // ------------------------------------------------------------------

    /**
     * When the model uses SoftDeletes, calling mutator delete() performs a soft
     * delete (sets deleted_at) and must NOT remove the file from disk.
     */
    public function test_soft_delete_keeps_file_on_disk(): void
    {
        Storage::disk('public')->put('soft_delete_post/cover.jpg', 'image content');

        $post = SoftDeletePost::create([
            'title'       => 'Hello',
            'cover_image' => 'soft_delete_post/cover.jpg',
        ]);

        $key = new EntityKey($post->id, 'int');
        $result = $this->mutator->delete($key, $this->definition);

        self::assertTrue($result);
        $this->assertSoftDeleted('soft_delete_posts', ['id' => $post->id]);
        Storage::disk('public')->assertExists('soft_delete_post/cover.jpg');
    }

    // ------------------------------------------------------------------
    // A22 / E.2 — bulk soft delete keeps files (same delete() branch)
    // ------------------------------------------------------------------

    /**
     * Bulk destroy reuses the same delete() code path per record.
     * Verify two records are soft-deleted and neither file is wiped.
     */
    public function test_bulk_soft_delete_keeps_files(): void
    {
        Storage::disk('public')->put('soft_delete_post/file1.jpg', 'img1');
        Storage::disk('public')->put('soft_delete_post/file2.jpg', 'img2');

        $post1 = SoftDeletePost::create(['title' => 'Post 1', 'cover_image' => 'soft_delete_post/file1.jpg']);
        $post2 = SoftDeletePost::create(['title' => 'Post 2', 'cover_image' => 'soft_delete_post/file2.jpg']);

        $this->mutator->delete(new EntityKey($post1->id, 'int'), $this->definition);
        $this->mutator->delete(new EntityKey($post2->id, 'int'), $this->definition);

        $this->assertSoftDeleted('soft_delete_posts', ['id' => $post1->id]);
        $this->assertSoftDeleted('soft_delete_posts', ['id' => $post2->id]);

        Storage::disk('public')->assertExists('soft_delete_post/file1.jpg');
        Storage::disk('public')->assertExists('soft_delete_post/file2.jpg');
    }

    // ------------------------------------------------------------------
    // A21 / E.3 — forceDelete removes file from disk
    // ------------------------------------------------------------------

    /**
     * When a soft-deleted model is later forceDelete()d, the service provider's
     * global forceDeleted listener must remove the associated file from disk so
     * there are no orphaned files after a prune.
     */
    public function test_force_delete_removes_file(): void
    {
        Storage::disk('public')->put('soft_delete_post/orphan.jpg', 'image content');

        $post = SoftDeletePost::create([
            'title'       => 'To Prune',
            'cover_image' => 'soft_delete_post/orphan.jpg',
        ]);

        // Soft-delete first (normal lifecycle), then force-delete (prune step).
        $post->delete();
        $post->forceDelete();

        $this->assertDatabaseMissing('soft_delete_posts', ['id' => $post->id]);
        Storage::disk('public')->assertMissing('soft_delete_post/orphan.jpg');
    }
}
