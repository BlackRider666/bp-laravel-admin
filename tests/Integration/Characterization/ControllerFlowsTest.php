<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Characterization;

use BlackParadise\CoreAdmin\Domain\Contracts\Auth\AuthorizationProviderContract;
use BlackParadise\CoreAdmin\Domain\Fields\BelongsToField;
use BlackParadise\CoreAdmin\Domain\Fields\FileField;
use BlackParadise\CoreAdmin\Domain\Fields\HasOneField;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\BootsBPAdmin;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\CreatesEmbeddedRelationFixtures;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\CreatesRelationFixtures;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\StubsValueHasher;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestAuthor;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestAuthorDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItem;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItemDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestPost;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestPublication;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestPublicationDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestPublicationItem;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestPublicationItemDefinition;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Mockery;

final class CharFlowNote extends Model
{
    protected $table = 'char_flow_notes';
    protected $guarded = [];
}

final class CharFlowHost extends Model
{
    protected $table = 'char_flow_hosts';
    protected $guarded = [];

    public function note(): HasOne
    {
        return $this->hasOne(CharFlowNote::class, 'host_id');
    }
}

/** Child with a NOT NULL column: an empty value fails the child write inside the controller transaction. */
final class CharFlowNoteDefinition extends EntityDefinition
{
    public string $model = CharFlowNote::class;

    public function resolveName(): string
    {
        return 'char_flow_note';
    }

    public function fields(): array
    {
        return [TextField::make('required_note')];
    }
}

final class CharFlowHostDefinition extends EntityDefinition
{
    public string $model = CharFlowHost::class;

    public function resolveName(): string
    {
        return 'char_flow_host';
    }

    public function fields(): array
    {
        return [
            TextField::make('name'),
            FileField::make('attachment')->disk('public'),
            HasOneField::make('note', CharFlowNote::class)->embed(CharFlowNoteDefinition::class),
        ];
    }
}

final class CharFlowRequiredItemDefinition extends EntityDefinition
{
    public string $model = TestItem::class;

    public function resolveName(): string
    {
        return 'char_flow_required_item';
    }

    public function fields(): array
    {
        return [
            TextField::make('name')->required(),
            TextField::make('email'),
        ];
    }
}

final class CharFlowOwnedPostDefinition extends EntityDefinition
{
    public string $model = TestPost::class;

