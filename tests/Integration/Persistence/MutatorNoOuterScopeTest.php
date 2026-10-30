<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Persistence;

use BlackParadise\CoreAdmin\Domain\Entity\EntityRecord;
use BlackParadise\CoreAdmin\Domain\Events\EntityCreated;
use BlackParadise\CoreAdmin\Domain\Exceptions\ValidationException;
use BlackParadise\CoreAdmin\Domain\Mutators\EntityMutatorInterface;
use BlackParadise\CoreAdmin\Domain\ValueObjects\EntityKey;
use BlackParadise\LaravelAdmin\Core\UseCaseFactory;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\ActsAsPermissiveAdmin;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\CreatesClearFileFixtures;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\RecordsDomainEvents;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ClearFile\ClearDoc;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ClearFile\ClearDocDefinition;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * A28: a CLI / seeder / queued job calling the mutator or a use case directly (no HTTP, no outer
 * write scope) still persists files and dispatches events — the mutator self-flushes.
 */
final class MutatorNoOuterScopeTest extends TestCase
{
    use ActsAsPermissiveAdmin;
    use CreatesClearFileFixtures;
    use RecordsDomainEvents;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stubHasher();
        $this->setUpClearFileFixtures();
        $this->recordEvents(EntityCreated::class);
    }

    /** @param array<string, mixed> $attributes */
    private function record(array $attributes): EntityRecord
    {
        return new EntityRecord(new ClearDocDefinition(), $attributes);
    }

    public function test_mutator_create_without_outer_scope_persists_the_uploaded_file(): void
    {
        $mutator = resolve(EntityMutatorInterface::class);

        $created = $mutator->create($this->record([
            'name' => 'Seeded',
            'optional_doc' => UploadedFile::fake()->create('seed.pdf', 5, 'application/pdf'),
        ]));

        $path = DB::table('clear_docs')->where('id', $created->id())->value('optional_doc');
        self::assertIsString($path);
        Storage::disk('archive')->assertExists($path);
    }

    public function test_mutator_create_that_fails_without_outer_scope_removes_the_uploaded_file(): void
    {
        $mutator = resolve(EntityMutatorInterface::class);

        try {
            $mutator->create($this->record([
                'name' => null,
                'optional_doc' => UploadedFile::fake()->create('seed.pdf', 5, 'application/pdf'),
            ]));
            self::fail('The NOT NULL violation must surface as a domain validation error.');
        } catch (ValidationException) {
            // expected
        }

        self::assertSame([], $this->archiveFiles());
    }

    public function test_mutator_update_without_outer_scope_replaces_the_file_after_commit(): void
    {
        Storage::disk('archive')->put('clear/old.bin', 'old');
        $doc = ClearDoc::create(['name' => 'Doc', 'optional_doc' => 'clear/old.bin']);
        $mutator = resolve(EntityMutatorInterface::class);

        $mutator->update(
            new EntityKey($doc->id, 'int'),
            $this->record(['name' => 'Doc', 'optional_doc' => UploadedFile::fake()->create('new.pdf', 5, 'application/pdf')]),
        );

        Storage::disk('archive')->assertMissing('clear/old.bin');
        self::assertCount(1, $this->archiveFiles());
    }

    public function test_create_use_case_without_outer_scope_dispatches_the_created_event_immediately(): void
    {
        $definition = new ClearDocDefinition();

        $created = resolve(UseCaseFactory::class)->createRecord($definition)->execute($this->record([
            'name' => 'Seeded',
            'required_doc' => UploadedFile::fake()->create('required.pdf', 5, 'application/pdf'),
            'roc_doc' => UploadedFile::fake()->create('roc.pdf', 5, 'application/pdf'),
        ]));

        self::assertSame([$created->id()], $this->seenKeys(EntityCreated::class));
        self::assertTrue($this->allSeenAfterCommit());
    }
}
