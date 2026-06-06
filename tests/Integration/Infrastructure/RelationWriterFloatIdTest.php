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
 * A12 (B10): Non-integral float in a belongsToMany id list must be rejected,
 * not floored to the nearest integer.
 *
 * Bug: the current implementation casts any finite float to int via (int) $v, so
 * 1.9 silently becomes 1 and attaches the wrong tag.
 *
 * Fix: accept only floats where floor($v) === $v (whole-number floats like 1.0 or 2.0)
 * and silently skip non-integral floats like 1.9.
 */
final class RelationWriterFloatIdTest extends TestCase
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

    private function makeDefinition(): EntityDefinition
    {
        return new class extends EntityDefinition {
            public string $model = TestItem::class;

            public function resolveName(): string
            {
                return 'test_item';
            }

            public function fields(): array
            {
                return [
                    TextField::make('name'),
                    TextField::make('email'),
                    BelongsToManyField::make('tags', TestTag::class),
                ];
            }
        };
    }

    // ------------------------------------------------------------------
    // A12 — non-integral float 1.9 must not attach tag id=1
    // ------------------------------------------------------------------

    /**
     * Supply only [1.9] (without id=1 in the list). If 1.9 is wrongly cast to 1,
     * tag id=1 gets attached even though it was never in the list.
     *
     * After the fix: 1.9 is silently discarded → 0 pivot rows.
     * Currently FAILS: 1.9 is cast to (int)1 → tag id=1 is attached.
     */
    public function test_non_integral_float_does_not_attach_wrong_tag(): void
    {
        TestTag::create(['name' => 'first-tag']); // id = 1

        $definition = $this->makeDefinition();
        $host = TestItem::create(['name' => 'Alice', 'email' => 'alice@example.com']);

        // Only supply 1.9 — not 1 itself. Should be discarded.
        $this->writer->applyAll($host, $definition, [
            'tags' => [1.9],
        ]);

        // With fix: 1.9 is rejected → no pivot row for tag1.
        // With bug: 1.9 → 1 → tag1 gets attached.
        $this->assertDatabaseCount('test_item_tag', 0);
    }

    // ------------------------------------------------------------------
    // A12 — mixed list: [2, 1.9] — only integer 2 is synced
    // ------------------------------------------------------------------

    /**
     * Supply [2, 1.9] — tag id=2 should sync, 1.9 should be discarded.
     * Currently FAILS: 1.9 → 1, which syncs tag id=1 unexpectedly (if it exists).
     */
    public function test_mixed_list_with_non_integral_float_syncs_only_valid_ids(): void
    {
        $tag1 = TestTag::create(['name' => 'first-tag']);   // will be wrongly attached if 1.9 → 1
        $tag2 = TestTag::create(['name' => 'second-tag']);  // should be synced

        $definition = $this->makeDefinition();
        $host = TestItem::create(['name' => 'Bob', 'email' => 'bob@example.com']);

        // Supply integer 2 + non-integral float 1.9 (NOT integer 1).
        $this->writer->applyAll($host, $definition, [
            'tags' => [2, 1.9],
        ]);

        // tag2 (id=2) must be synced.
        $this->assertDatabaseHas('test_item_tag', [
            'test_item_id' => $host->id,
            'test_tag_id'  => $tag2->id,
        ]);

        // tag1 (id=1) must NOT be synced — 1.9 is invalid.
        $this->assertDatabaseMissing('test_item_tag', [
            'test_item_id' => $host->id,
            'test_tag_id'  => $tag1->id,
        ]);
    }

    /**
     * After the fix, integral whole-number floats (1.0, 2.0) must still be accepted.
     */
    public function test_integral_float_ids_are_accepted(): void
    {
        $tag1 = TestTag::create(['name' => 'alpha']);
        $tag2 = TestTag::create(['name' => 'beta']);

        $definition = $this->makeDefinition();
        $host = TestItem::create(['name' => 'Carol', 'email' => 'carol@example.com']);

        $this->writer->applyAll($host, $definition, [
            'tags' => [1.0, 2.0],
        ]);

        $this->assertDatabaseHas('test_item_tag', ['test_item_id' => $host->id, 'test_tag_id' => $tag1->id]);
        $this->assertDatabaseHas('test_item_tag', ['test_item_id' => $host->id, 'test_tag_id' => $tag2->id]);
    }
}
