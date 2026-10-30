<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Characterization;

use BlackParadise\CoreAdmin\Domain\Contracts\Fields\FieldContract;
use BlackParadise\CoreAdmin\Domain\Entity\EntityRecord;
use BlackParadise\CoreAdmin\Domain\Fields\FileField;
use BlackParadise\CoreAdmin\Domain\Fields\HashedField;
use BlackParadise\CoreAdmin\Domain\Fields\HasManyField;
use BlackParadise\CoreAdmin\Domain\Fields\ImageField;
use BlackParadise\CoreAdmin\Domain\Fields\MorphManyField;
use BlackParadise\CoreAdmin\Domain\Fields\MorphToField;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\CoreAdmin\Domain\Fields\TranslatableField;
use BlackParadise\CoreAdmin\Domain\Mutators\EntityMutatorInterface;
use BlackParadise\CoreAdmin\Domain\ValueObjects\EntityKey;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\CreatesRelationFixtures;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\CreatesTestMorphFileTables;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\StubsValueHasher;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestAccount;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestAccountDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestArticle;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestArticleDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestComment;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItem;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItemDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItemWithPivotDataDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItemWithRelationsDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestMorphComment;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestTag;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/** Host with two column-file fields (file + image) — characterization only. */
final class CharDoc extends Model
{
    protected $table = 'char_docs';
    protected $guarded = [];
}

/** Plain (no array cast) host with a JSON-text translatable column. */
final class CharPlainArticle extends Model
{
    protected $table = 'char_plain_articles';
    protected $guarded = [];
}

final class CharMorphTarget extends Model
{
    protected $table = 'char_morph_targets';
    protected $guarded = [];
}

final class CharMorphOwner extends Model
{
    protected $table = 'char_morph_owners';
    protected $guarded = [];
}

/**
 * Task 0.3 / A18 baseline — CHARACTERIZATION of EloquentEntityMutator
 * (+ RelationWriter + MorphFilePersister) per built-in field type.
 *
 * Snapshots the CURRENT DB state and `Storage::fake` disk state after
 * create / update / delete for: column, hashed, translatable, column file/image,
 * morphTo, morph file, belongsToMany, hasOne, hasMany (replace/merge/append),
 * morphMany. Every test is GREEN today; W2 (FieldPersister strategies) must
 * keep them green. Tests marked `BUG:` flip deliberately in W2.
 */
final class MutatorWritesPerFieldTypeTest extends TestCase
{
    use RefreshDatabase;
    use StubsValueHasher;
    use CreatesRelationFixtures;
    use CreatesTestMorphFileTables;

    private EntityMutatorInterface $mutator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stubValueHasher(); // must be first
        Storage::fake('public');

