<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Infrastructure;

use BlackParadise\CoreAdmin\Domain\ValueObjects\EntityKey;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\EloquentEntityRepository;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\CreatesEmbeddedRelationFixtures;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestPublicationDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestPublicationNoEmbedDefinition;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Task 3 — EloquentEntityRepository: nested eager-load + deep serialization.
 *
 * Covers two behaviours:
 *   1. An embedded HasMany whose child definition contains a belongsToMany:
 *      the repository must build the nested dot-path ('items.tags') for
 *      eager-loading and serialize sub-relations into the returned EntityRecord.
 *   2. A non-embedded, non-displayEagerLoad relation stays flat (plain toArray,
 *      no sub-relations injected).
 */
final class EloquentEntityRepositoryDeepRelationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesEmbeddedRelationFixtures;

    private EloquentEntityRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpEmbeddedRelationFixtures();
        $this->repository = $this->app->make(EloquentEntityRepository::class);
    }

    /**
     * An embedded HasMany relation whose child definition contains a
     * belongsToMany field must have its sub-relations included in the
     * serialized EntityRecord.
     *
     * The repository builds 'items.tags' as an eager-load path, then
     * deep-serializes the loaded Collection so each item array carries
     * a 'tags' key with the correct tag records.
     */
    public function test_embedded_belongs_to_many_is_deep_serialized(): void
    {
        [$publicationId, $tagIds] = $this->seedPublicationWithItemAndTags();

        $definition = new TestPublicationDefinition();
        $record     = $this->repository->find($definition, new EntityKey($publicationId, 'int'));

        self::assertNotNull($record);

        $items = $record->relation('items');

        self::assertIsArray($items, 'items relation must be an array');
        self::assertNotEmpty($items, 'expected at least one item in publication');

        $firstItem = $items[0];
        self::assertIsArray($firstItem);
        self::assertArrayHasKey('tags', $firstItem, 'embedded belongsToMany must survive deep serialization');

        $serializedTagIds = array_map(static fn(mixed $t): int => (int) $t['id'], $firstItem['tags']);
        sort($serializedTagIds);
        sort($tagIds);
        self::assertSame($tagIds, $serializedTagIds, 'tag ids must match the seeded tags');
    }

    /**
     * Contrast test for the opt-in invariant: a HasManyField WITHOUT ->embed()
     * and WITHOUT ->withDisplayEagerLoad() serializes flat — each item is only
     * attributesToArray(), with NO sub-relation keys injected.
     *
     * Seeds the same data as the deep test (publication with items that have
     * tags attached). Uses TestPublicationNoEmbedDefinition (no ->embed()) so
     * deepSerializeRelationNames() does NOT include 'items'.
     *
     * Assertions:
     *   - items is a non-empty array (the flat path produced real data, not vacuously [])
     *   - each serialized item does NOT contain a 'tags' key (deep-serialize was NOT applied)
     *
     * Scope: this is an end-to-end behavioral guard — it asserts the observable
     * flat-serialization contract, not a unit-level isolation of
     * deepSerializeRelationNames(). Under the no-embed definition 'tags' is never
     * eager-loaded, so the eager-load gate and the deep-serialize gate each keep
     * it out independently. The non-deep code path is additionally covered by the
     * EloquentEntityRepository regression suite.
     */
    public function test_non_deep_relation_serialization_unchanged(): void
    {
        [$publicationId] = $this->seedPublicationWithItemAndTags();

        // No ->embed(), no ->withDisplayEagerLoad() — must serialize flat.
        $definition = new TestPublicationNoEmbedDefinition();
        $record     = $this->repository->find($definition, new EntityKey($publicationId, 'int'));

        self::assertNotNull($record);

        $items = $record->relation('items');

        self::assertIsArray($items, 'items relation must be an array');
        self::assertNotEmpty($items, 'non-deep path must return actual item data (not vacuously empty)');

        foreach ($items as $item) {
            self::assertIsArray($item);
            self::assertArrayNotHasKey('tags', $item, 'non-embedded relation must NOT deep-serialize sub-relations');
        }
    }
}
