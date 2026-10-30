<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Concerns;

use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ClearFile\ClearDocDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ClearFile\ClearMorphHostDefinition;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Task 2.1 fixtures: soft-deletable hosts with files on the NON-default `archive`
 * disk (default disk is `local`), a permissive authorization provider and a faked disk.
 */
trait CreatesClearFileFixtures
{
    protected function setUpClearFileFixtures(): void
    {
        Storage::fake('public');
        Storage::fake('archive');

        Schema::create('clear_docs', function (Blueprint $t): void {
            $t->id();
            $t->string('name'); // NOT NULL: an empty name fails the write (rollback scenarios)
            $t->string('optional_doc')->nullable();
            $t->string('optional_img')->nullable();
            $t->string('required_doc')->nullable();
            $t->string('roc_doc')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });
        Schema::create('clear_morph_hosts', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->softDeletes();
            $t->timestamps();
        });
        Schema::create('test_morphed_files', function (Blueprint $t): void {
            $t->id();
            $t->string('fileable_type');
            $t->unsignedBigInteger('fileable_id');
            $t->string('type');
            $t->string('name');
            $t->string('path');
            $t->string('mime_type')->nullable();
            $t->unsignedBigInteger('size')->nullable();
            $t->timestamps();
        });

        $this->permitEverything();

        /** @var EntityDefinitionRegistry $registry */
        $registry = app()->make(EntityDefinitionRegistry::class);
        $registry->register(new ClearDocDefinition());
        $registry->register(new ClearMorphHostDefinition());
    }

    /** @return list<string> */
    protected function archiveFiles(): array
    {
        $files = Storage::disk('archive')->allFiles();
        sort($files);

        return $files;
    }
}