        Schema::create('test_items', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->timestamps();
        });
        Schema::create('test_articles', function (Blueprint $table): void {
            $table->id();
            $table->json('title')->nullable();
            $table->timestamps();
        });
        Schema::create('char_plain_articles', function (Blueprint $table): void {
            $table->id();
            $table->text('title')->nullable();
            $table->timestamps();
        });
        Schema::create('char_docs', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('attachment')->nullable();
            $table->string('photo')->nullable();
            $table->timestamps();
        });
        Schema::create('char_morph_targets', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->timestamps();
        });
        Schema::create('char_morph_owners', function (Blueprint $table): void {
            $table->id();
            $table->string('body');
            $table->morphs('subject');
            $table->timestamps();
        });
        $this->setUpRelationFixtures();
        $this->setUpMorphFileFixtures();

        $this->mutator = $this->app->make(EntityMutatorInterface::class);
    }

    // ------------------------------------------------------------------
    // helpers
    // ------------------------------------------------------------------

    /** @param list<FieldContract> $fields */
    private function definition(string $model, string $name, array $fields): EntityDefinition
    {
        return new class ($model, $name, $fields) extends EntityDefinition {
            /** @param list<FieldContract> $fieldList */
            public function __construct(string $model, private readonly string $entityName, private readonly array $fieldList)
            {
                $this->model = $model;
            }

            public function resolveName(): string
            {
                return $this->entityName;
            }

            public function fields(): array
            {
                return $this->fieldList;
            }
        };
    }

    private function create(EntityDefinition $definition, array $attributes): EntityRecord
    {
        /** @var EntityRecord */
        return $this->mutator->create(new EntityRecord($definition, $attributes));
    }

    private function update(EntityDefinition $definition, int $id, array $attributes): EntityRecord
    {
        /** @var EntityRecord */
        return $this->mutator->update(new EntityKey($id), new EntityRecord($definition, $attributes));
    }

    private function upload(string $name, string $mime = 'application/pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 10, $mime);
    }

    /** @return list<string> */
    private function diskFiles(): array
    {
        $files = Storage::disk('public')->allFiles();
        sort($files);

        return $files;
    }

    // ------------------------------------------------------------------
    // column
    // ------------------------------------------------------------------

    public function test_column_create_update_delete_roundtrip(): void
    {
        $def = new TestItemDefinition();

        $created = $this->create($def, ['name' => 'Alice', 'email' => 'a@e.com']);
        $this->assertDatabaseHas('test_items', ['id' => $created->id(), 'name' => 'Alice', 'email' => 'a@e.com']);

        $this->update($def, $created->id(), ['name' => 'Alicia']);
        $this->assertDatabaseHas('test_items', ['id' => $created->id(), 'name' => 'Alicia', 'email' => 'a@e.com']);

        $this->assertTrue($this->mutator->delete(new EntityKey($created->id()), $def));
        $this->assertDatabaseCount('test_items', 0);
    }

    public function test_column_ignores_undeclared_attributes_on_create_and_update(): void
    {
        $def = new TestItemDefinition();

        $created = $this->create($def, ['name' => 'Alice', 'email' => 'a@e.com', 'password' => 'leak']);
        $this->update($def, $created->id(), ['name' => 'Alicia', 'password' => 'leak2']);

        $this->assertNull(DB::table('test_items')->where('id', $created->id())->value('password'));
    }

    // ------------------------------------------------------------------
    // hashed
    // ------------------------------------------------------------------

    public function test_hashed_create_stores_hash_not_plain_text(): void
    {
        $def = $this->definition(TestItem::class, 'char_hashed', [TextField::make('name'), HashedField::make('password')]);

        $created = $this->create($def, ['name' => 'Alice', 'password' => 'secret']);

        $this->assertSame('stub_hashed_secret', DB::table('test_items')->where('id', $created->id())->value('password'));
    }

    public function test_hashed_update_with_empty_value_keeps_existing_hash(): void
    {
        $def = $this->definition(TestItem::class, 'char_hashed', [TextField::make('name'), HashedField::make('password')]);
        $created = $this->create($def, ['name' => 'Alice', 'password' => 'secret']);

        $this->update($def, $created->id(), ['name' => 'Alice', 'password' => '']);
        $this->assertSame('stub_hashed_secret', DB::table('test_items')->where('id', $created->id())->value('password'));

        $this->update($def, $created->id(), ['name' => 'Alice', 'password' => null]);
        $this->assertSame('stub_hashed_secret', DB::table('test_items')->where('id', $created->id())->value('password'));
    }

    public function test_hashed_update_with_new_value_rehashes_and_with_already_hashed_value_stores_as_is(): void
    {
        $def = $this->definition(TestItem::class, 'char_hashed', [TextField::make('name'), HashedField::make('password')]);
        $created = $this->create($def, ['name' => 'Alice', 'password' => 'secret']);

        $this->update($def, $created->id(), ['name' => 'Alice', 'password' => 'newpass']);
        $this->assertSame('stub_hashed_newpass', DB::table('test_items')->where('id', $created->id())->value('password'));

        $this->update($def, $created->id(), ['name' => 'Alice', 'password' => 'stub_hashed_roundtrip']);
        $this->assertSame('stub_hashed_roundtrip', DB::table('test_items')->where('id', $created->id())->value('password'));
    }

    // ------------------------------------------------------------------
    // translatable
    // ------------------------------------------------------------------

    public function test_translatable_with_array_cast_model_is_stored_as_single_encoded_json(): void
    {
        $created = $this->create(new TestArticleDefinition(), ['title' => ['en' => 'Hello', 'uk' => 'Привіт']]);

        $raw = DB::table('test_articles')->where('id', $created->id())->value('title');

        $this->assertSame(['en' => 'Hello', 'uk' => 'Привіт'], json_decode((string) $raw, true));
        $this->assertSame(['en' => 'Hello', 'uk' => 'Привіт'], TestArticle::find($created->id())->title);
    }

    public function test_translatable_with_plain_model_is_json_encoded_with_unescaped_unicode(): void
    {
        $def = $this->definition(CharPlainArticle::class, 'char_plain_article', [TranslatableField::make('title')]);

        $created = $this->create($def, ['title' => ['en' => 'Hello', 'uk' => 'Привіт']]);

        $this->assertSame(
            '{"en":"Hello","uk":"Привіт"}',
            DB::table('char_plain_articles')->where('id', $created->id())->value('title'),
        );
    }

    public function test_translatable_update_replaces_whole_json_value(): void
    {
        $created = $this->create(new TestArticleDefinition(), ['title' => ['en' => 'Hello', 'uk' => 'Привіт']]);

        $this->update(new TestArticleDefinition(), $created->id(), ['title' => ['en' => 'Bye']]);

        $this->assertSame(['en' => 'Bye'], TestArticle::find($created->id())->title);
    }

    public function test_translatable_delete_removes_row(): void
    {
        $created = $this->create(new TestArticleDefinition(), ['title' => ['en' => 'Hello']]);

        $this->assertTrue($this->mutator->delete(new EntityKey($created->id()), new TestArticleDefinition()));
        $this->assertDatabaseCount('test_articles', 0);
    }

    // ------------------------------------------------------------------
    // column file / image
    // ------------------------------------------------------------------

    private function docDefinition(): EntityDefinition
    {
        return $this->definition(CharDoc::class, 'char_doc', [
            TextField::make('name'),
            FileField::make('attachment')->disk('public'),
            ImageField::make('photo')->disk('public')->directory('photos'),
        ]);
    }

    public function test_column_file_create_stores_path_and_writes_file_to_field_disk(): void
    {
        $created = $this->create($this->docDefinition(), [
            'name' => 'D',
            'attachment' => $this->upload('a.pdf'),
            'photo' => $this->upload('p.png', 'image/png'),
        ]);

        $row = DB::table('char_docs')->where('id', $created->id())->first();

        $this->assertStringStartsWith('char_doc/attachment/', $row->attachment);
        $this->assertStringStartsWith('photos/', $row->photo);
        $this->assertSame([$row->attachment, $row->photo], array_values(array_filter($this->diskFiles(), fn(string $f): bool => in_array($f, [$row->attachment, $row->photo], true))));
        $this->assertCount(2, $this->diskFiles());
    }

    public function test_column_file_update_without_key_keeps_stored_file(): void
    {
        $created = $this->create($this->docDefinition(), ['name' => 'D', 'attachment' => $this->upload('a.pdf')]);
        $before = DB::table('char_docs')->where('id', $created->id())->value('attachment');

        $this->update($this->docDefinition(), $created->id(), ['name' => 'D2']);

        $this->assertSame($before, DB::table('char_docs')->where('id', $created->id())->value('attachment'));
        $this->assertSame([$before], $this->diskFiles());
    }

    public function test_column_file_update_with_new_upload_replaces_path_and_removes_old_file(): void
    {
        $created = $this->create($this->docDefinition(), ['name' => 'D', 'attachment' => $this->upload('a.pdf')]);
        $old = DB::table('char_docs')->where('id', $created->id())->value('attachment');

        $this->update($this->docDefinition(), $created->id(), ['name' => 'D', 'attachment' => $this->upload('b.pdf')]);

        $new = DB::table('char_docs')->where('id', $created->id())->value('attachment');
        $this->assertNotSame($old, $new);
        $this->assertSame([$new], $this->diskFiles());
    }

    public function test_column_file_update_with_empty_value_does_not_clear_stored_file(): void
    {
        $created = $this->create($this->docDefinition(), ['name' => 'D', 'attachment' => $this->upload('a.pdf')]);
        $before = DB::table('char_docs')->where('id', $created->id())->value('attachment');

        $this->update($this->docDefinition(), $created->id(), ['name' => 'D', 'attachment' => '']);
        $this->update($this->docDefinition(), $created->id(), ['name' => 'D', 'attachment' => null]);

        $this->assertSame($before, DB::table('char_docs')->where('id', $created->id())->value('attachment'));
        $this->assertSame([$before], $this->diskFiles());
    }

    public function test_column_file_update_with_clear_flag_nulls_column_and_removes_file(): void
    {
        // Flipped snapshot (A13 / A53): `{field}__clear=1` clears an optional file/image column and removes the file.
        $created = $this->create($this->docDefinition(), [
            'name' => 'D',
            'attachment' => $this->upload('a.pdf'),
            'photo' => $this->upload('p.png', 'image/png'),
        ]);
        $photo = DB::table('char_docs')->where('id', $created->id())->value('photo');

        $this->update($this->docDefinition(), $created->id(), ['name' => 'D', 'attachment__clear' => '1']);

        self::assertNull(DB::table('char_docs')->where('id', $created->id())->value('attachment'));
        self::assertSame($photo, DB::table('char_docs')->where('id', $created->id())->value('photo'));
        self::assertSame([$photo], $this->diskFiles());
    }

    public function test_column_file_delete_removes_row_and_all_associated_files(): void
    {
        $created = $this->create($this->docDefinition(), [
            'name' => 'D',
            'attachment' => $this->upload('a.pdf'),
            'photo' => $this->upload('p.png', 'image/png'),
        ]);

        $this->assertTrue($this->mutator->delete(new EntityKey($created->id()), $this->docDefinition()));

        $this->assertDatabaseCount('char_docs', 0);
        $this->assertSame([], $this->diskFiles());
    }

    // ------------------------------------------------------------------
    // morphTo
    // ------------------------------------------------------------------

    private function morphToDefinition(): EntityDefinition
    {
        return $this->definition(CharMorphOwner::class, 'char_morph_owner', [
            TextField::make('body'),
            MorphToField::make('subject', CharMorphTarget::class)
                ->morphTypes([CharMorphTarget::class => ['label' => 'Target', 'display' => 'title']]),
        ]);
    }

    public function test_morph_to_create_and_update_write_type_and_id_columns(): void
    {
        $a = CharMorphTarget::create(['title' => 'A']);
        $b = CharMorphTarget::create(['title' => 'B']);

        $created = $this->create($this->morphToDefinition(), [
            'body' => 'x', 'subject_type' => CharMorphTarget::class, 'subject_id' => $a->id,
        ]);
        $this->assertDatabaseHas('char_morph_owners', [
            'id' => $created->id(), 'subject_type' => CharMorphTarget::class, 'subject_id' => $a->id,
        ]);

        $this->update($this->morphToDefinition(), $created->id(), [
            'body' => 'x', 'subject_type' => CharMorphTarget::class, 'subject_id' => $b->id,
        ]);
        $this->assertDatabaseHas('char_morph_owners', ['id' => $created->id(), 'subject_id' => $b->id]);
    }

    public function test_morph_to_type_is_normalised_through_morph_map_alias(): void
    {
        Relation::morphMap(['char_target' => CharMorphTarget::class]);
        $a = CharMorphTarget::create(['title' => 'A']);

        try {
            $created = $this->create($this->morphToDefinition(), [
                'body' => 'x', 'subject_type' => CharMorphTarget::class, 'subject_id' => $a->id,
            ]);
        } finally {
            Relation::morphMap([], false);
        }

        $this->assertSame('char_target', DB::table('char_morph_owners')->where('id', $created->id())->value('subject_type'));
    }

    public function test_morph_to_delete_removes_owner_row_and_leaves_target(): void
    {
        $a = CharMorphTarget::create(['title' => 'A']);
        $created = $this->create($this->morphToDefinition(), [
            'body' => 'x', 'subject_type' => CharMorphTarget::class, 'subject_id' => $a->id,
        ]);

        $this->mutator->delete(new EntityKey($created->id()), $this->morphToDefinition());

        $this->assertDatabaseCount('char_morph_owners', 0);
        $this->assertDatabaseCount('char_morph_targets', 1);
    }

    // ------------------------------------------------------------------
    // morph file
    // ------------------------------------------------------------------

    public function test_morph_file_create_writes_row_and_disk_file_per_stored_type(): void
    {
        $created = $this->create(new TestAccountDefinition(), [
            'name' => 'Acme',
            'avatar' => $this->upload('ava.png', 'image/png'),
            'image' => $this->upload('img.png', 'image/png'),
        ]);

        $rows = DB::table('test_morphed_files')->where('fileable_id', $created->id())->orderBy('type')->get();

        $this->assertSame(['avatar', 'image'], $rows->pluck('type')->all());
        $this->assertSame([TestAccount::class, TestAccount::class], $rows->pluck('fileable_type')->all());
        $this->assertSame(['ava.png', 'img.png'], $rows->pluck('name')->all());
        $this->assertStringStartsWith('avatars/', $rows[0]->path);
        $this->assertStringStartsWith('images/', $rows[1]->path);
        $this->assertSame($rows->pluck('path')->sort()->values()->all(), $this->diskFiles());
    }

    public function test_morph_file_update_replaces_row_and_removes_old_disk_file(): void
    {
        $created = $this->create(new TestAccountDefinition(), ['name' => 'Acme', 'avatar' => $this->upload('ava.png', 'image/png')]);
        $old = DB::table('test_morphed_files')->where('type', 'avatar')->value('path');

        $this->update(new TestAccountDefinition(), $created->id(), ['name' => 'Acme', 'avatar' => $this->upload('new.png', 'image/png')]);

        $rows = DB::table('test_morphed_files')->where('type', 'avatar')->get();
        $this->assertCount(1, $rows);
        $this->assertSame('new.png', $rows[0]->name);
        $this->assertNotSame($old, $rows[0]->path);
        $this->assertSame([$rows[0]->path], $this->diskFiles());
    }

    public function test_morph_file_update_without_key_keeps_existing(): void
    {
        $created = $this->create(new TestAccountDefinition(), ['name' => 'Acme', 'avatar' => $this->upload('ava.png', 'image/png')]);
        $path = DB::table('test_morphed_files')->value('path');

        $this->update(new TestAccountDefinition(), $created->id(), ['name' => 'Renamed']);

        $this->assertSame($path, DB::table('test_morphed_files')->value('path'));
        $this->assertSame([$path], $this->diskFiles());
    }

    public function test_morph_file_update_with_empty_value_does_not_clear_existing(): void
    {
        $created = $this->create(new TestAccountDefinition(), ['name' => 'Acme', 'avatar' => $this->upload('ava.png', 'image/png')]);
        $path = DB::table('test_morphed_files')->value('path');

        $this->update(new TestAccountDefinition(), $created->id(), ['name' => 'Acme', 'avatar' => null]);
        $this->update(new TestAccountDefinition(), $created->id(), ['name' => 'Acme', 'avatar' => '']);

        $this->assertDatabaseCount('test_morphed_files', 1);
        $this->assertSame([$path], $this->diskFiles());
    }

    public function test_morph_file_update_with_clear_flag_deletes_row_and_file(): void
    {
        // Flipped snapshot (A13): `{field}__clear=1` deletes the morph row and the stored file.
        $created = $this->create(new TestAccountDefinition(), [
            'name' => 'Acme',
            'avatar' => $this->upload('ava.png', 'image/png'),
            'image' => $this->upload('img.png', 'image/png'),
        ]);
        $image = DB::table('test_morphed_files')->where('type', 'image')->value('path');

        $this->update(new TestAccountDefinition(), $created->id(), ['name' => 'Acme', 'avatar__clear' => '1']);

        $this->assertDatabaseMissing('test_morphed_files', ['type' => 'avatar']);
        $this->assertDatabaseHas('test_morphed_files', ['type' => 'image', 'path' => $image]);
        self::assertSame([$image], $this->diskFiles());
    }

    public function test_morph_file_delete_removes_rows_and_disk_files(): void
    {
        $created = $this->create(new TestAccountDefinition(), [
            'name' => 'Acme',
            'avatar' => $this->upload('ava.png', 'image/png'),
            'image' => $this->upload('img.png', 'image/png'),
        ]);

        $this->assertTrue($this->mutator->delete(new EntityKey($created->id()), new TestAccountDefinition()));

        $this->assertDatabaseCount('test_accounts', 0);
        $this->assertDatabaseCount('test_morphed_files', 0);
        $this->assertSame([], $this->diskFiles());
    }

    public function test_morph_file_delete_leaves_other_hosts_files_untouched(): void
    {
        $a = $this->create(new TestAccountDefinition(), ['name' => 'A', 'avatar' => $this->upload('a.png', 'image/png')]);
        $b = $this->create(new TestAccountDefinition(), ['name' => 'B', 'avatar' => $this->upload('b.png', 'image/png')]);

        $this->mutator->delete(new EntityKey($a->id()), new TestAccountDefinition());

        $this->assertSame([$b->id()], DB::table('test_morphed_files')->pluck('fileable_id')->map(fn($v): int => (int) $v)->all());
        $this->assertCount(1, $this->diskFiles());
    }

    // ------------------------------------------------------------------
    // belongsToMany
    // ------------------------------------------------------------------

    public function test_belongs_to_many_create_syncs_ids_and_update_re_syncs(): void
    {
        $t1 = TestTag::create(['name' => 'T1']);
        $t2 = TestTag::create(['name' => 'T2']);
        $t3 = TestTag::create(['name' => 'T3']);
        $def = new TestItemWithRelationsDefinition();

        $created = $this->create($def, ['name' => 'I', 'tags' => [$t1->id, $t2->id]]);
        $this->assertEqualsCanonicalizing([$t1->id, $t2->id], DB::table('test_item_tag')->where('test_item_id', $created->id())->pluck('test_tag_id')->all());

        $this->update($def, $created->id(), ['name' => 'I', 'tags' => [$t2->id, $t3->id]]);
        $this->assertEqualsCanonicalizing([$t2->id, $t3->id], DB::table('test_item_tag')->where('test_item_id', $created->id())->pluck('test_tag_id')->all());
    }

    public function test_belongs_to_many_update_without_key_keeps_pivots_and_with_empty_array_detaches_all(): void
    {
        $t1 = TestTag::create(['name' => 'T1']);
        $def = new TestItemWithRelationsDefinition();
        $created = $this->create($def, ['name' => 'I', 'tags' => [$t1->id]]);

        $this->update($def, $created->id(), ['name' => 'I2']);
        $this->assertDatabaseCount('test_item_tag', 1);

        $this->update($def, $created->id(), ['name' => 'I2', 'tags' => []]);
        $this->assertDatabaseCount('test_item_tag', 0);
    }

    public function test_belongs_to_many_applies_static_pivot_data(): void
    {
        $t1 = TestTag::create(['name' => 'T1']);

        $created = $this->create(new TestItemWithPivotDataDefinition(), ['name' => 'I', 'email' => 'e', 'tags' => [$t1->id]]);

        $this->assertSame(1, (int) DB::table('test_item_tag')->where('test_item_id', $created->id())->value('approved'));
    }

    public function test_belongs_to_many_accepts_pre_shaped_payload_with_per_record_pivot_attrs(): void
    {
        $t1 = TestTag::create(['name' => 'T1']);

        $created = $this->create(new TestItemWithRelationsDefinition(), ['name' => 'I', 'tags' => [$t1->id => ['approved' => true]]]);

        $this->assertSame(1, (int) DB::table('test_item_tag')->where('test_item_id', $created->id())->value('approved'));
    }

    public function test_belongs_to_many_delete_detaches_pivots_and_keeps_targets(): void
    {
        $t1 = TestTag::create(['name' => 'T1']);
        $def = new TestItemWithRelationsDefinition();
        $created = $this->create($def, ['name' => 'I', 'tags' => [$t1->id]]);

        $this->mutator->delete(new EntityKey($created->id()), $def);

        $this->assertDatabaseCount('test_item_tag', 0);
        $this->assertDatabaseCount('test_tags', 1);
    }

    // ------------------------------------------------------------------
    // hasOne
    // ------------------------------------------------------------------

    public function test_has_one_create_then_update_updates_existing_child_in_place(): void
    {
        $def = new TestItemWithRelationsDefinition();

        $created = $this->create($def, ['name' => 'I', 'profile' => ['bio' => 'one']]);
        $profileId = DB::table('test_profiles')->value('id');
        $this->assertDatabaseHas('test_profiles', ['test_item_id' => $created->id(), 'bio' => 'one']);

        $this->update($def, $created->id(), ['name' => 'I', 'profile' => ['bio' => 'two']]);

        $this->assertDatabaseCount('test_profiles', 1);
        $this->assertSame('two', DB::table('test_profiles')->where('id', $profileId)->value('bio'));
    }

    public function test_has_one_explicit_empty_value_deletes_child(): void
    {
        $def = new TestItemWithRelationsDefinition();
        $created = $this->create($def, ['name' => 'I', 'profile' => ['bio' => 'one']]);

        $this->update($def, $created->id(), ['name' => 'I', 'profile' => null]);

        $this->assertDatabaseCount('test_profiles', 0);
    }

    public function test_has_one_update_without_key_keeps_child_and_host_delete_leaves_child_row(): void
    {
        $def = new TestItemWithRelationsDefinition();
        $created = $this->create($def, ['name' => 'I', 'profile' => ['bio' => 'one']]);

        $this->update($def, $created->id(), ['name' => 'I2']);
        $this->assertDatabaseCount('test_profiles', 1);

        $this->mutator->delete(new EntityKey($created->id()), $def);
        $this->assertDatabaseCount('test_items', 0);
        $this->assertDatabaseCount('test_profiles', 1);
    }

    // ------------------------------------------------------------------
    // hasMany — replace / merge / append
    // ------------------------------------------------------------------

    private function commentsDefinition(string $strategy): EntityDefinition
    {
        return $this->definition(TestItem::class, 'char_items_' . $strategy, [
            TextField::make('name'),
            HasManyField::make('comments', TestComment::class)->strategy($strategy),
        ]);
    }

    /** @return list<string> */
    private function commentTexts(): array
    {
        return DB::table('test_comments')->orderBy('id')->pluck('text')->all();
    }

    public function test_has_many_replace_is_default_and_deletes_then_recreates_children(): void
    {
        $def = new TestItemWithRelationsDefinition();
        $created = $this->create($def, ['name' => 'I', 'comments' => [['text' => 'c1'], ['text' => 'c2']]]);
        $oldIds = DB::table('test_comments')->pluck('id')->all();

        $this->update($def, $created->id(), ['name' => 'I', 'comments' => [['text' => 'c3']]]);

        $this->assertSame(['c3'], $this->commentTexts());
        $this->assertNotContains(DB::table('test_comments')->value('id'), $oldIds);
    }

    public function test_has_many_replace_with_empty_array_removes_all_children_and_missing_key_keeps_them(): void
    {
        $def = new TestItemWithRelationsDefinition();
        $created = $this->create($def, ['name' => 'I', 'comments' => [['text' => 'c1']]]);

        $this->update($def, $created->id(), ['name' => 'I2']);
        $this->assertSame(['c1'], $this->commentTexts());

        $this->update($def, $created->id(), ['name' => 'I2', 'comments' => []]);
        $this->assertSame([], $this->commentTexts());
    }

    public function test_has_many_replace_normalises_empty_string_child_attrs_to_null(): void
    {
        $def = new TestItemWithRelationsDefinition();
        Schema::table('test_comments', function (Blueprint $t): void {
            $t->string('note')->nullable();
        });

        $created = $this->create($def, ['name' => 'I', 'comments' => [['text' => 'c1', 'note' => '']]]);

        $this->assertNull(DB::table('test_comments')->where('test_item_id', $created->id())->value('note'));
    }

    public function test_has_many_merge_updates_by_id_creates_new_and_deletes_missing(): void
    {
        $def = $this->commentsDefinition('merge');
        $created = $this->create($def, ['name' => 'I', 'comments' => [['text' => 'c1'], ['text' => 'c2']]]);
        $ids = DB::table('test_comments')->orderBy('id')->pluck('id')->all();

        $this->update($def, $created->id(), ['name' => 'I', 'comments' => [
            ['id' => $ids[0], 'text' => 'c1-edited'],
            ['text' => 'c3'],
        ]]);

        $this->assertSame(['c1-edited', 'c3'], $this->commentTexts());
        $this->assertSame($ids[0], DB::table('test_comments')->orderBy('id')->value('id'));
        $this->assertNotContains($ids[1], DB::table('test_comments')->pluck('id')->all());
    }

    public function test_has_many_merge_strips_foreign_id_and_lets_db_assign(): void
    {
        $other = TestComment::create(['test_item_id' => 999, 'text' => 'foreign']);
        $def = $this->commentsDefinition('merge');
        $created = $this->create($def, ['name' => 'I']);

        $this->update($def, $created->id(), ['name' => 'I', 'comments' => [['id' => $other->id, 'text' => 'sneaky']]]);

        $this->assertSame('foreign', TestComment::find($other->id)->text);
        $this->assertSame(['sneaky'], DB::table('test_comments')->where('test_item_id', $created->id())->pluck('text')->all());
    }

    public function test_has_many_append_only_adds_and_never_deletes(): void
    {
        $def = $this->commentsDefinition('append');
        $created = $this->create($def, ['name' => 'I', 'comments' => [['text' => 'c1']]]);

        $this->update($def, $created->id(), ['name' => 'I', 'comments' => [['text' => 'c2']]]);
        $this->update($def, $created->id(), ['name' => 'I', 'comments' => []]);

        $this->assertSame(['c1', 'c2'], $this->commentTexts());
    }

    public function test_has_many_delete_leaves_child_rows_in_place(): void
    {
        $def = new TestItemWithRelationsDefinition();
        $created = $this->create($def, ['name' => 'I', 'comments' => [['text' => 'c1']]]);

        $this->mutator->delete(new EntityKey($created->id()), $def);

        $this->assertDatabaseCount('test_items', 0);
        $this->assertSame(['c1'], $this->commentTexts());
    }

    // ------------------------------------------------------------------
    // morphMany
    // ------------------------------------------------------------------

    public function test_morph_many_create_writes_children_with_morph_columns_and_update_replaces(): void
    {
        $def = new TestItemWithRelationsDefinition();
        $created = $this->create($def, ['name' => 'I', 'morphComments' => [['text' => 'm1'], ['text' => 'm2']]]);

        $rows = DB::table('test_morph_comments')->orderBy('id')->get();
        $this->assertSame(['m1', 'm2'], $rows->pluck('text')->all());
        $this->assertSame([TestItem::class, TestItem::class], $rows->pluck('commentable_type')->all());
        $this->assertSame([$created->id(), $created->id()], $rows->pluck('commentable_id')->map(fn($v): int => (int) $v)->all());

        $this->update($def, $created->id(), ['name' => 'I', 'morphComments' => [['text' => 'm3']]]);
        $this->assertSame(['m3'], DB::table('test_morph_comments')->pluck('text')->all());
    }

    public function test_morph_many_append_strategy_only_adds_children(): void
    {
        $def = $this->definition(TestItem::class, 'char_items_morph_append', [
            TextField::make('name'),
            MorphManyField::make('morphComments', TestMorphComment::class)->strategy('append'),
        ]);
        $created = $this->create($def, ['name' => 'I', 'morphComments' => [['text' => 'm1']]]);

        $this->update($def, $created->id(), ['name' => 'I', 'morphComments' => [['text' => 'm2']]]);

        $this->assertSame(['m1', 'm2'], DB::table('test_morph_comments')->orderBy('id')->pluck('text')->all());
    }

    public function test_morph_many_delete_leaves_child_rows_in_place(): void
    {
        $def = new TestItemWithRelationsDefinition();
        $created = $this->create($def, ['name' => 'I', 'morphComments' => [['text' => 'm1']]]);

        $this->mutator->delete(new EntityKey($created->id()), $def);

        $this->assertDatabaseCount('test_morph_comments', 1);
    }

    // ------------------------------------------------------------------
    // misc
    // ------------------------------------------------------------------

    public function test_delete_returns_false_for_missing_record(): void
    {
        $this->assertFalse($this->mutator->delete(new EntityKey(999), new TestItemDefinition()));
    }
}
