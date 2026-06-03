<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Infrastructure;

use BlackParadise\CoreAdmin\Domain\Contracts\Auth\AuthorizationProviderContract;
use BlackParadise\CoreAdmin\Domain\Entity\EntityRecord;
use BlackParadise\CoreAdmin\Domain\Exceptions\ValidationException;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\CoreAdmin\Domain\Mutators\EntityMutatorInterface;
use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Mockery;

/**
 * Integration tests for H3 — SQLSTATE 23000 sub-classification.
 *
 * UNIQUE violation (MySQL 1062 / SQLite "UNIQUE constraint failed")
 *   → domain ValidationException (HTTP 422) with '_database' key.
 *
 * FK violation (SQLite "FOREIGN KEY constraint failed")
 *   → raw QueryException re-thrown (HTTP 500 — server-side bug, not user error).
 */
final class EloquentEntityMutatorConstraintViolationTest extends TestCase
{
    use RefreshDatabase;

    private EntityMutatorInterface $mutator;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Enable FK enforcement on SQLite at the connection level (before the
        // connector opens the PDO handle). This is the only reliable way to
        // activate FK checks before RefreshDatabase opens the transaction.
        $app['config']->set('database.connections.testing.foreign_key_constraints', true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('constraint_parents', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->timestamps();
        });

        Schema::create('constraint_children', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->unsignedBigInteger('parent_id');
            $table->foreign('parent_id')->references('id')->on('constraint_parents');
            $table->timestamps();
        });

        $definition = new class extends EntityDefinition {
            public string $model = ConstraintParentModel::class;

            public function resolveName(): string
            {
                return 'constraint_parent';
            }

            public function fields(): array
            {
                return [TextField::make('code')];
            }
        };

        /** @var EntityDefinitionRegistry $registry */
        $registry = $this->app->make(EntityDefinitionRegistry::class);
        $registry->register($definition);

        $this->app->bind(AuthorizationProviderContract::class, function (): AuthorizationProviderContract {
            $mock = Mockery::mock(AuthorizationProviderContract::class);
            $mock->shouldReceive('can')->andReturn(true);
            return $mock;
        });

        $this->mutator = $this->app->make(EntityMutatorInterface::class);
    }

    // -------------------------------------------------------------------------
    // H3-A: UNIQUE violation → ValidationException (field-level 422)
    // -------------------------------------------------------------------------

    public function test_unique_constraint_violation_converts_to_validation_exception(): void
    {
        ConstraintParentModel::create(['code' => 'ALPHA']);

        $definition = $this->makeParentDefinition();

        $record = new EntityRecord($definition, ['code' => 'ALPHA']); // duplicate

        $this->expectException(ValidationException::class);

        $this->mutator->create($record);
    }

    public function test_unique_constraint_validation_exception_has_database_key(): void
    {
        ConstraintParentModel::create(['code' => 'ALPHA']);

        $definition = $this->makeParentDefinition();
        $record = new EntityRecord($definition, ['code' => 'ALPHA']);

        try {
            $this->mutator->create($record);
            self::fail('Expected ValidationException.');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('_database', $e->errors());
        }
    }

    // -------------------------------------------------------------------------
    // H3-B: FK violation → QueryException re-thrown (NOT a unique-422)
    // -------------------------------------------------------------------------

    /**
     * A child row with a non-existent parent_id must NOT produce a user-facing
     * ValidationException. It must re-throw the raw QueryException so the
     * framework renders HTTP 500 (it is a server-side bug, not user input error).
     */
    public function test_foreign_key_violation_rethrows_query_exception_not_validation_exception(): void
    {
        $childDefinition = new class extends EntityDefinition {
            public string $model = ConstraintChildModel::class;

            public function resolveName(): string
            {
                return 'constraint_child';
            }

            public function fields(): array
            {
                return [
                    TextField::make('code'),
                    TextField::make('parent_id'),
                ];
            }
        };

        /** @var EntityDefinitionRegistry $registry */
        $registry = $this->app->make(EntityDefinitionRegistry::class);
        $registry->register($childDefinition);

        // parent_id=99999 does not exist → FK violation.
        $record = new EntityRecord($childDefinition, [
            'code'      => 'CHILD-1',
            'parent_id' => '99999',
        ]);

        // Must NOT throw ValidationException — must throw QueryException.
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/FOREIGN KEY|constraint/i');

        $this->mutator->create($record);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeParentDefinition(): EntityDefinition
    {
        return new class extends EntityDefinition {
            public string $model = ConstraintParentModel::class;

            public function resolveName(): string
            {
                return 'constraint_parent';
            }

            public function fields(): array
            {
                return [TextField::make('code')];
            }
        };
    }
}

// ---------------------------------------------------------------------------
// Inline model fixtures
// ---------------------------------------------------------------------------

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $code
 */
class ConstraintParentModel extends Model
{
    protected $table    = 'constraint_parents';
    protected $fillable = ['code'];
}

/**
 * @property int $id
 * @property string $code
 * @property int $parent_id
 */
class ConstraintChildModel extends Model
{
    protected $table    = 'constraint_children';
    protected $fillable = ['code', 'parent_id'];
}
