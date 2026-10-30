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
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Task 2.1 — A13 (morph), A53 (column file/image incl. rollback), A51 (new file wins),
 * A52 (422 on required/requiredOnCreate), A14 (stored file never removed by __clear there).
 */
final class ClearFileTest extends TestCase
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

    private function seedDoc(): ClearDoc
    {
        foreach (['optional_doc', 'optional_img', 'required_doc', 'roc_doc'] as $col) {
            Storage::disk('archive')->put("clear_doc/{$col}.bin", 'old');
        }

        return ClearDoc::create([
            'name' => 'D',
            'optional_doc' => 'clear_doc/optional_doc.bin',
            'optional_img' => 'clear_doc/optional_img.bin',
            'required_doc' => 'clear_doc/required_doc.bin',
            'roc_doc' => 'clear_doc/roc_doc.bin',
        ]);
    }

    /**
     * @param array<string, mixed> $data
     * @return TestResponse<Response>
     */
    private function updateDoc(ClearDoc $doc, array $data): TestResponse
    {
        return $this->putJson(
            route('bpadmin.entity.update', ['entity' => 'clear_doc', 'id' => $doc->id]),
            ['name' => 'D'] + $data,
        );
    }

    /** @return array{0: ClearMorphHost, 1: array<string, string>} */
    private function seedMorphHost(): array
    {
        $host = ClearMorphHost::create(['name' => 'M']);
        $paths = [];
        foreach (['optional', 'required', 'roc'] as $type) {
            $paths[$type] = "morph_{$type}/old.bin";
            Storage::disk('archive')->put($paths[$type], 'old');
            $host->files()->create(['type' => $type, 'name' => 'old.bin', 'path' => $paths[$type], 'mime_type' => 'application/pdf', 'size' => 3]);
        }

        return [$host, $paths];
    }

    /**
     * @param array<string, mixed> $data
     * @return TestResponse<Response>
     */
    private function updateMorphHost(ClearMorphHost $host, array $data): TestResponse
    {
        return $this->putJson(
            route('bpadmin.entity.update', ['entity' => 'clear_morph_host', 'id' => $host->id]),
            ['name' => 'M'] + $data,
        );
    }

    /** @param TestResponse<Response> $response */
    private function assertErrorOnFieldOrClearKey(TestResponse $response, string $field): void
    {
        $errors = $response->json('errors') ?? $response->json('error.errors') ?? $response->json('error') ?? [];
        $keys = is_array($errors) ? array_keys($errors) : [];
        self::assertTrue(
            in_array($field, $keys, true) || in_array($field . '__clear', $keys, true),
            'Expected a validation error keyed "' . $field . '" or "' . $field . '__clear", got: ' . json_encode($response->json()),
        );
    }

    // ---------------------------------------------------------------- A53 (column file / image)

    public function test_clear_optional_column_file_sets_null_and_removes_file_from_field_disk(): void
    {
        $doc = $this->seedDoc();

        $this->updateDoc($doc, ['optional_doc__clear' => '1'])->assertOk();

        self::assertNull(DB::table('clear_docs')->where('id', $doc->id)->value('optional_doc'));
        Storage::disk('archive')->assertMissing('clear_doc/optional_doc.bin');
    }

    public function test_clear_optional_column_image_sets_null_and_removes_file_from_field_disk(): void
    {
        $doc = $this->seedDoc();

        $this->updateDoc($doc, ['optional_img__clear' => '1'])->assertOk();

        self::assertNull(DB::table('clear_docs')->where('id', $doc->id)->value('optional_img'));
        Storage::disk('archive')->assertMissing('clear_doc/optional_img.bin');
    }

    public function test_clear_one_optional_file_leaves_other_files_untouched(): void
    {
        $doc = $this->seedDoc();

        $this->updateDoc($doc, ['optional_doc__clear' => '1'])->assertOk();

        self::assertNull(DB::table('clear_docs')->where('id', $doc->id)->value('optional_doc'));
        self::assertSame('clear_doc/optional_img.bin', DB::table('clear_docs')->where('id', $doc->id)->value('optional_img'));
        Storage::disk('archive')->assertExists('clear_doc/optional_img.bin');
        Storage::disk('archive')->assertExists('clear_doc/required_doc.bin');
    }

    public function test_clear_optional_column_file_on_failed_write_keeps_file_and_column(): void
    {
        // Rollback scenario: an empty `name` violates NOT NULL, so the whole update fails.
        $doc = $this->seedDoc();

        $response = $this->putJson(
            route('bpadmin.entity.update', ['entity' => 'clear_doc', 'id' => $doc->id]),
            ['name' => '', 'optional_doc__clear' => '1'],
        );

        self::assertGreaterThanOrEqual(400, $response->baseResponse->getStatusCode());
        self::assertSame('clear_doc/optional_doc.bin', DB::table('clear_docs')->where('id', $doc->id)->value('optional_doc'));
        Storage::disk('archive')->assertExists('clear_doc/optional_doc.bin');
    }

    // ---------------------------------------------------------------- A51 (new file wins)

    public function test_new_file_together_with_clear_flag_stores_new_file_and_removes_old(): void
    {
        $doc = $this->seedDoc();

        $this->updateDoc($doc, [
            'optional_doc' => UploadedFile::fake()->create('new.pdf', 10, 'application/pdf'),
            'optional_doc__clear' => '1',
        ])->assertOk();

        $new = DB::table('clear_docs')->where('id', $doc->id)->value('optional_doc');
        self::assertNotNull($new);
        self::assertNotSame('clear_doc/optional_doc.bin', $new);
        Storage::disk('archive')->assertExists((string) $new);
        Storage::disk('archive')->assertMissing('clear_doc/optional_doc.bin');
    }

    public function test_new_morph_file_together_with_clear_flag_keeps_new_row_and_removes_old_file(): void
    {
        [$host, $paths] = $this->seedMorphHost();

        $this->updateMorphHost($host, [
            'optional_file' => UploadedFile::fake()->create('new.pdf', 10, 'application/pdf'),
            'optional_file__clear' => '1',
        ])->assertOk();

        $rows = DB::table('test_morphed_files')->where('type', 'optional');
        self::assertSame(1, $rows->count());
        self::assertSame('new.pdf', $rows->value('name'));
        Storage::disk('archive')->assertExists((string) $rows->value('path'));
        Storage::disk('archive')->assertMissing($paths['optional']);
    }

    // ---------------------------------------------------------------- A52 / A14 (required)

    public function test_clear_on_required_column_file_returns_422_and_keeps_file(): void
    {
        $doc = $this->seedDoc();

        $response = $this->updateDoc($doc, ['required_doc__clear' => '1']);

        $response->assertStatus(422);
        $this->assertErrorOnFieldOrClearKey($response, 'required_doc');
        self::assertSame('clear_doc/required_doc.bin', DB::table('clear_docs')->where('id', $doc->id)->value('required_doc'));
        Storage::disk('archive')->assertExists('clear_doc/required_doc.bin');
    }

    public function test_clear_on_required_on_create_column_file_returns_422_and_keeps_file(): void
    {
        $doc = $this->seedDoc();

        $response = $this->updateDoc($doc, ['roc_doc__clear' => '1']);

        $response->assertStatus(422);
        $this->assertErrorOnFieldOrClearKey($response, 'roc_doc');
        self::assertSame('clear_doc/roc_doc.bin', DB::table('clear_docs')->where('id', $doc->id)->value('roc_doc'));
        Storage::disk('archive')->assertExists('clear_doc/roc_doc.bin');
    }

    public function test_clear_on_required_morph_file_returns_422_and_keeps_row_and_file(): void
    {
        [$host, $paths] = $this->seedMorphHost();

        $response = $this->updateMorphHost($host, ['required_file__clear' => '1']);

        $response->assertStatus(422);
        $this->assertErrorOnFieldOrClearKey($response, 'required_file');
        $this->assertDatabaseHas('test_morphed_files', ['type' => 'required', 'path' => $paths['required']]);
        Storage::disk('archive')->assertExists($paths['required']);
    }

    public function test_clear_on_required_on_create_morph_file_returns_422_and_keeps_row_and_file(): void
    {
        [$host, $paths] = $this->seedMorphHost();

        $response = $this->updateMorphHost($host, ['roc_file__clear' => '1']);

        $response->assertStatus(422);
        $this->assertErrorOnFieldOrClearKey($response, 'roc_file');
        $this->assertDatabaseHas('test_morphed_files', ['type' => 'roc', 'path' => $paths['roc']]);
        Storage::disk('archive')->assertExists($paths['roc']);
    }

    // ---------------------------------------------------------------- A13 (morph)

    public function test_clear_optional_morph_file_deletes_row_and_file(): void
    {
        [$host, $paths] = $this->seedMorphHost();

        $this->updateMorphHost($host, ['optional_file__clear' => '1'])->assertOk();

        $this->assertDatabaseMissing('test_morphed_files', ['type' => 'optional']);
        Storage::disk('archive')->assertMissing($paths['optional']);
    }

    public function test_clear_optional_morph_file_leaves_other_morph_rows_untouched(): void
    {
        [$host, $paths] = $this->seedMorphHost();

        $this->updateMorphHost($host, ['optional_file__clear' => '1'])->assertOk();

        $this->assertDatabaseMissing('test_morphed_files', ['type' => 'optional']);
        $this->assertDatabaseHas('test_morphed_files', ['type' => 'required', 'path' => $paths['required']]);
        $this->assertDatabaseHas('test_morphed_files', ['type' => 'roc', 'path' => $paths['roc']]);
        Storage::disk('archive')->assertExists($paths['required']);
    }
}
