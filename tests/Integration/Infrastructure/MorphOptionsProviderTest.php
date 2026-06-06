<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Infrastructure;

use BlackParadise\CoreAdmin\Domain\Contracts\Entity\RelationOptionsProviderContract;
use BlackParadise\CoreAdmin\Domain\Fields\BelongsToField;
use BlackParadise\CoreAdmin\Domain\Fields\MorphToField;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\CreatesRelationFixtures;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\CreatesTestItemTable;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItem;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestTag;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Verifies EloquentRelationOptionsProvider::morphOptions() returns per-type
 * option lists shaped for a MorphTo two-step picker UI.
 */
final class MorphOptionsProviderTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTestItemTable;
    use CreatesRelationFixtures;

    private RelationOptionsProviderContract $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestItemFixtures();
        $this->setUpRelationFixtures();
        $this->provider = resolve(RelationOptionsProviderContract::class);
    }

    protected function tearDown(): void
    {
        // Clear any morph map registered during tests to avoid leaking global state.
        Relation::morphMap([], false);
        parent::tearDown();
    }

    public function test_returns_per_type_options_with_morph_value(): void
    {
        // No morph map → value must equal FQCN.
        TestItem::create(['name' => 'Item A', 'email' => 'a@test.com']);
        TestTag::create(['name' => 'Tag One']);

        $field = MorphToField::make('commentable', '')
            ->morphTypes([
                TestItem::class => ['label' => 'Post', 'display' => 'name'],
                TestTag::class  => ['label' => 'Tag',  'display' => 'name'],
            ]);

        $result = $this->provider->morphOptions($field);

        self::assertCount(2, $result);

        // Find TestItem entry.
        $itemEntry = collect($result)->firstWhere('value', TestItem::class);
        self::assertNotNull($itemEntry, 'Entry for TestItem not found');
        self::assertSame(TestItem::class, $itemEntry['value']);
        self::assertSame('Post', $itemEntry['label']);
        self::assertCount(1, $itemEntry['options']);
        self::assertSame('Item A', $itemEntry['options'][0]['label']);

        // Find TestTag entry.
        $tagEntry = collect($result)->firstWhere('value', TestTag::class);
        self::assertNotNull($tagEntry, 'Entry for TestTag not found');
        self::assertSame(TestTag::class, $tagEntry['value']);
        self::assertSame('Tag', $tagEntry['label']);
        self::assertCount(1, $tagEntry['options']);
        self::assertSame('Tag One', $tagEntry['options'][0]['label']);
    }

    public function test_uses_morph_map_alias_when_registered(): void
    {
        // Register a morph map — getMorphClass() should return the alias.
        Relation::morphMap([
            'item' => TestItem::class,
            'tag'  => TestTag::class,
        ]);

        TestItem::create(['name' => 'Aliased Item', 'email' => 'alias@test.com']);

        $field = MorphToField::make('commentable', '')
            ->morphTypes([
                TestItem::class => ['label' => 'Item', 'display' => 'name'],
            ]);

        $result = $this->provider->morphOptions($field);

        self::assertCount(1, $result);
        self::assertSame('item', $result[0]['value']);
        self::assertSame('Item', $result[0]['label']);
        self::assertCount(1, $result[0]['options']);
        self::assertSame('Aliased Item', $result[0]['options'][0]['label']);
    }

    public function test_returns_empty_array_for_non_morph_field(): void
    {
        $field = BelongsToField::make('tag_id', TestTag::class);

        $result = $this->provider->morphOptions($field);

        self::assertSame([], $result);
    }

    public function test_returns_empty_options_when_type_map_is_empty(): void
    {
        $field = MorphToField::make('commentable', '');
        // morphTypes() never called → morphTypeMap() returns []

        $result = $this->provider->morphOptions($field);

        self::assertSame([], $result);
    }
}
