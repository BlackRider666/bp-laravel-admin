<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration;

use BlackParadise\CoreAdmin\Domain\Fields\BelongsToField;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\EloquentRelationOptionsProvider;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\CreatesRelationFixtures;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\CreatesTestItemTable;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestTag;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * Verifies the per-instance request-scoped cache in EloquentRelationOptionsProvider.
 *
 * A second call to options() with identical parameters must not emit a second
 * SQL query — the result is served from the in-memory cache.
 */
final class RelationOptionsCacheTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTestItemTable;
    use CreatesRelationFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestItemFixtures();
        $this->setUpRelationFixtures();
    }

    public function test_repeated_options_calls_for_the_same_target_hit_the_db_once(): void
    {
        TestTag::create(['name' => 'alpha']);
        TestTag::create(['name' => 'beta']);
        TestTag::create(['name' => 'gamma']);

        $provider = new EloquentRelationOptionsProvider();
        $field    = BelongsToField::make('test_tag_id', TestTag::class);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $first  = $provider->options($field);
        $second = $provider->options($field);

        $this->assertCount(1, DB::getQueryLog());
        $this->assertSame($first, $second);
        $this->assertCount(3, $first);
    }

    public function test_different_targets_use_separate_cache_entries_and_each_hit_the_db_once(): void
    {
        TestTag::create(['name' => 'tag-one']);

        $provider = new EloquentRelationOptionsProvider();
        $fieldA   = BelongsToField::make('test_tag_id', TestTag::class);

        // Second field pointing at the SAME class but with a different display field
        // is a distinct cache key — must NOT bleed through.
        $fieldB = BelongsToField::make('test_tag_id', TestTag::class)->withDisplayField('id');

        DB::flushQueryLog();
        DB::enableQueryLog();

        $resA1 = $provider->options($fieldA);
        $resA2 = $provider->options($fieldA); // cache hit
        $resB1 = $provider->options($fieldB); // different key → new query
        $resB2 = $provider->options($fieldB); // cache hit

        $this->assertCount(2, DB::getQueryLog());
        $this->assertSame($resA1, $resA2);
        $this->assertSame($resB1, $resB2);
        // Explicit anti-collision: results for different cache keys must differ.
        $this->assertNotSame($resA1, $resB1);
    }
}
