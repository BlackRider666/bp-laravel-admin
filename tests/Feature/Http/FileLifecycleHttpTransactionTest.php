<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Feature\Http;

use BlackParadise\CoreAdmin\Domain\Contracts\Auth\AuthorizationProviderContract;
use BlackParadise\CoreAdmin\Domain\Fields\FileField;
use BlackParadise\CoreAdmin\Domain\Fields\HasOneField;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\BootsBPAdmin;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\StubsValueHasher;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Mockery;

/**
 * Fixture: host with a file column + hasOne child whose 'required_note' column
 * is NOT NULL — leaving it empty causes a QueryException after the host's inner
 * transaction has already committed (and the mutator has already flushed
 * DeferredFileOperations), triggering the outer-transaction-boundary bug.
 *
 * The hasOne child is handled via EmbeddedChildWriter in the CONTROLLER, AFTER
 * the mutator's inner DB::transaction() completes. This means:
 *
 *   1. Host + file written → inner savepoint commits.
 *   2. EloquentEntityMutator::create/update calls deferredFiles->commit() → reset().
 *   3. EmbeddedChildWriter tries to create the child → NOT NULL fails → QueryException.
 *   4. Outer controller DB::transaction() rolls back the DB.
 *   5. Controller finally-block calls deferredFiles->rollback() → NO-OP (already reset).
 *
 * Result:
 *   A3 (store): uploaded new file is NOT deleted — orphaned on disk.
 *   A2 (update): old replaced file IS deleted — data loss.
 *
 * These tests MUST be RED against current code.
 * They will go GREEN once DeferredFileOperations flush is deferred
 * to the controller's outermost transaction boundary.
 */
final class FlhNote extends Model
{
    protected $table = 'flh_http_notes';
    protected $guarded = [];
}

final class FlhHost extends Model
{
    protected $table = 'flh_http_hosts';
    protected $guarded = [];

    public function note(): HasOne
    {
        return $this->hasOne(FlhNote::class, 'host_id');
    }
}

/** Child: 'required_note' is NOT NULL — submitting empty value forces failure. */
final class FlhNoteDefinition extends EntityDefinition
{
    public string $model = FlhNote::class;

    public function resolveName(): string
    {
        return 'flh_http_note';
    }

    public function fields(): array
    {
        return [
            TextField::make('required_note'),
        ];
    }
}

/**
 * Host: FileField + hasOne-embedded note (hasOne goes into controller's defer
 * map and is written by EmbeddedChildWriter OUTSIDE the mutator's inner tx).
 */
final class FlhHostDefinition extends EntityDefinition
{
    public string $model = FlhHost::class;

    public function resolveName(): string
    {
        return 'flh_http_host';
    }

    public function fields(): array
    {
        return [
            TextField::make('name'),
            FileField::make('attachment')->disk('public'),
            HasOneField::make('note', FlhNote::class)
                ->embed(FlhNoteDefinition::class),
        ];
    }
}

/**
 * A2/A3: File lifecycle correctness on the OUTER controller transaction boundary.
 *
 * This test class exercises the real HTTP route (AdminEntityController::store /
 * AdminEntityController::update) to reproduce the bug that the direct-mutator
 * tests in FileLifecycleTransactionTest CANNOT expose:
 *
 *   FileLifecycleTransactionTest calls the mutator directly → the mutator IS the
 *   outermost transaction → its own commit()/rollback() flush is correct.
 *
 *   HERE the controller wraps the mutator in an outer DB::transaction(). The
 *   mutator commits its inner savepoint and prematurely flushes
 *   DeferredFileOperations. When EmbeddedChildWriter subsequently fails (causing
 *   the outer tx to roll back), the deferred list is already empty and the
 *   controller's finally-block rollback() is a no-op.
 *
 * Assertions:
 *   A3 (create): outer-tx rollback → newly uploaded file DELETED (not orphaned).
 *   A2 (update): outer-tx rollback → old replaced file KEPT (not lost).
 *   Happy path:  outer-tx commit  → old file deleted, new file kept.
 */
final class FileLifecycleHttpTransactionTest extends TestCase
{
    use RefreshDatabase;
    use BootsBPAdmin;
    use StubsValueHasher;

    protected function setUp(): void
    {
        parent::setUp();

        // Must be first: prevents LaravelValueHasher fatal on container resolution.
        $this->stubValueHasher();

        Storage::fake('public');

        $this->setUpBPAdmin();

        Schema::create('flh_http_hosts', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('attachment')->nullable();
            $table->timestamps();
        });

