<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Persistence;

use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\ActsAsPermissiveAdmin;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\CreatesClearFileFixtures;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ClearFile\ClearDoc;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ClearFile\ClearMorphHost;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

/**
 * Task 2.1 — A20: Eloquent `forceDelete()` OUTSIDE the admin removes files from the
 * field's configured (non-default) disk. Today the cleanup is disk-less.
 */
final class ForceDeleteDiskTest extends TestCase
{
    use RefreshDatabase;
    use ActsAsPermissiveAdmin;
    use CreatesClearFileFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpClearFileFixtures();
        Storage::fake('local'); // the default disk — must NOT be where deletion is attempted
    }

    private function seedDoc(): ClearDoc
    {
        $row = ['name' => 'D'];
        foreach (['optional_doc', 'optional_img', 'required_doc', 'roc_doc'] as $col) {
            Storage::disk('archive')->put("clear_doc/{$col}.bin", 'x');
            $row[$col] = "clear_doc/{$col}.bin";
        }

        return ClearDoc::create($row);
    }

    public function test_force_delete_removes_column_files_from_configured_disk(): void
    {
        $doc = $this->seedDoc();

        $doc->forceDelete();

        self::assertSame([], $this->archiveFiles());
    }

    public function test_force_delete_does_not_touch_same_path_on_default_disk(): void
    {
        $doc = $this->seedDoc();
        Storage::disk('local')->put('clear_doc/optional_doc.bin', 'unrelated');

        $doc->forceDelete();

        Storage::disk('archive')->assertMissing('clear_doc/optional_doc.bin');
        Storage::disk('local')->assertExists('clear_doc/optional_doc.bin');
    }

    public function test_force_delete_removes_morph_files_from_configured_disk(): void
    {
        $host = ClearMorphHost::create(['name' => 'M']);
        Storage::disk('archive')->put('morph_optional/a.bin', 'x');
        $host->files()->create(['type' => 'optional', 'name' => 'a.bin', 'path' => 'morph_optional/a.bin']);

        $host->forceDelete();

        Storage::disk('archive')->assertMissing('morph_optional/a.bin');
    }

    public function test_soft_delete_outside_admin_keeps_files(): void
    {
        $doc = $this->seedDoc();

        $doc->delete();

        self::assertCount(4, $this->archiveFiles());
        $this->assertSoftDeleted('clear_docs', ['id' => $doc->id]);
    }
}
