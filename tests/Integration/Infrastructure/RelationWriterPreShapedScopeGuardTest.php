<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Infrastructure;

use BlackParadise\CoreAdmin\Domain\Entity\EntityRecord;
use BlackParadise\CoreAdmin\Domain\Fields\BelongsToManyField;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\CoreAdmin\Domain\Mutators\EntityMutatorInterface;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\CreatesRelationFixtures;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\CreatesTestItemTable;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItem;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestTag;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

/**
 * Security regression: Bug #4 — pre-shaped sync payload must pass through
 * scope-guard (optionConstraints) when set.
 *
 * Before the fix, `RelationWriter::applyBelongsToMany()` called `->sync($value)`
 * directly for pre-shaped payloads, bypassing `filterConstrainedIds()`. An
 * attacker could supply `{"5":{"x":1}}` to attach an ID that is outside the
 * allowed constraint set.
 */
final class RelationWriterPreShapedScopeGuardTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTestItemTable;
    use CreatesRelationFixtures;

    private EntityMutatorInterface $mutator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestItemFixtures();
        $this->setUpRelationFixtures();

        // Add a 'type' column to test_tags so we can filter on it.
        Schema::table('test_tags', function (Blueprint $t): void {
            $t->string('type')->default('general');
        });

        $this->mutator = $this->app->make(EntityMutatorInterface::class);
    }

    /**
     * Pre-shaped payload with a disallowed ID must not be synced when
     * optionConstraints are declared on the field.
     */
    public function test_pre_shaped_payload_disallowed_id_is_filtered_by_scope_guard(): void
    {
        $allowed   = TestTag::create(['name' => 'allowed',   'type' => 'permitted']);
        $forbidden = TestTag::create(['name' => 'forbidden', 'type' => 'restricted']);

        $definition = $this->definitionWithConstraint('type', 'permitted');

        // Attacker sends pre-shaped payload that includes the forbidden id.
        $record = new EntityRecord($definition, [
            'name'  => 'Attacker',
            'email' => 'attacker@example.com',
            'tags'  => [
                $allowed->id   => ['approved' => true],
                $forbidden->id => ['approved' => true],  // must be filtered out
            ],
        ]);

        $created = $this->mutator->create($record);

        // The allowed id is attached.
        $this->assertDatabaseHas('test_item_tag', [
            'test_item_id' => $created->id(),
            'test_tag_id'  => $allowed->id,
        ]);

        // The forbidden id is NOT attached.
        $this->assertDatabaseMissing('test_item_tag', [
            'test_item_id' => $created->id(),
            'test_tag_id'  => $forbidden->id,
        ]);
    }

    /**
     * Pre-shaped payload passes through unchanged when no optionConstraints are
     * set — back-compat with pivot-callback patterns.
     */
    public function test_pre_shaped_payload_passes_through_unchanged_without_constraints(): void
    {
        $t1 = TestTag::create(['name' => 'php',     'type' => 'general']);
        $t2 = TestTag::create(['name' => 'laravel', 'type' => 'general']);

        // No whereOption() → no constraints → no filtering.
        $definition = new class extends EntityDefinition {
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

        $record = new EntityRecord($definition, [
            'name'  => 'Normal',
            'email' => 'normal@example.com',
            'tags'  => [
                $t1->id => ['approved' => true],
                $t2->id => ['approved' => false],
            ],
        ]);

        $created = $this->mutator->create($record);

        $this->assertDatabaseHas('test_item_tag', [
            'test_item_id' => $created->id(),
            'test_tag_id'  => $t1->id,
            'approved'     => 1,
        ]);
        $this->assertDatabaseHas('test_item_tag', [
            'test_item_id' => $created->id(),
            'test_tag_id'  => $t2->id,
            'approved'     => 0,
        ]);
    }

    /**
     * @param 'type' $column
     */
    private function definitionWithConstraint(string $column, string $value): EntityDefinition
    {
        return new class ($column, $value) extends EntityDefinition {
            public string $model = TestItem::class;

            public function __construct(
                private readonly string $col,
                private readonly string $val,
            ) {}

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
                        ->whereOption($this->col, $this->val),
                ];
            }
        };
    }
}
