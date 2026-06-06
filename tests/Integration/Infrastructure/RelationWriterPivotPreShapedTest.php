<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Infrastructure;

use BlackParadise\CoreAdmin\Domain\Fields\BelongsToManyField;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\RelationWriter;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\CreatesRelationFixtures;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\CreatesTestItemTable;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItem;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestTag;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * A11 (B6): BelongsToManyField with ->withPivotData([...]) must also apply the
 * static pivot data when the incoming payload is pre-shaped [id => ['note' => ...]].
 *
 * Bug: pre-shaped payloads bypass buildBelongsToManySyncPayload(), so static pivot
 * columns declared via ->withPivotData() are silently dropped.
 *
 * Fix: in applyBelongsToMany(), when the payload is pre-shaped AND the field has
 * pivot data / callback, merge the static data into each per-record entry before sync.
 */
final class RelationWriterPivotPreShapedTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTestItemTable;
    use CreatesRelationFixtures;

    private RelationWriter $writer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestItemFixtures();
        $this->setUpRelationFixtures();

        $this->writer = new RelationWriter();
    }

    // ------------------------------------------------------------------
    // A11 — pre-shaped payload + withPivotData → merged pivot on sync
    // ------------------------------------------------------------------

    /**
     * When the payload is pre-shaped [id => []] and the field declares
     * ->withPivotData(['approved' => true]), sync must receive the merged
     * result [id => ['approved' => true]].
     *
     * Currently FAILS: pre-shaped branch calls sync($value) directly, skipping the
     * pivot merge → 'approved' remains at its default (false/0) in the pivot table.
     */
    public function test_pre_shaped_payload_receives_merged_static_pivot_data(): void
    {
        $tag = TestTag::create(['name' => 'php']);

        $definition = $this->makeStaticPivotDefinition(['approved' => true]);
        $host = TestItem::create(['name' => 'Alice', 'email' => 'alice@example.com']);

        $this->writer->applyAll($host, $definition, [
            'tags' => [
                $tag->id => [], // per-record attrs empty — static pivot should fill in
            ],
        ]);

        // Static pivot 'approved' => true must be merged into the pre-shaped payload.
        $this->assertDatabaseHas('test_item_tag', [
            'test_item_id' => $host->id,
            'test_tag_id'  => $tag->id,
            'approved'     => 1,
        ]);
    }

    /**
     * Per-record attributes in pre-shaped payload must win over static pivot defaults.
     *
     * Currently FAILS: per-record overrides are silently ignored because pivot merge
     * never runs.
     */
    public function test_per_record_pivot_attrs_win_over_static_pivot_data(): void
    {
        $tag1 = TestTag::create(['name' => 'laravel']);
        $tag2 = TestTag::create(['name' => 'symfony']);

        $definition = $this->makeStaticPivotDefinition(['approved' => true]);
        $host = TestItem::create(['name' => 'Bob', 'email' => 'bob@example.com']);

        $this->writer->applyAll($host, $definition, [
            'tags' => [
                $tag1->id => ['approved' => false], // override: false wins
                $tag2->id => [],                    // no override: static true applies
            ],
        ]);

        // tag1: per-record false overrides static true
        $this->assertDatabaseHas('test_item_tag', [
            'test_item_id' => $host->id,
            'test_tag_id'  => $tag1->id,
            'approved'     => 0,
        ]);
        // tag2: static true applied
        $this->assertDatabaseHas('test_item_tag', [
            'test_item_id' => $host->id,
            'test_tag_id'  => $tag2->id,
            'approved'     => 1,
        ]);
    }

    /**
     * Build a definition with BelongsToManyField that has static pivot data.
     *
     * @param array<string, mixed> $staticPivot
     */
    private function makeStaticPivotDefinition(array $staticPivot): EntityDefinition
    {
        // Use a named inner class to avoid PHP anonymous-class constructor issues.
        return new class ($staticPivot) extends EntityDefinition {
            public string $model = TestItem::class;

            public function __construct(private readonly array $staticPivot) {}

            public function resolveName(): string
            {
                return 'test_item';
            }

            public function fields(): array
            {
                return [
                    TextField::make('name'),
                    TextField::make('email'),
                    BelongsToManyField::make('tags', TestTag::class)
                        ->withPivotData($this->staticPivot),
                ];
            }
        };
    }
}