    public function resolveName(): string
    {
        return 'char_flow_owned_post';
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

/**
 * Task 0.3 / A18 baseline — CHARACTERIZATION of AdminEntityController flows
 * (store / update / destroy / bulkDestroy) incl. embedded relations and the
 * outer-transaction rollback of DB rows and disk files. All GREEN today; the
 * default test presenter is JSON so statuses/envelopes are snapshotted.
 */
final class ControllerFlowsTest extends TestCase
{
    use RefreshDatabase;
    use BootsBPAdmin;
    use StubsValueHasher;
    use CreatesRelationFixtures;
    use CreatesEmbeddedRelationFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stubValueHasher(); // must be first
        Storage::fake('public');

        $this->setUpBPAdmin();
        Schema::create('test_items', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->string('email')->nullable();
            $t->timestamps();
        });
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
        Schema::create('char_flow_hosts', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->string('attachment')->nullable();
            $t->timestamps();
        });
        Schema::create('char_flow_notes', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('host_id');
            $t->string('required_note');
            $t->timestamps();
        });
        $this->setUpRelationFixtures();
        $this->setUpEmbeddedRelationFixtures();

        /** @var EntityDefinitionRegistry $registry */
        $registry = $this->app->make(EntityDefinitionRegistry::class);
        foreach ([
            new TestItemDefinition(),
            new CharFlowRequiredItemDefinition(),
            new TestPublicationDefinition(),
            new TestPublicationItemDefinition(),
            new CharFlowOwnedPostDefinition(),
            new TestAuthorDefinition(),
            new CharFlowHostDefinition(),
            new CharFlowNoteDefinition(),
        ] as $definition) {
            $registry->register($definition);
        }

        $this->app->bind(AuthorizationProviderContract::class, function (): AuthorizationProviderContract {
            $mock = Mockery::mock(AuthorizationProviderContract::class);
            $mock->shouldReceive('can')->andReturn(true);
            return $mock;
        });

        $this->actingAsAdmin();
    }

    // ------------------------------------------------------------------
    // store
    // ------------------------------------------------------------------

    public function test_store_creates_row_and_returns_201_with_record(): void
    {
        $response = $this->postJson(route('bpadmin.entity.store', ['entity' => 'test_item']), ['name' => 'Alice', 'email' => 'a@e.com']);

        $response->assertCreated();
        $this->assertDatabaseHas('test_items', ['name' => 'Alice', 'email' => 'a@e.com']);
        $this->assertSame('Alice', $response->json('data.name') ?? $response->json('name'));
    }

    public function test_store_with_missing_required_field_returns_422_and_writes_nothing(): void
    {
        $response = $this->postJson(route('bpadmin.entity.store', ['entity' => 'char_flow_required_item']), ['email' => 'a@e.com']);

        $response->assertStatus(422);
        $this->assertArrayHasKey('name', $response->json('errors'));
        $this->assertDatabaseCount('test_items', 0);
    }

    public function test_store_embedded_has_many_creates_host_and_children(): void
    {
        $response = $this->postJson(route('bpadmin.entity.store', ['entity' => 'test_publication']), [
            'name' => 'Pub',
            'items' => [
                ['title' => 'First'],
                ['title' => 'Second'],
            ],
        ]);

        $response->assertCreated();
        $this->assertSame(['First', 'Second'], DB::table('test_publication_items')->orderBy('id')->pluck('title')->all());
        $this->assertSame(DB::table('test_publications')->value('id'), DB::table('test_publication_items')->value('publication_id'));
    }

    public function test_store_owned_embedded_belongs_to_creates_author_then_post(): void
    {
        $response = $this->postJson(route('bpadmin.entity.store', ['entity' => 'char_flow_owned_post']), [
            'title' => 'P',
            'author_id' => ['name' => 'Ann', 'email' => 'ann@e.com'],
        ]);

        $response->assertCreated();
        $authorId = DB::table('test_authors')->value('id');
        $this->assertSame('Ann', DB::table('test_authors')->value('name'));
        $this->assertSame($authorId, DB::table('test_posts')->value('author_id'));
    }

    public function test_store_embedded_child_failure_rolls_back_host_row(): void
    {
        $response = $this->postJson(route('bpadmin.entity.store', ['entity' => 'char_flow_host']), [
            'name' => 'H',
            'note' => ['required_note' => ''],
        ]);

        $this->assertGreaterThanOrEqual(400, $response->status());
        $this->assertDatabaseCount('char_flow_hosts', 0);
        $this->assertDatabaseCount('char_flow_notes', 0);
    }

    public function test_store_embedded_child_failure_removes_uploaded_file_from_disk(): void
    {
        $this->postJson(route('bpadmin.entity.store', ['entity' => 'char_flow_host']), [
            'name' => 'H',
            'attachment' => UploadedFile::fake()->create('d.pdf', 10, 'application/pdf'),
            'note' => ['required_note' => ''],
        ]);

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_store_with_file_and_valid_child_persists_row_child_and_file(): void
    {
        $this->postJson(route('bpadmin.entity.store', ['entity' => 'char_flow_host']), [
            'name' => 'H',
            'attachment' => UploadedFile::fake()->create('d.pdf', 10, 'application/pdf'),
            'note' => ['required_note' => 'ok'],
        ])->assertCreated();

        $path = DB::table('char_flow_hosts')->value('attachment');
        $this->assertSame([$path], Storage::disk('public')->allFiles());
        $this->assertSame('ok', DB::table('char_flow_notes')->value('required_note'));
    }

    // ------------------------------------------------------------------
    // update
    // ------------------------------------------------------------------

    public function test_update_changes_row_and_returns_200(): void
    {
        $item = TestItem::create(['name' => 'Alice', 'email' => 'a@e.com']);

        $this->putJson(route('bpadmin.entity.update', ['entity' => 'test_item', 'id' => $item->id]), ['name' => 'Alicia'])->assertOk();

        $this->assertDatabaseHas('test_items', ['id' => $item->id, 'name' => 'Alicia', 'email' => 'a@e.com']);
    }

    public function test_update_missing_record_returns_404(): void
    {
        $this->putJson(route('bpadmin.entity.update', ['entity' => 'test_item', 'id' => 999]), ['name' => 'X'])->assertNotFound();
    }

    public function test_update_with_invalid_payload_returns_422_and_keeps_row(): void
    {
        $item = TestItem::create(['name' => 'Alice', 'email' => 'a@e.com']);

        $this->putJson(route('bpadmin.entity.update', ['entity' => 'char_flow_required_item', 'id' => $item->id]), ['name' => ''])->assertStatus(422);

        $this->assertDatabaseHas('test_items', ['id' => $item->id, 'name' => 'Alice']);
    }

    public function test_update_embedded_has_many_replaces_children(): void
    {
        $publication = TestPublication::create(['name' => 'Pub']);
        TestPublicationItem::create(['publication_id' => $publication->id, 'title' => 'Old']);

        $this->putJson(route('bpadmin.entity.update', ['entity' => 'test_publication', 'id' => $publication->id]), [
            'name' => 'Pub',
            'items' => [['title' => 'New']],
        ])->assertOk();

        $this->assertSame(['New'], DB::table('test_publication_items')->pluck('title')->all());
    }

    public function test_update_owned_embedded_belongs_to_updates_author_in_place(): void
    {
        $author = TestAuthor::create(['name' => 'Ann', 'email' => 'ann@e.com']);
        $post = TestPost::create(['title' => 'P', 'author_id' => $author->id]);

        $this->putJson(route('bpadmin.entity.update', ['entity' => 'char_flow_owned_post', 'id' => $post->id]), [
            'title' => 'P2',
            'author_id' => ['name' => 'Anna', 'email' => 'ann@e.com'],
        ])->assertOk();

        $this->assertDatabaseCount('test_authors', 1);
        $this->assertDatabaseHas('test_authors', ['id' => $author->id, 'name' => 'Anna']);
        $this->assertDatabaseHas('test_posts', ['id' => $post->id, 'title' => 'P2', 'author_id' => $author->id]);
    }

    public function test_update_embedded_child_failure_keeps_old_file_and_old_values(): void
    {
        Storage::disk('public')->put('char_flow_host/old.pdf', 'original');
        $host = CharFlowHost::create(['name' => 'H', 'attachment' => 'char_flow_host/old.pdf']);

        $response = $this->putJson(route('bpadmin.entity.update', ['entity' => 'char_flow_host', 'id' => $host->id]), [
            'name' => 'H2',
            'attachment' => UploadedFile::fake()->create('new.pdf', 10, 'application/pdf'),
            'note' => ['required_note' => ''],
        ]);

        $this->assertGreaterThanOrEqual(400, $response->status());
        $this->assertDatabaseHas('char_flow_hosts', ['id' => $host->id, 'name' => 'H', 'attachment' => 'char_flow_host/old.pdf']);
        $this->assertSame(['char_flow_host/old.pdf'], Storage::disk('public')->allFiles());
    }

    public function test_update_with_new_file_replaces_file_after_commit(): void
    {
        Storage::disk('public')->put('char_flow_host/old.pdf', 'original');
        $host = CharFlowHost::create(['name' => 'H', 'attachment' => 'char_flow_host/old.pdf']);

        $this->putJson(route('bpadmin.entity.update', ['entity' => 'char_flow_host', 'id' => $host->id]), [
            'name' => 'H2',
            'attachment' => UploadedFile::fake()->create('new.pdf', 10, 'application/pdf'),
            'note' => ['required_note' => 'ok'],
        ])->assertOk();

        $new = DB::table('char_flow_hosts')->where('id', $host->id)->value('attachment');
        $this->assertNotSame('char_flow_host/old.pdf', $new);
        $this->assertSame([$new], Storage::disk('public')->allFiles());
    }

    public function test_update_with_empty_attachment_input_keeps_existing_file(): void
    {
        Storage::disk('public')->put('char_flow_host/old.pdf', 'original');
        $host = CharFlowHost::create(['name' => 'H', 'attachment' => 'char_flow_host/old.pdf']);

        $this->putJson(route('bpadmin.entity.update', ['entity' => 'char_flow_host', 'id' => $host->id]), [
            'name' => 'H',
            'attachment' => '',
            'note' => ['required_note' => 'ok'],
        ])->assertOk();

        $this->assertDatabaseHas('char_flow_hosts', ['id' => $host->id, 'attachment' => 'char_flow_host/old.pdf']);
        $this->assertSame(['char_flow_host/old.pdf'], Storage::disk('public')->allFiles());
    }

    public function test_update_with_clear_flag_removes_optional_file_after_commit(): void
    {
        // Flipped snapshot (A13 / A53): `{field}__clear=1` clears an optional file column and deletes the file.
        Storage::disk('public')->put('char_flow_host/old.pdf', 'original');
        CharFlowHost::create(['name' => 'H', 'attachment' => 'char_flow_host/old.pdf']);
        $id = DB::table('char_flow_hosts')->value('id');

        $this->putJson(route('bpadmin.entity.update', ['entity' => 'char_flow_host', 'id' => $id]), [
            'name' => 'H',
            'attachment__clear' => '1',
            'note' => ['required_note' => 'ok'],
        ])->assertOk();

        self::assertNull(DB::table('char_flow_hosts')->where('id', $id)->value('attachment'));
        self::assertSame([], Storage::disk('public')->allFiles());
    }

    // ------------------------------------------------------------------
    // destroy
    // ------------------------------------------------------------------

    public function test_destroy_removes_row_and_returns_204(): void
    {
        $item = TestItem::create(['name' => 'Alice', 'email' => 'a@e.com']);

        $this->deleteJson(route('bpadmin.entity.destroy', ['entity' => 'test_item', 'id' => $item->id]))->assertNoContent();

        $this->assertDatabaseCount('test_items', 0);
    }

    public function test_destroy_missing_record_returns_404(): void
    {
        $this->deleteJson(route('bpadmin.entity.destroy', ['entity' => 'test_item', 'id' => 999]))->assertNotFound();
    }

    public function test_destroy_removes_column_file_from_disk(): void
    {
        Storage::disk('public')->put('char_flow_host/old.pdf', 'original');
        $host = CharFlowHost::create(['name' => 'H', 'attachment' => 'char_flow_host/old.pdf']);

        $this->deleteJson(route('bpadmin.entity.destroy', ['entity' => 'char_flow_host', 'id' => $host->id]))->assertNoContent();

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_destroy_cascades_owned_embedded_record(): void
    {
        $author = TestAuthor::create(['name' => 'Ann', 'email' => 'ann@e.com']);
        $orphan = TestAuthor::create(['name' => 'Orphan', 'email' => 'o@e.com']);
        $post = TestPost::create(['title' => 'P', 'author_id' => $author->id]);

        $this->deleteJson(route('bpadmin.entity.destroy', ['entity' => 'char_flow_owned_post', 'id' => $post->id]))->assertNoContent();

        $this->assertDatabaseCount('test_posts', 0);
        $this->assertSame([$orphan->id], DB::table('test_authors')->pluck('id')->all());
    }

    // ------------------------------------------------------------------
    // bulkDestroy
    // ------------------------------------------------------------------

    public function test_bulk_destroy_reports_deleted_and_not_found_ids(): void
    {
        $a = TestItem::create(['name' => 'A', 'email' => 'a@e.com']);
        $b = TestItem::create(['name' => 'B', 'email' => 'b@e.com']);
        $keep = TestItem::create(['name' => 'K', 'email' => 'k@e.com']);

        $response = $this->postJson(route('bpadmin.entity.bulk-destroy', ['entity' => 'test_item']), ['ids' => [$a->id, $b->id, 999]]);

        $response->assertOk()->assertJson(['deleted' => 2, 'failed_ids' => [], 'not_found_ids' => ['999']]);
        $this->assertSame([$keep->id], DB::table('test_items')->pluck('id')->all());
    }

    public function test_bulk_destroy_removes_files_of_every_deleted_record(): void
    {
        Storage::disk('public')->put('char_flow_host/a.pdf', 'a');
        Storage::disk('public')->put('char_flow_host/b.pdf', 'b');
        $a = CharFlowHost::create(['name' => 'A', 'attachment' => 'char_flow_host/a.pdf']);
        $b = CharFlowHost::create(['name' => 'B', 'attachment' => 'char_flow_host/b.pdf']);

        $this->postJson(route('bpadmin.entity.bulk-destroy', ['entity' => 'char_flow_host']), ['ids' => [$a->id, $b->id]])
            ->assertOk()
            ->assertJson(['deleted' => 2]);

        $this->assertSame([], Storage::disk('public')->allFiles());
    }
}
