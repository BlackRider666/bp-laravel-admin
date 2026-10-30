<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Feature\Http;

use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\ActsAsPermissiveAdmin;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\CreatesClearFileFixtures;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ClearFile\ClearDoc;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ClearFile\ClearMorphHost;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Task 2.1 — A11 (requiredOnCreate create 422 / update with empty part keeps file) and A12 (replace removes old). */
final class RequiredOnCreateFileTest extends TestCase
{
    use RefreshDatabase;
    use ActsAsPermissiveAdmin;
    use CreatesClearFileFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stubHasher();
        $this->setUpClearFileFixtures();
        $this->loginAdmin();
    }

    private function pdf(string $name = 'f.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 10, 'application/pdf');
    }

    private function seedDoc(): ClearDoc
    {
        Storage::disk('archive')->put('clear_doc/roc_doc.bin', 'old');

        return ClearDoc::create(['name' => 'D', 'roc_doc' => 'clear_doc/roc_doc.bin']);
    }

    public function test_create_without_required_on_create_file_returns_422_on_that_field(): void
    {
        $response = $this->postJson(route('bpadmin.entity.store', ['entity' => 'clear_doc']), [
            'name' => 'D',
            'required_doc' => $this->pdf(),
        ]);

        $response->assertStatus(422);
        self::assertArrayHasKey('roc_doc', $response->json('errors') ?? $response->json('error.errors') ?? []);
        $this->assertDatabaseCount('clear_docs', 0);
    }

    public function test_create_with_required_on_create_file_succeeds(): void
    {
        $this->postJson(route('bpadmin.entity.store', ['entity' => 'clear_doc']), [
            'name' => 'D',
            'required_doc' => $this->pdf('a.pdf'),
            'roc_doc' => $this->pdf('b.pdf'),
        ])->assertCreated();

        self::assertNotNull(DB::table('clear_docs')->value('roc_doc'));
    }

    public function test_update_with_empty_required_on_create_file_part_keeps_stored_file(): void
    {
        $doc = $this->seedDoc();

        $this->putJson(route('bpadmin.entity.update', ['entity' => 'clear_doc', 'id' => $doc->id]), [
            'name' => 'D2',
            'roc_doc' => '',
        ])->assertOk();

        self::assertSame('clear_doc/roc_doc.bin', DB::table('clear_docs')->where('id', $doc->id)->value('roc_doc'));
        Storage::disk('archive')->assertExists('clear_doc/roc_doc.bin');
    }

    public function test_update_without_required_on_create_file_key_keeps_stored_file(): void
    {
        $doc = $this->seedDoc();

        $this->putJson(route('bpadmin.entity.update', ['entity' => 'clear_doc', 'id' => $doc->id]), ['name' => 'D2'])->assertOk();

        Storage::disk('archive')->assertExists('clear_doc/roc_doc.bin');
    }

    public function test_update_with_empty_required_on_create_morph_part_keeps_row_and_file(): void
    {
        $host = ClearMorphHost::create(['name' => 'M']);
        Storage::disk('archive')->put('morph_roc/old.bin', 'old');
        $host->files()->create(['type' => 'roc', 'name' => 'old.bin', 'path' => 'morph_roc/old.bin']);

        $this->putJson(route('bpadmin.entity.update', ['entity' => 'clear_morph_host', 'id' => $host->id]), [
            'name' => 'M2',
            'roc_file' => '',
        ])->assertOk();

        $this->assertDatabaseHas('test_morphed_files', ['type' => 'roc', 'path' => 'morph_roc/old.bin']);
        Storage::disk('archive')->assertExists('morph_roc/old.bin');
    }

    public function test_update_with_new_file_replaces_path_and_removes_old_file_from_disk(): void
    {
        $doc = $this->seedDoc();

        $this->putJson(route('bpadmin.entity.update', ['entity' => 'clear_doc', 'id' => $doc->id]), [
            'name' => 'D',
            'roc_doc' => $this->pdf('new.pdf'),
        ])->assertOk();

        $new = (string) DB::table('clear_docs')->where('id', $doc->id)->value('roc_doc');
        self::assertNotSame('clear_doc/roc_doc.bin', $new);
        Storage::disk('archive')->assertExists($new);
        Storage::disk('archive')->assertMissing('clear_doc/roc_doc.bin');
    }

    public function test_update_with_new_morph_file_replaces_row_and_removes_old_file_from_disk(): void
    {
        $host = ClearMorphHost::create(['name' => 'M']);
        Storage::disk('archive')->put('morph_roc/old.bin', 'old');
        $host->files()->create(['type' => 'roc', 'name' => 'old.bin', 'path' => 'morph_roc/old.bin']);

        $this->putJson(route('bpadmin.entity.update', ['entity' => 'clear_morph_host', 'id' => $host->id]), [
            'name' => 'M',
            'roc_file' => $this->pdf('new.pdf'),
        ])->assertOk();

        $rows = DB::table('test_morphed_files')->where('type', 'roc');
        self::assertSame(1, $rows->count());
        Storage::disk('archive')->assertExists((string) $rows->value('path'));
        Storage::disk('archive')->assertMissing('morph_roc/old.bin');
    }
}
