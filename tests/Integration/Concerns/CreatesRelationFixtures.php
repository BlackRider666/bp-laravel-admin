<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migrations for relation-write integration tests.
 *
 * Creates: test_tags, test_comments, test_profiles, test_morph_comments,
 * test_item_tag (pivot with 'approved' column).
 */
trait CreatesRelationFixtures
{
    protected function setUpRelationFixtures(): void
    {
        Schema::create('test_tags', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('test_item_tag', function (Blueprint $table): void {
            $table->unsignedBigInteger('test_item_id');
            $table->unsignedBigInteger('test_tag_id');
            $table->boolean('approved')->default(false);
            $table->primary(['test_item_id', 'test_tag_id']);
        });

        Schema::create('test_comments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('test_item_id');
            $table->string('text');
            $table->timestamps();
        });

        Schema::create('test_profiles', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('test_item_id');
            $table->string('bio');
            $table->timestamps();
        });

        Schema::create('test_morph_comments', function (Blueprint $table): void {
            $table->id();
            $table->string('commentable_type');
            $table->unsignedBigInteger('commentable_id');
            $table->string('text');
            $table->timestamps();
        });
    }
}
