<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Infrastructure\Validation;

use BlackParadise\LaravelAdmin\Infrastructure\Validation\LaravelValidationProvider;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Factory as ValidationFactory;
use ReflectionMethod;

/**
 * Connection-aware resolveRelationExists() in LaravelValidationProvider (M1/S1).
 * One RED test (named connection) + one regression-guard (default connection, --pre-green).
 *
 * Seam: reflection on the private resolveRelationExists() method.
 * No DB setup needed: getConnectionName() reads the model's $connection property;
 * resolveRelationExists() never calls getConnection().
 *
 * Pre-IMPL failure mode: resolveRelationExists() omits the connection prefix →
 *   returns 'exists:sec_targets,id' instead of 'exists:secondary.sec_targets,id' → FAIL → RED.
 */
final class LaravelValidationProviderConnectionAwareTest extends TestCase
{
    private LaravelValidationProvider $provider;

    private ReflectionMethod $resolveRelationExists;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = new LaravelValidationProvider(resolve(ValidationFactory::class));

        $method = new ReflectionMethod(LaravelValidationProvider::class, 'resolveRelationExists');

        $this->resolveRelationExists = $method;
    }

    /**
     * A model with a named connection must produce an exists rule prefixed
     * with '<connectionName>.<table>,<key>'.
     *
     * Pre-IMPL: resolveRelationExists() returns 'exists:sec_targets,id' (no prefix) → FAILS → RED.
     * Post-IMPL: returns 'exists:secondary.sec_targets,id' → passes.
     */
    public function test_resolve_relation_exists_with_named_connection_prefixes_table_with_connection_name(): void
    {
        /** @var string|null $rule */
        $rule = $this->resolveRelationExists->invoke(
            $this->provider,
            'relation_exists:' . SecondaryConnModel::class,
        );

        self::assertSame('exists:secondary.sec_targets,id', $rule);
    }

    /**
     * A model without an explicit connection must produce an unprefixed exists rule.
     * This locks that the IMPL does not prefix when getConnectionName() returns null.
     *
     * Pre-IMPL: already returns 'exists:default_conn_targets,id' → GREEN → declared --pre-green.
     * Post-IMPL: still returns 'exists:default_conn_targets,id' → passes.
     */
    public function test_resolve_relation_exists_with_default_connection_yields_unprefixed_rule(): void
    {
        /** @var string|null $rule */
        $rule = $this->resolveRelationExists->invoke(
            $this->provider,
            'relation_exists:' . DefaultConnModel::class,
        );

        self::assertSame('exists:default_conn_targets,id', $rule);
    }
}

// ---------------------------------------------------------------------------
// Inline Eloquent model fixtures
// ---------------------------------------------------------------------------

/**
 * Minimal model on a named ('secondary') connection.
 * Used to assert that resolveRelationExists() prefixes the table with the connection name.
 */
final class SecondaryConnModel extends Model
{
    protected $connection = 'secondary';

    protected $table = 'sec_targets';
}

/**
 * Minimal model on the default connection (no $connection property).
 * Used to assert that resolveRelationExists() does NOT prefix when getConnectionName() is null.
 */
final class DefaultConnModel extends Model
{
    protected $table = 'default_conn_targets';
}
