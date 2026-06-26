<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Concerns;

use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestPublication;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestPublicationItem;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestTag;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the minimal schema and helper seed methods for
 * EloquentEntityRepositoryDeepRelationTest.
 *
 * Tables: test_publications, test_publication_items, test_pub_item_tag, test_tags.
 * Note: test_tags may already exist; this trait creates it only when absent.
 */
trait CreatesEmbeddedRelationFixtures
{
    protected function setUpEmbeddedRelationFixtures(): void
    {
        Schema::create('test_publications', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('test_publication_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('publication_id');
            $table->string('title');
            $table->timestamps();
        });

        if (!Schema::hasTable('test_tags')) {
            Schema::create('test_tags', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->timestamps();
            });
        }

        Schema::create('test_pub_item_tag', function (Blueprint $table): void {
            $table->unsignedBigInteger('pub_item_id');
            $table->unsignedBigInteger('test_tag_id');
            $table->primary(['pub_item_id', 'test_tag_id']);
        });
    }

    /**
     * Seed a publication with one item that has two tags.
     * Returns [publicationId, list<int> tagIds].
     *
     * @return array{int, list<int>}
     */
    protected function seedPublicationWithItemAndTags(): array
    {
        $publication = TestPublication::create(['name' => 'Deep Relation Pub']);

        $item = TestPublicationItem::create([
            'publication_id' => $publication->id,
            'title'          => 'Item One',
        ]);

        $tag1 = TestTag::create(['name' => 'TagA']);
        $tag2 = TestTag::create(['name' => 'TagB']);

        $item->tags()->attach([$tag1->id, $tag2->id]);

        return [(int) $publication->id, [(int) $tag1->id, (int) $tag2->id]];
    }

    /**
     * Seed a publication with no items (empty collection for the 'items' relation).
     * Returns the publication id.
     */
    protected function seedPublicationWithNoItems(): int
    {
        $publication = TestPublication::create(['name' => 'Empty Pub']);
        return (int) $publication->id;
    }
}
