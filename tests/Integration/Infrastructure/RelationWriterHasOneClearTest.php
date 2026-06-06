<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Infrastructure;

use BlackParadise\CoreAdmin\Domain\Fields\HasOneField;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\RelationWriter;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\CreatesRelationFixtures;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\CreatesTestItemTable;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItem;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestProfile;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * A18 (B16): Sending an explicit empty value for a hasOne relation must delete
 * the existing child (not silently skip the update).
 *
 * Bug: applyHasOne() returns early when $value === null || $value === '', so there
 * is no way to clear a hasOne relation via the admin — the child always survives.
 *
 * Fix: distinguish "key absent from payload" (do nothing) from "key present with
 * empty value" (delete existing child). Since extractRelationPayload only includes
 * keys present in the input, any value reaching applyHasOne is explicit.
 */
final class RelationWriterHasOneClearTest extends TestCase
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
                    HasOneField::make('profile', TestProfile::class),
                ];
            }
        };
    }

    // ------------------------------------------------------------------
    // A18 — explicit null clears existing hasOne child
    // ------------------------------------------------------------------

    /**
     * Calling applyAll with profile => null must delete the existing child.
     *
     * Currently FAILS: applyHasOne() returns early on null → child survives.
     */
    public function test_explicit_null_for_has_one_deletes_existing_child(): void
    {
        $definition = $this->makeDefinition();

        $host = TestItem::create(['name' => 'Alice', 'email' => 'alice@example.com']);
        $host->profile()->create(['bio' => 'My bio']);

        $this->assertDatabaseHas('test_profiles', ['test_item_id' => $host->id]);

        // Apply with explicit null.
        $this->writer->applyAll($host, $definition, ['profile' => null]);

        // Child must be gone.
        $this->assertDatabaseMissing('test_profiles', ['test_item_id' => $host->id]);
    }

    // ------------------------------------------------------------------
    // A18 — explicit empty string clears existing hasOne child
    // ------------------------------------------------------------------

    /**
     * Calling applyAll with profile => '' must delete the existing child.
     *
     * Currently FAILS: same early-return on ''.
     */
    public function test_explicit_empty_string_for_has_one_deletes_existing_child(): void
    {
        $definition = $this->makeDefinition();

        $host = TestItem::create(['name' => 'Bob', 'email' => 'bob@example.com']);
        $host->profile()->create(['bio' => 'Bob bio']);

        $this->assertDatabaseHas('test_profiles', ['test_item_id' => $host->id]);

        $this->writer->applyAll($host, $definition, ['profile' => '']);

        $this->assertDatabaseMissing('test_profiles', ['test_item_id' => $host->id]);
    }

    // ------------------------------------------------------------------
    // A18 — explicit empty array clears existing hasOne child
    // ------------------------------------------------------------------

    public function test_explicit_empty_array_for_has_one_deletes_existing_child(): void
    {
        $definition = $this->makeDefinition();

        $host = TestItem::create(['name' => 'Carol', 'email' => 'carol@example.com']);
        $host->profile()->create(['bio' => 'Carol bio']);

        $this->assertDatabaseHas('test_profiles', ['test_item_id' => $host->id]);

        $this->writer->applyAll($host, $definition, ['profile' => []]);

        $this->assertDatabaseMissing('test_profiles', ['test_item_id' => $host->id]);
    }

    // ------------------------------------------------------------------
    // A18 — happy path: non-empty value still upserts the child
    // ------------------------------------------------------------------

    public function test_non_empty_value_upserts_has_one_child(): void
    {
        $definition = $this->makeDefinition();

        $host = TestItem::create(['name' => 'Dan', 'email' => 'dan@example.com']);
        $host->profile()->create(['bio' => 'Old bio']);

        $this->writer->applyAll($host, $definition, ['profile' => ['bio' => 'Updated bio']]);

        $this->assertDatabaseHas('test_profiles', [
            'test_item_id' => $host->id,
            'bio'          => 'Updated bio',
        ]);
        $this->assertDatabaseCount('test_profiles', 1);
    }
}
