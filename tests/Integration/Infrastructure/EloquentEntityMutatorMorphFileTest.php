<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Infrastructure;

use BlackParadise\CoreAdmin\Domain\Contracts\Auth\AuthorizationProviderContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Files\FileStorageProviderContract;
use BlackParadise\CoreAdmin\Domain\Entity\EntityRecord;
use BlackParadise\CoreAdmin\Domain\Mutators\EntityMutatorInterface;
use BlackParadise\CoreAdmin\Domain\ValueObjects\EntityKey;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\CreatesTestMorphFileTables;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestAccount;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestAccountDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestAccountThatFailsDelete;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestAccountThatFailsDeleteDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestMorphedFile;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Mockery;
use RuntimeException;

final class EloquentEntityMutatorMorphFileTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTestMorphFileTables;

    private EntityMutatorInterface $mutator;
    private TestAccountDefinition  $definition;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMorphFileFixtures();

        // Permissive auth.
        $this->app->bind(AuthorizationProviderContract::class, function (): AuthorizationProviderContract {
            $mock = Mockery::mock(AuthorizationProviderContract::class);
            $mock->shouldReceive('can')->andReturn(true);
            return $mock;
        });

        // Deterministic file storage for assertions.
        $this->app->bind(FileStorageProviderContract::class, function (): FileStorageProviderContract {
            $mock = Mockery::mock(FileStorageProviderContract::class);
            $mock->shouldReceive('store')->andReturnUsing(
                fn(string $dir, mixed $file, ?string $disk = null): string => $dir . '/' . ($file instanceof UploadedFile ? $file->getClientOriginalName() : 'unknown'),
            );
            $mock->shouldReceive('delete')->andReturn(true);
            $mock->shouldReceive('url')->andReturnUsing(fn(string $p, ?string $disk = null): string => 'https://cdn.test/' . $p);
            return $mock;
        });

        $this->mutator    = $this->app->make(EntityMutatorInterface::class);
        $this->definition = new TestAccountDefinition();
    }

    // ------------------------------------------------------------------
    // create
    // ------------------------------------------------------------------

    public function test_create_persists_morph_file_record_for_uploaded_file(): void
    {
        $avatar = UploadedFile::fake()->create('avatar.jpg', 10, 'image/jpeg');

        $record = new EntityRecord($this->definition, [
            'name'   => 'Alice',
            'avatar' => $avatar,
        ]);

        $created = $this->mutator->create($record);

        $this->assertDatabaseHas('test_accounts', ['id' => $created->id(), 'name' => 'Alice']);
        $this->assertDatabaseHas('test_morphed_files', [
            'fileable_type' => TestAccount::class,
            'fileable_id'   => $created->id(),
            'type'          => 'avatar',
            'name'          => 'avatar.jpg',
            'path'          => 'avatars/avatar.jpg',
        ]);
    }

    public function test_create_without_morph_file_input_creates_no_record(): void
    {
        $record = new EntityRecord($this->definition, ['name' => 'Bob']);

        $created = $this->mutator->create($record);

        $this->assertDatabaseHas('test_accounts', ['id' => $created->id()]);
        $this->assertDatabaseCount('test_morphed_files', 0);
    }

    public function test_create_with_two_morph_files_persists_both_with_correct_type(): void
    {
        $avatar = UploadedFile::fake()->create('ava.png', 10, 'image/png');
        $image  = UploadedFile::fake()->create('img.png', 10, 'image/png');

        $record = new EntityRecord($this->definition, [
            'name'   => 'Carol',
            'avatar' => $avatar,
            'image'  => $image,
        ]);

        $created = $this->mutator->create($record);

        $this->assertDatabaseHas('test_morphed_files', [
            'fileable_id' => $created->id(),
            'type'        => 'avatar',
            'path'        => 'avatars/ava.png',
        ]);
        $this->assertDatabaseHas('test_morphed_files', [
            'fileable_id' => $created->id(),
            'type'        => 'image',
            'path'        => 'images/img.png',
        ]);
    }

    public function test_create_with_null_morph_file_creates_no_record(): void
    {
        $record = new EntityRecord($this->definition, [
            'name'   => 'Dave',
            'avatar' => null,
        ]);

        $created = $this->mutator->create($record);

        $this->assertDatabaseHas('test_accounts', ['id' => $created->id()]);
        $this->assertDatabaseCount('test_morphed_files', 0);
    }

    // ------------------------------------------------------------------
    // update
    // ------------------------------------------------------------------

    public function test_update_with_new_upload_replaces_morph_record(): void
    {
        $initial = UploadedFile::fake()->create('old.jpg', 10, 'image/jpeg');
        $created = $this->mutator->create(new EntityRecord($this->definition, [
            'name'   => 'Eve',
            'avatar' => $initial,
        ]));

        $this->assertDatabaseHas('test_morphed_files', ['path' => 'avatars/old.jpg']);

        $replacement = UploadedFile::fake()->create('new.jpg', 10, 'image/jpeg');
        $this->mutator->update(
            new EntityKey($created->id(), 'int'),
            new EntityRecord($this->definition, [
                'name'   => 'Eve',
                'avatar' => $replacement,
            ]),
        );

        $this->assertDatabaseMissing('test_morphed_files', ['path' => 'avatars/old.jpg']);
        $this->assertDatabaseHas('test_morphed_files', [
            'fileable_id' => $created->id(),
            'type'        => 'avatar',
            'path'        => 'avatars/new.jpg',
        ]);
        $this->assertDatabaseCount('test_morphed_files', 1);
    }

    public function test_update_without_upload_keeps_existing_morph_record(): void
    {
        $initial = UploadedFile::fake()->create('keep.jpg', 10, 'image/jpeg');
        $created = $this->mutator->create(new EntityRecord($this->definition, [
            'name'   => 'Frank',
            'avatar' => $initial,
        ]));

        // No 'avatar' key in update payload.
        $this->mutator->update(
            new EntityKey($created->id(), 'int'),
            new EntityRecord($this->definition, ['name' => 'Frank Renamed']),
        );

        $this->assertDatabaseHas('test_accounts', ['name' => 'Frank Renamed']);
        $this->assertDatabaseHas('test_morphed_files', ['path' => 'avatars/keep.jpg']);
        $this->assertDatabaseCount('test_morphed_files', 1);
    }

    public function test_update_replaces_only_the_targeted_morph_type(): void
    {
        $created = $this->mutator->create(new EntityRecord($this->definition, [
            'name'   => 'Grace',
            'avatar' => UploadedFile::fake()->create('ava1.jpg', 10, 'image/jpeg'),
            'image'  => UploadedFile::fake()->create('img1.jpg', 10, 'image/jpeg'),
        ]));

        // Replace only the avatar, leave image alone.
        $this->mutator->update(
            new EntityKey($created->id(), 'int'),
            new EntityRecord($this->definition, [
                'name'   => 'Grace',
                'avatar' => UploadedFile::fake()->create('ava2.jpg', 10, 'image/jpeg'),
            ]),
        );

        $this->assertDatabaseHas('test_morphed_files', ['type' => 'avatar', 'path' => 'avatars/ava2.jpg']);
        $this->assertDatabaseHas('test_morphed_files', ['type' => 'image',  'path' => 'images/img1.jpg']);
        $this->assertDatabaseMissing('test_morphed_files', ['path' => 'avatars/ava1.jpg']);
        $this->assertDatabaseCount('test_morphed_files', 2);
    }

    // ------------------------------------------------------------------
    // delete
    // ------------------------------------------------------------------

    public function test_delete_removes_morph_file_records_and_disk_files(): void
    {
        $created = $this->mutator->create(new EntityRecord($this->definition, [
            'name'   => 'Hank',
            'avatar' => UploadedFile::fake()->create('a.jpg', 10, 'image/jpeg'),
            'image'  => UploadedFile::fake()->create('b.jpg', 10, 'image/jpeg'),
        ]));

        $this->assertDatabaseCount('test_morphed_files', 2);

        $this->mutator->delete(new EntityKey($created->id(), 'int'), $this->definition);

        $this->assertDatabaseMissing('test_accounts', ['id' => $created->id()]);
        $this->assertDatabaseCount('test_morphed_files', 0);
    }

    // ------------------------------------------------------------------
    // delete rollback safety
    // ------------------------------------------------------------------

    public function test_file_storage_delete_not_called_when_transaction_rolls_back(): void
    {
        // Track whether Storage::delete() was invoked on the FileStorageProvider.
        $deleteWasCalled = false;

        $this->app->bind(FileStorageProviderContract::class, function () use (&$deleteWasCalled): FileStorageProviderContract {
            $mock = Mockery::mock(FileStorageProviderContract::class);
            $mock->shouldReceive('store')->andReturnUsing(
                fn(string $dir, mixed $file, ?string $disk = null): string => $dir . '/' . ($file instanceof UploadedFile ? $file->getClientOriginalName() : 'unknown'),
            );
            $mock->shouldReceive('delete')->andReturnUsing(function () use (&$deleteWasCalled): bool {
                $deleteWasCalled = true;
                return true;
            });
            $mock->shouldReceive('url')->andReturnUsing(fn(string $p, ?string $disk = null): string => 'https://cdn.test/' . $p);
            return $mock;
        });

        // Re-resolve the mutator so it uses the new binding.
        $mutator    = $this->app->make(EntityMutatorInterface::class);
        $definition = new TestAccountThatFailsDeleteDefinition();

        // Create a host with one morph file record.
        $account = TestAccountThatFailsDelete::query()->create(['name' => 'Ivan']);
        $account->files()->create([
            'type'      => 'avatar',
            'name'      => 'keep.jpg',
            'path'      => 'avatars/keep.jpg',
            'mime_type' => 'image/jpeg',
            'size'      => 1024,
        ]);

        $morphFileId = TestMorphedFile::query()->where('path', 'avatars/keep.jpg')->firstOrFail()->id;

        // Attempt delete — the model throws inside the transaction, causing rollback.
        try {
            $mutator->delete(new EntityKey((string) $account->id, 'int'), $definition);
            self::fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $e) {
            self::assertSame('forced delete failure', $e->getMessage());
        }

        // The MorphFile DB record MUST still exist (transaction rolled back).
        self::assertNotNull(TestMorphedFile::find($morphFileId), 'Morph file record was deleted despite rollback');

        // The host record MUST still exist.
        self::assertNotNull(TestAccountThatFailsDelete::find($account->id), 'Host record was deleted despite rollback');

        // Storage::delete() must NOT have been called (file paths must not be deleted from disk).
        self::assertFalse($deleteWasCalled, 'FileStorageProvider::delete() was called even though transaction rolled back');
    }
}
