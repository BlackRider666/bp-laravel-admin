<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Infrastructure;

use BlackParadise\CoreAdmin\Domain\Entity\EntityRecord;
use BlackParadise\CoreAdmin\Domain\Fields\BelongsToManyField;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\CoreAdmin\Domain\Mutators\EntityMutatorInterface;
use BlackParadise\CoreAdmin\Domain\ValueObjects\EntityKey;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\CreatesRelationFixtures;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\CreatesTestItemTable;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItem;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItemWithPivotCallbackDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItemWithPivotDataDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItemWithRelationsDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestTag;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;

final class EloquentEntityMutatorRelationsTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTestItemTable;
    use CreatesRelationFixtures;

    private EntityMutatorInterface $mutator;
    private TestItemWithRelationsDefinition $definition;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestItemFixtures();
        $this->setUpRelationFixtures();

        $this->mutator    = $this->app->make(EntityMutatorInterface::class);
        $this->definition = new TestItemWithRelationsDefinition();
    }

    // ------------------------------------------------------------------
    // belongsToMany — basic sync
    // ------------------------------------------------------------------

    public function test_create_attaches_belongs_to_many_records_without_pivot(): void
    {
        $tag1 = TestTag::create(['name' => 'laravel']);
        $tag2 = TestTag::create(['name' => 'admin']);

        $record = new EntityRecord($this->definition, [
            'name'  => 'Alice',
            'email' => 'alice@example.com',
            'tags'  => [$tag1->id, $tag2->id],
        ]);

        $created = $this->mutator->create($record);

        $this->assertDatabaseHas('test_item_tag', [
            'test_item_id' => $created->id(),
            'test_tag_id'  => $tag1->id,
        ]);
        $this->assertDatabaseHas('test_item_tag', [
            'test_item_id' => $created->id(),
            'test_tag_id'  => $tag2->id,
        ]);
    }

    public function test_create_with_empty_tags_array_attaches_nothing(): void
    {
        $record = new EntityRecord($this->definition, [
            'name'  => 'Bob',
            'email' => 'bob@example.com',
            'tags'  => [],
        ]);

        $created = $this->mutator->create($record);

        $this->assertDatabaseCount('test_item_tag', 0);
        $this->assertDatabaseHas('test_items', ['id' => $created->id()]);
    }

    public function test_create_without_tags_key_attaches_nothing(): void
    {
        $record = new EntityRecord($this->definition, [
            'name'  => 'Carol',
            'email' => 'carol@example.com',
        ]);

        $created = $this->mutator->create($record);

        $this->assertDatabaseCount('test_item_tag', 0);
        $this->assertDatabaseHas('test_items', ['id' => $created->id()]);
    }

    // ------------------------------------------------------------------
    // belongsToMany — static pivot data
    // ------------------------------------------------------------------

    public function test_create_attaches_belongs_to_many_with_static_pivot_data(): void
    {
        $tag1 = TestTag::create(['name' => 'php']);
        $tag2 = TestTag::create(['name' => 'laravel']);

        $definition = new TestItemWithPivotDataDefinition();

        $record = new EntityRecord($definition, [
            'name'  => 'Alice',
            'email' => 'alice@example.com',
            'tags'  => [$tag1->id, $tag2->id],
        ]);

        $created = $this->mutator->create($record);

        $this->assertDatabaseHas('test_item_tag', [
            'test_item_id' => $created->id(),
            'test_tag_id'  => $tag1->id,
            'approved'     => 1,
        ]);
        $this->assertDatabaseHas('test_item_tag', [
            'test_item_id' => $created->id(),
            'test_tag_id'  => $tag2->id,
            'approved'     => 1,
        ]);
    }

    // ------------------------------------------------------------------
    // belongsToMany — dynamic pivot callback
    // ------------------------------------------------------------------

    public function test_create_attaches_belongs_to_many_with_dynamic_pivot_callback(): void
    {
        $tagOdd  = TestTag::create(['name' => 'alpha']);  // id буде непарним (1)
        $tagEven = TestTag::create(['name' => 'beta']);   // id буде парним (2)

        $definition = new TestItemWithPivotCallbackDefinition();

        $record = new EntityRecord($definition, [
            'name'  => 'Dave',
            'email' => 'dave@example.com',
            'tags'  => [$tagOdd->id, $tagEven->id],
        ]);

        $created = $this->mutator->create($record);

        $this->assertDatabaseHas('test_item_tag', [
            'test_item_id' => $created->id(),
            'test_tag_id'  => $tagOdd->id,
            'approved'     => 0,  // непарний id → false
        ]);
        $this->assertDatabaseHas('test_item_tag', [
            'test_item_id' => $created->id(),
            'test_tag_id'  => $tagEven->id,
            'approved'     => 1,  // парний id → true
        ]);
    }

    public function test_pivot_callback_receives_host_attributes(): void
    {
        $tag = TestTag::create(['name' => 'gamma']);

        $definition = new class extends EntityDefinition {
            public string $model = TestItem::class;
            public function resolveName(): string
            {
                return 'test_item';
            }
            public array $capture = [];
            public function fields(): array
            {
                $capture = &$this->capture;
                return [
                    TextField::make('name'),
                    TextField::make('email'),
                    BelongsToManyField::make('tags', TestTag::class)
                        ->pivotPayload(function ($id, array $host) use (&$capture): array {
                            $capture[$id] = $host;
                            return ['approved' => true];
                        }),
                ];
            }
        };

        $record = new EntityRecord($definition, [
            'name'  => 'Eve',
            'email' => 'eve@example.com',
            'tags'  => [$tag->id],
        ]);

        $this->mutator->create($record);

        $this->assertArrayHasKey($tag->id, $definition->capture);
        $this->assertSame('Eve', $definition->capture[$tag->id]['name']);
        $this->assertSame('eve@example.com', $definition->capture[$tag->id]['email']);
    }

    // ------------------------------------------------------------------
    // hasOne
    // ------------------------------------------------------------------

    public function test_create_creates_has_one_child(): void
    {
        $record = new EntityRecord($this->definition, [
            'name'    => 'Frank',
            'email'   => 'frank@example.com',
            'profile' => ['bio' => 'Laravel developer'],
        ]);

        $created = $this->mutator->create($record);

        $this->assertDatabaseHas('test_profiles', [
            'test_item_id' => $created->id(),
            'bio'          => 'Laravel developer',
        ]);
    }

    public function test_create_with_null_has_one_creates_no_child(): void
    {
        $record = new EntityRecord($this->definition, [
            'name'    => 'Grace',
            'email'   => 'grace@example.com',
            'profile' => null,
        ]);

        $created = $this->mutator->create($record);

        $this->assertDatabaseMissing('test_profiles', ['test_item_id' => $created->id()]);
    }

    public function test_update_replaces_has_one_child_attributes(): void
    {
        $record = new EntityRecord($this->definition, [
            'name'    => 'Henry',
            'email'   => 'henry@example.com',
            'profile' => ['bio' => 'Initial bio'],
        ]);
        $created = $this->mutator->create($record);

        $this->mutator->update(
            new EntityKey($created->id(), 'int'),
            new EntityRecord($this->definition, [
                'name'    => 'Henry',
                'email'   => 'henry@example.com',
                'profile' => ['bio' => 'Updated bio'],
            ]),
        );

        $this->assertDatabaseHas('test_profiles', [
            'test_item_id' => $created->id(),
            'bio'          => 'Updated bio',
        ]);
        $this->assertDatabaseCount('test_profiles', 1);
    }

    // ------------------------------------------------------------------
    // hasMany — sync (delete old + create new)
    // ------------------------------------------------------------------

    public function test_create_persists_all_has_many_children(): void
    {
        $record = new EntityRecord($this->definition, [
            'name'     => 'Ivy',
            'email'    => 'ivy@example.com',
            'comments' => [
                ['text' => 'First comment'],
                ['text' => 'Second comment'],
            ],
        ]);

        $created = $this->mutator->create($record);

        $this->assertDatabaseCount('test_comments', 2);
        $this->assertDatabaseHas('test_comments', [
            'test_item_id' => $created->id(),
            'text'         => 'First comment',
        ]);
        $this->assertDatabaseHas('test_comments', [
            'test_item_id' => $created->id(),
            'text'         => 'Second comment',
        ]);
    }

    public function test_update_replaces_has_many_children(): void
    {
        $record = new EntityRecord($this->definition, [
            'name'     => 'Jack',
            'email'    => 'jack@example.com',
            'comments' => [
                ['text' => 'Original 1'],
                ['text' => 'Original 2'],
            ],
        ]);
        $created = $this->mutator->create($record);

        $this->mutator->update(
            new EntityKey($created->id(), 'int'),
            new EntityRecord($this->definition, [
                'name'     => 'Jack',
                'email'    => 'jack@example.com',
                'comments' => [
                    ['text' => 'Replacement 1'],
                ],
            ]),
        );

        $this->assertDatabaseCount('test_comments', 1);
        $this->assertDatabaseMissing('test_comments', ['text' => 'Original 1']);
        $this->assertDatabaseMissing('test_comments', ['text' => 'Original 2']);
        $this->assertDatabaseHas('test_comments', [
            'test_item_id' => $created->id(),
            'text'         => 'Replacement 1',
        ]);
    }

    public function test_update_with_empty_has_many_removes_all_children(): void
    {
        $record = new EntityRecord($this->definition, [
            'name'     => 'Kate',
            'email'    => 'kate@example.com',
            'comments' => [['text' => 'Will be removed']],
        ]);
        $created = $this->mutator->create($record);

        $this->mutator->update(
            new EntityKey($created->id(), 'int'),
            new EntityRecord($this->definition, [
                'name'     => 'Kate',
                'email'    => 'kate@example.com',
                'comments' => [],
            ]),
        );

        $this->assertDatabaseCount('test_comments', 0);
    }

    // ------------------------------------------------------------------
    // morphMany — sync (delete old + create new), morph_type auto-set
    // ------------------------------------------------------------------

    public function test_create_persists_morph_many_children_with_correct_morph_type(): void
    {
        $record = new EntityRecord($this->definition, [
            'name'          => 'Leo',
            'email'         => 'leo@example.com',
            'morphComments' => [
                ['text' => 'Morph comment one'],
                ['text' => 'Morph comment two'],
            ],
        ]);

        $created = $this->mutator->create($record);

        $this->assertDatabaseCount('test_morph_comments', 2);
        $this->assertDatabaseHas('test_morph_comments', [
            'commentable_type' => TestItem::class,
            'commentable_id'   => $created->id(),
            'text'             => 'Morph comment one',
        ]);
    }

    public function test_update_replaces_morph_many_children(): void
    {
        $record = new EntityRecord($this->definition, [
            'name'          => 'Mia',
            'email'         => 'mia@example.com',
            'morphComments' => [['text' => 'Old morph']],
        ]);
        $created = $this->mutator->create($record);

        $this->mutator->update(
            new EntityKey($created->id(), 'int'),
            new EntityRecord($this->definition, [
                'name'          => 'Mia',
                'email'         => 'mia@example.com',
                'morphComments' => [['text' => 'New morph']],
            ]),
        );

        $this->assertDatabaseCount('test_morph_comments', 1);
        $this->assertDatabaseHas('test_morph_comments', ['text' => 'New morph']);
    }

    // ------------------------------------------------------------------
    // belongsToMany — pre-shaped sync payload pass-through
    // ------------------------------------------------------------------

    public function test_belongs_to_many_passes_through_pre_shaped_sync_payload(): void
    {
        $t1 = TestTag::create(['name' => 'php']);
        $t2 = TestTag::create(['name' => 'laravel']);

        $definition = new TestItemWithRelationsDefinition();

        $record = new EntityRecord($definition, [
            'name'  => 'PreShaped',
            'email' => 'preshaped@example.com',
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

    // ------------------------------------------------------------------
    // delete — detach belongsToMany pivots
    // ------------------------------------------------------------------

    public function test_delete_detaches_belongs_to_many_pivots(): void
    {
        $tag1 = TestTag::create(['name' => 'x']);
        $tag2 = TestTag::create(['name' => 'y']);

        $record = new EntityRecord($this->definition, [
            'name'  => 'Nick',
            'email' => 'nick@example.com',
            'tags'  => [$tag1->id, $tag2->id],
        ]);
        $created = $this->mutator->create($record);

        $this->assertDatabaseCount('test_item_tag', 2);

        $this->mutator->delete(new EntityKey($created->id(), 'int'), $this->definition);

        $this->assertDatabaseCount('test_item_tag', 0);
        $this->assertDatabaseCount('test_tags', 2);  // tags themselves survive
    }

    public function test_delete_does_not_remove_has_many_children_by_default(): void
    {
        // hasMany cascade handled by DB-level FK or ->owns() (blocker #8).
        // Default mutator behavior: leave orphans, respect DB constraints.
        $record = new EntityRecord($this->definition, [
            'name'     => 'Olga',
            'email'    => 'olga@example.com',
            'comments' => [['text' => 'will be orphaned']],
        ]);
        $created = $this->mutator->create($record);

        $this->mutator->delete(new EntityKey($created->id(), 'int'), $this->definition);

        // Children залишаються — DB не має cascade FK в тестовій схемі.
        $this->assertDatabaseCount('test_comments', 1);
    }

    // ------------------------------------------------------------------
    // Transaction safety
    // ------------------------------------------------------------------

    public function test_create_rolls_back_host_when_relation_apply_fails(): void
    {
        // belongsToMany sync з неіснуючим tag id → FK constraint fail на реальній DB,
        // але SQLite без enforce_fk може не кинути — використай callback що throws.
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
                    BelongsToManyField::make('tags', TestTag::class)
                        ->pivotPayload(function ($id, array $host): array {
                            throw new RuntimeException('simulated sync failure');
                        }),
                ];
            }
        };

        $tag = TestTag::create(['name' => 'temp']);

        $record = new EntityRecord($definition, [
            'name'  => 'Paul',
            'email' => 'paul@example.com',
            'tags'  => [$tag->id],
        ]);

        try {
            $this->mutator->create($record);
            $this->fail('Expected RuntimeException to propagate.');
        } catch (RuntimeException $e) {
            $this->assertSame('simulated sync failure', $e->getMessage());
        }

        // Host row has been rolled back.
        $this->assertDatabaseMissing('test_items', ['email' => 'paul@example.com']);
        $this->assertDatabaseCount('test_item_tag', 0);
    }
}
