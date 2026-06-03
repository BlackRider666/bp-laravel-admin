<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Infrastructure;

use BlackParadise\CoreAdmin\Domain\Contracts\Auth\AuthorizationProviderContract;
use BlackParadise\CoreAdmin\Domain\Entity\EntityRecord;
use BlackParadise\CoreAdmin\Domain\Exceptions\ValidationException;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\CoreAdmin\Domain\Mutators\EntityMutatorInterface;
use BlackParadise\CoreAdmin\Domain\ValueObjects\EntityKey;
use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Mockery;

/**
 * Integration tests for Bug #6 — DB errors converted to domain ValidationException.
 *
 * Uses a real SQLite database with a UNIQUE constraint to trigger SQLSTATE 23000
 * and verifies the mutator converts it to a domain ValidationException instead
 * of propagating the raw QueryException (which would result in HTTP 500).
 */
final class EloquentEntityMutatorDbExceptionTest extends TestCase
{
    use RefreshDatabase;

    private EntityMutatorInterface $mutator;
    private EntityDefinition $definition;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a table with a UNIQUE email column to trigger 23000.
        Schema::create('test_unique_items', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        $this->definition = new class extends EntityDefinition {
            public string $model = TestUniqueItem::class;

            public function resolveName(): string
            {
                return 'test_unique_item';
            }

            public function fields(): array
            {
                return [
                    TextField::make('name'),
                    TextField::make('email'),
                ];
            }
        };

        /** @var EntityDefinitionRegistry $registry */
        $registry = $this->app->make(EntityDefinitionRegistry::class);
        $registry->register($this->definition);

        $this->app->bind(AuthorizationProviderContract::class, function (): AuthorizationProviderContract {
            $mock = Mockery::mock(AuthorizationProviderContract::class);
            $mock->shouldReceive('can')->andReturn(true);
            return $mock;
        });

        $this->mutator = $this->app->make(EntityMutatorInterface::class);
    }

    public function test_create_converts_unique_violation_to_validation_exception(): void
    {
        // Seed the first record.
        TestUniqueItem::create(['name' => 'Alice', 'email' => 'alice@example.com']);

        $record = new EntityRecord($this->definition, [
            'name'  => 'Alice Duplicate',
            'email' => 'alice@example.com', // same email — triggers UNIQUE constraint
        ]);

        $this->expectException(ValidationException::class);

        $this->mutator->create($record);
    }

    public function test_update_converts_unique_violation_to_validation_exception(): void
    {
        $item1 = TestUniqueItem::create(['name' => 'Alice', 'email' => 'alice@example.com']);
        TestUniqueItem::create(['name' => 'Bob', 'email' => 'bob@example.com']);

        $record = new EntityRecord($this->definition, [
            'name'  => 'Alice Updated',
            'email' => 'bob@example.com', // taken by Bob
        ]);

        $this->expectException(ValidationException::class);

        $key = new EntityKey($item1->id, 'int');
        $this->mutator->update($key, $record);
    }

    public function test_validation_exception_has_database_error_key(): void
    {
        TestUniqueItem::create(['name' => 'Alice', 'email' => 'alice@example.com']);

        $record = new EntityRecord($this->definition, [
            'name'  => 'Duplicate',
            'email' => 'alice@example.com',
        ]);

        try {
            $this->mutator->create($record);
            self::fail('ValidationException was not thrown.');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('_database', $e->errors());
        }
    }
}

// ---------------------------------------------------------------------------
// Inline model fixture
// ---------------------------------------------------------------------------

use Illuminate\Database\Eloquent\Model;

/**
 * Minimal Eloquent model backed by the test_unique_items table.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 */
class TestUniqueItem extends Model
{
    protected $table    = 'test_unique_items';
    protected $fillable = ['name', 'email'];
}
