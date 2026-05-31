<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Infrastructure;

use BlackParadise\CoreAdmin\Domain\Entity\EntityRecord;
use BlackParadise\CoreAdmin\Domain\Mutators\EntityMutatorInterface;
use BlackParadise\CoreAdmin\Domain\ValueObjects\EntityKey;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\CreatesTestItemTable;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItem;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItemDefinition;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Integration tests for EloquentEntityMutator.
 *
 * Verifies create, update, and delete operations against a real in-memory
 * SQLite database. Each test asserts both the returned domain object and
 * the persisted database state.
 */
final class EloquentEntityMutatorTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTestItemTable;

    private EntityMutatorInterface $mutator;
    private TestItemDefinition $definition;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestItemFixtures();

        $this->mutator    = $this->app->make(EntityMutatorInterface::class);
        $this->definition = new TestItemDefinition();
    }

    // ------------------------------------------------------------------
    // create()
    // ------------------------------------------------------------------

    public function test_create_persists_record_to_database(): void
    {
        $record = new EntityRecord($this->definition, ['name' => 'Alice', 'email' => 'alice@example.com']);

        $this->mutator->create($record);

        $this->assertDatabaseHas('test_items', ['name' => 'Alice', 'email' => 'alice@example.com']);
    }

    public function test_create_returns_entity_record_with_assigned_id(): void
    {
        $record  = new EntityRecord($this->definition, ['name' => 'Bob', 'email' => 'bob@example.com']);
        $created = $this->mutator->create($record);

        $this->assertNotNull($created->id());
        $this->assertIsInt($created->id());
    }

    public function test_create_returns_entity_record_with_correct_attributes(): void
    {
        $record  = new EntityRecord($this->definition, ['name' => 'Carol', 'email' => 'carol@example.com']);
        $created = $this->mutator->create($record);

        $this->assertSame('Carol', $created->get('name'));
        $this->assertSame('carol@example.com', $created->get('email'));
    }

    public function test_create_ignores_attributes_not_declared_in_definition(): void
    {
        $record = new EntityRecord($this->definition, [
            'name'           => 'Dan',
            'email'          => 'dan@example.com',
            'undeclared_col' => 'should be ignored',
        ]);

        // Should not throw even though 'undeclared_col' is not a real column.
        $created = $this->mutator->create($record);

        $this->assertSame('Dan', $created->get('name'));
    }

    public function test_create_increments_total_record_count(): void
    {
        $this->assertSame(0, TestItem::count());

        $record = new EntityRecord($this->definition, ['name' => 'Eve', 'email' => 'eve@example.com']);
        $this->mutator->create($record);

        $this->assertSame(1, TestItem::count());
    }

    // ------------------------------------------------------------------
    // update()
    // ------------------------------------------------------------------

    public function test_update_modifies_record_in_database(): void
    {
        $item = TestItem::create(['name' => 'Frank', 'email' => 'frank@example.com']);

        $key    = new EntityKey($item->id, 'int');
        $record = new EntityRecord($this->definition, ['name' => 'Frank Updated', 'email' => 'frank-updated@example.com']);

        $this->mutator->update($key, $record);

        $this->assertDatabaseHas('test_items', [
            'id'    => $item->id,
            'name'  => 'Frank Updated',
            'email' => 'frank-updated@example.com',
        ]);
    }

    public function test_update_returns_entity_record_with_updated_values(): void
    {
        $item = TestItem::create(['name' => 'Grace', 'email' => 'grace@example.com']);

        $key     = new EntityKey($item->id, 'int');
        $record  = new EntityRecord($this->definition, ['name' => 'Grace Updated', 'email' => 'grace-updated@example.com']);
        $updated = $this->mutator->update($key, $record);

        $this->assertSame('Grace Updated', $updated->get('name'));
        $this->assertSame('grace-updated@example.com', $updated->get('email'));
    }

    public function test_update_throws_when_record_not_found(): void
    {
        $key    = new EntityKey(99999, 'int');
        $record = new EntityRecord($this->definition, ['name' => 'Ghost', 'email' => 'ghost@example.com']);

        $this->expectException(ModelNotFoundException::class);

        $this->mutator->update($key, $record);
    }

    // ------------------------------------------------------------------
    // delete()
    // ------------------------------------------------------------------

    public function test_delete_removes_record_from_database(): void
    {
        $item = TestItem::create(['name' => 'Harry', 'email' => 'harry@example.com']);

        $key = new EntityKey($item->id, 'int');
        $this->mutator->delete($key, $this->definition);

        $this->assertDatabaseMissing('test_items', ['id' => $item->id]);
    }

    public function test_delete_returns_true_when_record_was_deleted(): void
    {
        $item = TestItem::create(['name' => 'Iris', 'email' => 'iris@example.com']);

        $key    = new EntityKey($item->id, 'int');
        $result = $this->mutator->delete($key, $this->definition);

        $this->assertTrue($result);
    }

    public function test_delete_returns_false_when_record_does_not_exist(): void
    {
        $key    = new EntityKey(99999, 'int');
        $result = $this->mutator->delete($key, $this->definition);

        $this->assertFalse($result);
    }
}
