<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

trait CreatesTestMorphFileTables
{
    protected function setUpMorphFileFixtures(): void
    {
        Schema::create('test_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('test_morphed_files', function (Blueprint $table): void {
            $table->id();
            $table->string('fileable_type');
            $table->unsignedBigInteger('fileable_id');
            $table->string('type');      // virtual 'kind' column — 'avatar'|'image'|...
            $table->string('name');      // original filename
            $table->string('path');      // storage path
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();
        });
    }
}
