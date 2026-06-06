<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Infrastructure;

use BlackParadise\CoreAdmin\Domain\Contracts\Auth\AuthorizationProviderContract;
use BlackParadise\CoreAdmin\Domain\Entity\EntityRecord;
use BlackParadise\CoreAdmin\Domain\Fields\FileField;
use BlackParadise\CoreAdmin\Domain\Fields\HasManyField;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\CoreAdmin\Domain\Mutators\EntityMutatorInterface;
use BlackParadise\CoreAdmin\Domain\ValueObjects\EntityKey;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\StubsValueHasher;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Throwable;

/**
 * Fixture: host with a file column + hasMany child whose 'required_text' column is NOT NULL.
 * Leaving 'required_text' empty causes a NOT NULL constraint violation on child insert,
 * which forces the outer transaction to roll back.
 *
 * This lets us test:
 *   A3: on rollback → newly uploaded file must be DELETED (orphan cleanup)
 *   A2: on rollback → replaced old file must be KEPT (not prematurely deleted)
 */
final class FileLifecycleTxHost extends Model
{
    protected $table = 'file_lifecycle_hosts';
    protected $guarded = [];

    public function notes(): HasMany
    {
        return $this->hasMany(FileLifecycleTxNote::class, 'host_id');
    }
}

final class FileLifecycleTxNote extends Model
{
    protected $table = 'file_lifecycle_notes';
    protected $guarded = [];
}

final class FileLifecycleTxHostDefinition extends EntityDefinition
{
    public string $model = FileLifecycleTxHost::class;

    public function resolveName(): string
    {
        return 'file_lifecycle_host';
    }

    public function fields(): array
    {
        return [
            TextField::make('name'),
            FileField::make('attachment')->disk('public'),
            HasManyField::make('notes', FileLifecycleTxNote::class),
        ];
    }
}

final class FileLifecycleTxNoteDefinition extends EntityDefinition
{
    public string $model = FileLifecycleTxNote::class;

    public function resolveName(): string
    {
        return 'file_lifecycle_note';
    }

    public function fields(): array
    {
        return [
            TextField::make('required_text'),
        ];
    }
}

/**
 * A2/A3: File lifecycle correctness on outer transaction boundaries.
 *
 * Bug (A3): on rollback the newly uploaded file is NOT removed — it orphans on disk.
 * Bug (A2): on rollback the OLD replaced file IS removed prematurely — data loss.
 *
 * Fix: DeferredFileOperations defers all disk I/O to the controller transaction boundary.
 */
final class FileLifecycleTransactionTest extends TestCase
{
    use RefreshDatabase;
    use StubsValueHasher;

    private EntityMutatorInterface $mutator;
    private FileLifecycleTxHostDefinition $definition;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stubValueHasher(); // must be first — prevents LaravelValueHasher fatal

        Storage::fake('public');

        Schema::create('file_lifecycle_hosts', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('attachment')->nullable();
            $table->timestamps();
        });

        Schema::create('file_lifecycle_notes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('host_id');
            $table->string('required_text'); // NOT NULL — leave empty to force failure
            $table->timestamps();
        });

        // Permissive auth mock.
        $this->app->bind(AuthorizationProviderContract::class, function (): AuthorizationProviderContract {
            $mock = Mockery::mock(AuthorizationProviderContract::class);
            $mock->shouldReceive('can')->andReturn(true);
            return $mock;
        });

        $this->mutator    = $this->app->make(EntityMutatorInterface::class);
        $this->definition = new FileLifecycleTxHostDefinition();
    }

    // ------------------------------------------------------------------
    // A3: on rollback — uploaded new file must be deleted from disk
    // ------------------------------------------------------------------

    /**
     * When creating a host with a new file upload and the embedded hasMany
     * child write fails (NOT NULL constraint), the outer transaction rolls
     * back and the freshly-uploaded file must be removed from disk.
     *
     * Currently FAILS: the uploaded file is stored before the transaction
     * rolls back and DeferredFileOperations.rollback() is never called, so
     * the file remains orphaned on disk.
     */
    public function test_uploaded_file_is_deleted_from_disk_when_outer_transaction_rolls_back(): void
    {
        $file = UploadedFile::fake()->create('contract.pdf', 100, 'application/pdf');

        try {
            $this->mutator->create(new EntityRecord($this->definition, [
                'name'       => 'Host A',
                'attachment' => $file,
                // Supply a notes child with empty required_text → forces NOT NULL failure
                'notes'      => [['required_text' => '']],
            ]));
        } catch (Throwable) {
            // Transaction expected to fail; we only care about disk state.
        }

        // A3 assertion: no file should linger on disk after rollback.
        Storage::disk('public')->assertDirectoryEmpty('file_lifecycle_host');
    }

    // ------------------------------------------------------------------
    // A2: on rollback — old/replaced file must NOT be deleted from disk
    // ------------------------------------------------------------------

    /**
     * When updating a host by replacing its file AND the embedded hasMany
     * child write fails, the outer transaction rolls back and the OLD file
     * must still be present on disk.
     *
     * Currently FAILS: cleanupReplacedFiles() deletes the old file immediately
     * after the inner mutator transaction, BEFORE the outer transaction
     * boundary is known — so the old file is gone even though the update
     * effectively never happened.
     */
    public function test_old_replaced_file_is_kept_on_disk_when_outer_transaction_rolls_back(): void
    {
        // Pre-create a host with an existing file.
        Storage::disk('public')->put('file_lifecycle_host/old.pdf', 'old content');
        $host = FileLifecycleTxHost::create([
            'name'       => 'Host B',
            'attachment' => 'file_lifecycle_host/old.pdf',
        ]);

        $newFile = UploadedFile::fake()->create('new.pdf', 100, 'application/pdf');

        try {
            $this->mutator->update(
                new EntityKey($host->id, 'int'),
                new EntityRecord($this->definition, [
                    'name'       => 'Host B Updated',
                    'attachment' => $newFile,
                    // Empty required_text forces NOT NULL failure → rollback
                    'notes'      => [['required_text' => '']],
                ]),
            );
        } catch (Throwable) {
            // expected rollback
        }

        // A2 assertion: old file must still exist after the failed update.
        Storage::disk('public')->assertExists('file_lifecycle_host/old.pdf');
    }

    // ------------------------------------------------------------------
    // Happy path: on commit — old file is cleaned up, new file is kept
    // ------------------------------------------------------------------

    /**
     * When the update succeeds, the old replaced file should be deleted
     * and the new file should be accessible on disk.
     */
    public function test_on_successful_update_old_file_is_deleted_and_new_file_kept(): void
    {
        Storage::disk('public')->put('file_lifecycle_host/original.pdf', 'original');
        $host = FileLifecycleTxHost::create([
            'name'       => 'Host C',
            'attachment' => 'file_lifecycle_host/original.pdf',
        ]);

        $newFile = UploadedFile::fake()->create('updated.pdf', 50, 'application/pdf');

        $this->mutator->update(
            new EntityKey($host->id, 'int'),
            new EntityRecord($this->definition, [
                'name'       => 'Host C Updated',
                'attachment' => $newFile,
                // No notes → no child write failure
            ]),
        );

        // New file must be on disk.
        $updatedHost = FileLifecycleTxHost::find($host->id);
        $this->assertNotNull($updatedHost->attachment);
        Storage::disk('public')->assertExists($updatedHost->attachment);

        // Old file must be gone.
        Storage::disk('public')->assertMissing('file_lifecycle_host/original.pdf');
    }
}
