<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Infrastructure;

use BlackParadise\CoreAdmin\Domain\Query\Criteria;
use BlackParadise\CoreAdmin\Domain\Repositories\EntityRepositoryInterface;
use BlackParadise\CoreAdmin\Domain\ValueObjects\EntityKey;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\CreatesTestMorphFileTables;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestAccount;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestAccountDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestMorphedFile;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

/**
 * Integration tests for Bug #12 — MorphFileField eager-loaded in list/find.
 *
 * Before the fix, MorphFileField was NOT in the eager-load set because it does
 * not implement RelationFieldContract. The list view showed "—" for morph-file
 * cells. After the fix, getMorphName() is added to the eager-load list.
 */
final class EloquentEntityRepositoryMorphFileTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTestMorphFileTables;

    private EntityRepositoryInterface $repository;
    private TestAccountDefinition $definition;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMorphFileFixtures();

        Schema::create('users', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('password');
            $t->rememberToken();
            $t->timestamps();
        });

        $this->repository = $this->app->make(EntityRepositoryInterface::class);
        $this->definition = new TestAccountDefinition();
    }

    public function test_list_eager_loads_morph_file_relation(): void
    {
        $account = TestAccount::create(['name' => 'Alice']);
        TestMorphedFile::create([
            'fileable_type' => TestAccount::class,
            'fileable_id'   => $account->id,
            'type'          => 'avatar',
            'name'          => 'avatar.jpg',
            'path'          => 'avatars/avatar.jpg',
        ]);

        $result = $this->repository->list($this->definition, new Criteria());

        self::assertCount(1, $result->items);

        // The morph relation must be loaded — relation data must not be null.
        $record   = $result->items[0];
        $filesRel = $record->relation('files');

        self::assertNotNull(
            $filesRel,
            'Morph-file relation "files" was not eager-loaded for list.',
        );
    }

    public function test_find_eager_loads_morph_file_relation(): void
    {
        $account = TestAccount::create(['name' => 'Bob']);
        TestMorphedFile::create([
            'fileable_type' => TestAccount::class,
            'fileable_id'   => $account->id,
            'type'          => 'avatar',
            'name'          => 'bob.jpg',
            'path'          => 'avatars/bob.jpg',
        ]);

        $key    = new EntityKey($account->id, 'int');
        $record = $this->repository->find($this->definition, $key);

        self::assertNotNull($record);

        $filesRel = $record->relation('files');
        self::assertNotNull(
            $filesRel,
            'Morph-file relation "files" was not eager-loaded for find.',
        );
    }

    public function test_list_returns_empty_relation_when_no_morph_files(): void
    {
        TestAccount::create(['name' => 'Charlie']);

        $result = $this->repository->list($this->definition, new Criteria());

        self::assertCount(1, $result->items);

        $record   = $result->items[0];
        $filesRel = $record->relation('files');

        // Relation key must exist (eager-loaded), value is empty array (no files).
        self::assertNotNull($filesRel);
        self::assertIsArray($filesRel);
        self::assertEmpty($filesRel);
    }
}
