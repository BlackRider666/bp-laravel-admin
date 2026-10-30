<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Persistence;

use BlackParadise\CoreAdmin\Domain\Fields\BelongsToManyField;
use BlackParadise\CoreAdmin\Domain\Fields\FileField;
use BlackParadise\CoreAdmin\Domain\Fields\MorphFileField;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestMorphedFile;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestTag;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class FdtHost extends Model
{
    use SoftDeletes;

    protected $table = 'fdt_hosts';
    protected $guarded = [];

    /** @return BelongsToMany<TestTag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(TestTag::class, 'fdt_host_tag', 'fdt_host_id', 'test_tag_id');
    }

    /** @return MorphMany<TestMorphedFile, $this> */
    public function files(): MorphMany
    {
        return $this->morphMany(TestMorphedFile::class, 'fileable');
    }
}

final class FdtHostDefinition extends EntityDefinition
{
    public string $model = FdtHost::class;

    public function resolveName(): string
    {
        return 'fdt_host';
    }

    public function fields(): array
    {
        return [
            TextField::make('name'),
            FileField::make('doc')->disk('archive'),
            MorphFileField::make('attachment')->morphName('files')->storesAs('attachment')->fileModel(TestMorphedFile::class)->directory('fdt')->disk('archive'),
            BelongsToManyField::make('tags', TestTag::class),
        ];
    }
}

/**
 * Task 2.2 fix round 1 — forceDelete() outside the admin:
 *  - inside an outer DB transaction the file cleanup is deferred to commit (rollback keeps files);
 *  - DB-level cascade (pivot detach) happens BEFORE the host row is deleted (FK without ON DELETE CASCADE).
 * No RefreshDatabase on purpose: the transaction boundary is the subject under test.
 */
final class ForceDeleteTransactionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('archive');
        DB::statement('PRAGMA foreign_keys = ON');

        Schema::create('test_tags', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->timestamps();
        });
        Schema::create('fdt_hosts', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->string('doc')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });
        Schema::create('fdt_host_tag', function (Blueprint $t): void {
            $t->foreignId('fdt_host_id')->constrained('fdt_hosts'); // NO ON DELETE CASCADE
            $t->unsignedBigInteger('test_tag_id');
        });
        Schema::create('test_morphed_files', function (Blueprint $t): void {
            $t->id();
            $t->string('fileable_type');
            $t->unsignedBigInteger('fileable_id');
            $t->string('type');
            $t->string('name');
            $t->string('path');
            $t->string('mime_type')->nullable();
            $t->unsignedBigInteger('size')->nullable();
            $t->timestamps();
        });

        resolve(EntityDefinitionRegistry::class)->register(new FdtHostDefinition());
    }

    private function seedHost(): FdtHost
    {
        Storage::disk('archive')->put('fdt/doc.bin', 'x');
        Storage::disk('archive')->put('fdt/morph.bin', 'x');
        $host = FdtHost::create(['name' => 'H', 'doc' => 'fdt/doc.bin']);
        $host->files()->create(['type' => 'attachment', 'name' => 'morph.bin', 'path' => 'fdt/morph.bin']);

        return $host;
    }

    public function test_force_delete_in_rolled_back_transaction_keeps_row_and_files(): void
    {
        $host = $this->seedHost();

        try {
            DB::transaction(function () use ($host): never {
                $host->forceDelete();
                throw new RuntimeException('boom');
            });
        } catch (RuntimeException) {
            // expected: outer transaction rolled back
        }

        self::assertSame(1, DB::table('fdt_hosts')->count());
        Storage::disk('archive')->assertExists('fdt/doc.bin');
        Storage::disk('archive')->assertExists('fdt/morph.bin');
    }

    public function test_force_delete_in_committed_transaction_removes_files_after_commit(): void
    {
        $host = $this->seedHost();

        DB::transaction(function () use ($host): void {
            $host->forceDelete();
        });

        self::assertSame(0, DB::table('fdt_hosts')->count());
        Storage::disk('archive')->assertMissing('fdt/doc.bin');
        Storage::disk('archive')->assertMissing('fdt/morph.bin');
    }

    public function test_force_delete_detaches_pivot_rows_before_host_row_is_deleted(): void
    {
        $host = $this->seedHost();
        $tag = TestTag::create(['name' => 'T']);
        $host->tags()->attach($tag->id);

        $host->forceDelete();

        self::assertSame(0, DB::table('fdt_hosts')->count());
        self::assertSame(0, DB::table('fdt_host_tag')->count());
    }
}
