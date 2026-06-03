<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Infrastructure;

use BlackParadise\CoreAdmin\Domain\Entity\EntityRecord;
use BlackParadise\CoreAdmin\Domain\Fields\HasManyField;
use BlackParadise\CoreAdmin\Domain\Fields\MorphManyField;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\CoreAdmin\Domain\Mutators\EntityMutatorInterface;
use BlackParadise\CoreAdmin\Domain\ValueObjects\EntityKey;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\CreatesRelationFixtures;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\CreatesTestItemTable;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestComment;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItem;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestMorphComment;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Sleep;

/**
 * Verifies HasManyField/MorphManyField strategy semantics in RelationWriter:
 *  - replace: per-model delete + recreate (fires events, respects SoftDeletes)
 *  - merge:   update existing-by-id, create new, delete missing
 *  - append:  create only (never touch existing)
 */
final class RelationWriterStrategyTest extends TestCase
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

        $this->mutator = $this->app->make(EntityMutatorInterface::class);
    }

    // ------------------------------------------------------------------
    // append
    // ------------------------------------------------------------------

    public function test_append_strategy_does_not_delete_existing_has_many_children(): void
    {
        $definition = $this->definitionWithStrategy('append');

        $created = $this->mutator->create(new EntityRecord($definition, [
            'name'     => 'Alice',
            'email'    => 'alice@example.com',
            'comments' => [['text' => 'Original 1'], ['text' => 'Original 2']],
        ]));

        $this->mutator->update(
            new EntityKey($created->id(), 'int'),
            new EntityRecord($definition, [
                'name'     => 'Alice',
                'email'    => 'alice@example.com',
                'comments' => [['text' => 'New 1']],
            ]),
        );

        $this->assertDatabaseCount('test_comments', 3);
        $this->assertDatabaseHas('test_comments', ['text' => 'Original 1']);
        $this->assertDatabaseHas('test_comments', ['text' => 'Original 2']);
        $this->assertDatabaseHas('test_comments', ['text' => 'New 1']);
    }

    // ------------------------------------------------------------------
    // merge
    // ------------------------------------------------------------------

    public function test_merge_strategy_updates_existing_creates_new_and_deletes_missing(): void
    {
        $definition = $this->definitionWithStrategy('merge');

        $created = $this->mutator->create(new EntityRecord($definition, [
            'name'     => 'Bob',
            'email'    => 'bob@example.com',
            'comments' => [['text' => 'Keep this'], ['text' => 'Remove this']],
        ]));

        $keep   = TestComment::query()->where('text', 'Keep this')->firstOrFail();
        $remove = TestComment::query()->where('text', 'Remove this')->firstOrFail();

        $this->mutator->update(
            new EntityKey($created->id(), 'int'),
            new EntityRecord($definition, [
                'name'     => 'Bob',
                'email'    => 'bob@example.com',
                'comments' => [
                    ['id' => $keep->id, 'text' => 'Updated text'], // update
                    ['text' => 'Brand new'],                        // create
                    // $remove->id absent — must be deleted
                ],
            ]),
        );

        $this->assertDatabaseCount('test_comments', 2);
        $this->assertDatabaseHas('test_comments', ['id' => $keep->id, 'text' => 'Updated text']);
        $this->assertDatabaseHas('test_comments', ['text' => 'Brand new']);
        $this->assertDatabaseMissing('test_comments', ['id' => $remove->id]);
    }

    public function test_merge_strategy_preserves_created_at_on_updated_rows(): void
    {
        $definition = $this->definitionWithStrategy('merge');

        $created = $this->mutator->create(new EntityRecord($definition, [
            'name'     => 'Carol',
            'email'    => 'carol@example.com',
            'comments' => [['text' => 'Stable']],
        ]));

        $original   = TestComment::query()->where('text', 'Stable')->firstOrFail();
        $originalAt = $original->created_at;

        // small wait so timestamps would visibly diverge if a replace happened
        Sleep::usleep(10000);

        $this->mutator->update(
            new EntityKey($created->id(), 'int'),
            new EntityRecord($definition, [
                'name'     => 'Carol',
                'email'    => 'carol@example.com',
                'comments' => [['id' => $original->id, 'text' => 'Stable v2']],
            ]),
        );

        $after = TestComment::query()->where('id', $original->id)->firstOrFail();
        $this->assertSame('Stable v2', $after->text);
        $this->assertSame((string) $originalAt, (string) $after->created_at);
    }

    // ------------------------------------------------------------------
    // replace (default) for morph_many
    // ------------------------------------------------------------------

    public function test_morph_many_replace_strategy_uses_per_model_delete(): void
    {
        $definition = $this->definitionWithMorphStrategy('replace');

        $created = $this->mutator->create(new EntityRecord($definition, [
            'name'          => 'Dave',
            'email'         => 'dave@example.com',
            'morphComments' => [['text' => 'A'], ['text' => 'B']],
        ]));

        $this->mutator->update(
            new EntityKey($created->id(), 'int'),
            new EntityRecord($definition, [
                'name'          => 'Dave',
                'email'         => 'dave@example.com',
                'morphComments' => [['text' => 'C']],
            ]),
        );

        $this->assertDatabaseCount('test_morph_comments', 1);
        $this->assertDatabaseHas('test_morph_comments', ['text' => 'C']);
    }

    public function test_morph_many_append_strategy_does_not_delete(): void
    {
        $definition = $this->definitionWithMorphStrategy('append');

        $created = $this->mutator->create(new EntityRecord($definition, [
            'name'          => 'Eve',
            'email'         => 'eve@example.com',
            'morphComments' => [['text' => 'X']],
        ]));

        $this->mutator->update(
            new EntityKey($created->id(), 'int'),
            new EntityRecord($definition, [
                'name'          => 'Eve',
                'email'         => 'eve@example.com',
                'morphComments' => [['text' => 'Y']],
            ]),
        );

        $this->assertDatabaseCount('test_morph_comments', 2);
        $this->assertDatabaseHas('test_morph_comments', ['text' => 'X']);
        $this->assertDatabaseHas('test_morph_comments', ['text' => 'Y']);
    }

    // ------------------------------------------------------------------
    // helpers
    // ------------------------------------------------------------------

    /**
     * @param 'replace'|'merge'|'append' $strategy
     */
    private function definitionWithStrategy(string $strategy): EntityDefinition
    {
        return new class ($strategy) extends EntityDefinition {
            public string $model = TestItem::class;

            /**
             * @param 'replace'|'merge'|'append' $strat
             */
            public function __construct(private readonly string $strat) {}

            public function resolveName(): string
            {
                return 'test_item';
            }

            public function fields(): array
            {
                return [
                    TextField::make('name'),
                    TextField::make('email'),
                    HasManyField::make('comments', TestComment::class)->strategy($this->strat),
                ];
            }
        };
    }

    /**
     * @param 'replace'|'merge'|'append' $strategy
     */
    private function definitionWithMorphStrategy(string $strategy): EntityDefinition
    {
        return new class ($strategy) extends EntityDefinition {
            public string $model = TestItem::class;

            /**
             * @param 'replace'|'merge'|'append' $strat
             */
            public function __construct(private readonly string $strat) {}

            public function resolveName(): string
            {
                return 'test_item';
            }

            public function fields(): array
            {
                return [
                    TextField::make('name'),
                    TextField::make('email'),
                    MorphManyField::make('morphComments', TestMorphComment::class)->strategy($this->strat),
                ];
            }
        };
    }
}