        Schema::create('flh_http_notes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('host_id');
            $table->string('required_note'); // NOT NULL — empty value → QueryException
            $table->timestamps();
        });

        /** @var EntityDefinitionRegistry $registry */
        $registry = $this->app->make(EntityDefinitionRegistry::class);
        $registry->register(new FlhHostDefinition());
        $registry->register(new FlhNoteDefinition());

        $this->app->bind(AuthorizationProviderContract::class, function (): AuthorizationProviderContract {
            $mock = Mockery::mock(AuthorizationProviderContract::class);
            $mock->shouldReceive('can')->andReturn(true);
            return $mock;
        });

        $this->actingAsAdmin();
    }

    // ------------------------------------------------------------------
    // A3 (create via HTTP): outer-tx rollback → uploaded file must be deleted
    // ------------------------------------------------------------------

    /**
     * POST /admin/flh_http_host with a file upload AND an embedded hasOne child
     * whose required_note is intentionally left empty.
     *
     * Expected flow:
     *   1. Controller outer DB::transaction() begins.
     *   2. createRecord use case fires → mutator opens inner savepoint.
     *   3. File is uploaded; path recorded in deferredFiles.
     *   4. Inner savepoint commits → mutator calls deferredFiles->commit() + reset().
     *      (BUG: premature flush clears the uploads list)
     *   5. EmbeddedChildWriter::writeAll() tries to insert note with required_note = null.
     *   6. QueryException → outer DB::transaction() rolls back.
     *   7. Controller finally-block: deferredFiles->rollback() → NO-OP (already reset).
     *   8. Result: uploaded file is orphaned on disk (A3 violated).
     *
     * After the fix the deferred list is not reset until the controller's own
     * commit/rollback call, so the orphaned upload is cleaned up.
     *
     * Currently RED: Storage::disk('public')->assertDirectoryEmpty('flh_http_host')
     * fails because the file lingers on disk.
     */
    public function test_store_via_http_uploaded_file_deleted_when_outer_tx_rolls_back(): void
    {
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $this->postJson(
            route('bpadmin.entity.store', ['entity' => 'flh_http_host']),
            [
                'name'       => 'Host A',
                'attachment' => $file,
                // note.required_note deliberately omitted/empty → child write fails
                'note' => ['required_note' => ''],
            ],
        );
        // We intentionally do not assert on the HTTP status code — the controller
        // may return any non-2xx status. What matters is disk state.

        // A3: no file should linger on disk after outer-tx rollback.
        Storage::disk('public')->assertDirectoryEmpty('flh_http_host');
    }

    // ------------------------------------------------------------------
    // A2 (update via HTTP): outer-tx rollback → old replaced file must survive
    // ------------------------------------------------------------------

    /**
     * PUT /admin/flh_http_host/{id} replacing the existing file AND failing the
     * embedded hasOne child write.
     *
     * Expected flow:
     *   1. Controller outer DB::transaction() begins.
     *   2. findRecord fetches the host; updateRecord fires → mutator inner savepoint.
     *   3. New file uploaded; old path recorded for deferred deletion.
     *   4. Inner savepoint commits → mutator calls deferredFiles->commit():
     *      - OLD file deleted from disk.                (A2 BUG: premature deletion)
     *      - deferredFiles->reset() clears all state.
     *   5. EmbeddedChildWriter::writeAll() fails → outer tx rolls back.
     *   6. Controller finally-block: deferredFiles->rollback() → NO-OP.
     *   7. Result: old file is gone (data loss) + new file orphaned (A3).
     *
     * After the fix only the controller's commit() call triggers old-file deletion,
     * so a rollback keeps the old file intact.
     *
     * Currently RED: Storage::disk('public')->assertExists('flh_http_host/old.pdf')
     * fails because the old file was deleted prematurely.
     */
    public function test_update_via_http_old_file_kept_when_outer_tx_rolls_back(): void
    {
        // Arrange: host with an existing file on disk.
        Storage::disk('public')->put('flh_http_host/old.pdf', 'original content');
        $host = FlhHost::create([
            'name'       => 'Host B',
            'attachment' => 'flh_http_host/old.pdf',
        ]);

        $newFile = UploadedFile::fake()->create('replacement.pdf', 50, 'application/pdf');

        // Act: update replacing the file, but force child write to fail.
        $this->putJson(
            route('bpadmin.entity.update', ['entity' => 'flh_http_host', 'id' => $host->id]),
            [
                'name'       => 'Host B Updated',
                'attachment' => $newFile,
                // required_note empty → QueryException in EmbeddedChildWriter
                'note' => ['required_note' => ''],
            ],
        );

        // A2: old file must still exist after the failed (rolled-back) update.
        Storage::disk('public')->assertExists('flh_http_host/old.pdf');
    }

    // ------------------------------------------------------------------
    // Happy path: outer-tx commit → old file deleted, new file kept
    // ------------------------------------------------------------------

    /**
     * PUT /admin/flh_http_host/{id} with valid data.
     *
     * On success: the controller commit() fires, old file is deleted, new file
     * is accessible. This ensures the deferred-flush fix does not break the
     * normal success path.
     */
    public function test_update_via_http_on_success_old_file_deleted_new_file_kept(): void
    {
        // Arrange.
        Storage::disk('public')->put('flh_http_host/original.pdf', 'original content');
        $host = FlhHost::create([
            'name'       => 'Host C',
            'attachment' => 'flh_http_host/original.pdf',
        ]);

        $newFile = UploadedFile::fake()->create('updated.pdf', 50, 'application/pdf');

        // Act: valid child payload — child write succeeds.
        $this->putJson(
            route('bpadmin.entity.update', ['entity' => 'flh_http_host', 'id' => $host->id]),
            [
                'name'       => 'Host C Updated',
                'attachment' => $newFile,
                'note' => ['required_note' => 'valid text'],
            ],
        )->assertOk();

        // New file must be on disk.
        $updatedHost = FlhHost::find($host->id);
        $this->assertNotNull($updatedHost->attachment);
        Storage::disk('public')->assertExists($updatedHost->attachment);

        // Old file must be gone.
        Storage::disk('public')->assertMissing('flh_http_host/original.pdf');
    }
}
