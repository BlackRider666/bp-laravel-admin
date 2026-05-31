<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Unit\Core;

use BlackParadise\CoreAdmin\Domain\Contracts\EntityDefinition\EntityDefinitionContract;
use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use BlackParadise\LaravelAdmin\EntityDefinition;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Unit tests for EntityDefinitionRegistry.
 *
 * Verifies register, get, has, all, and count behaviour using lightweight
 * anonymous-class stubs — no Laravel boot required.
 */
final class EntityDefinitionRegistryTest extends TestCase
{
    private EntityDefinitionRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new EntityDefinitionRegistry();
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Create a minimal concrete EntityDefinition stub with a given class-basename.
     * The class-basename determines the value returned by resolveName(), which is
     * the key used by the registry.
     *
     * Because resolveName() uses static::class we create an anonymous class that
     * extends EntityDefinition so the logic runs correctly.
     */
    private function makeDefinition(string $expectedName): EntityDefinition
    {
        return new class ($expectedName) extends EntityDefinition {
            public string $model = stdClass::class;

            public function __construct(private readonly string $forcedName) {}

            public function resolveName(): string
            {
                return $this->forcedName;
            }

            public function fields(): array
            {
                return [];
            }
        };
    }

    // ------------------------------------------------------------------
    // register()
    // ------------------------------------------------------------------

    public function test_register_adds_definition_to_registry(): void
    {
        $definition = $this->makeDefinition('users');

        $this->registry->register($definition);

        self::assertTrue($this->registry->has('users'));
    }

    public function test_register_overwrites_existing_definition_with_same_name(): void
    {
        $first  = $this->makeDefinition('users');
        $second = $this->makeDefinition('users');

        $this->registry->register($first);
        $this->registry->register($second);

        self::assertSame($second, $this->registry->get('users'));
    }

    public function test_register_multiple_definitions_with_different_names(): void
    {
        $this->registry->register($this->makeDefinition('users'));
        $this->registry->register($this->makeDefinition('posts'));

        self::assertTrue($this->registry->has('users'));
        self::assertTrue($this->registry->has('posts'));
    }

    // ------------------------------------------------------------------
    // get()
    // ------------------------------------------------------------------

    public function test_get_returns_registered_definition(): void
    {
        $definition = $this->makeDefinition('orders');

        $this->registry->register($definition);

        self::assertSame($definition, $this->registry->get('orders'));
    }

    public function test_get_throws_when_name_not_registered(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("EntityDefinition 'unknown' not registered.");

        $this->registry->get('unknown');
    }

    public function test_get_returns_entity_definition_contract(): void
    {
        $definition = $this->makeDefinition('products');
        $this->registry->register($definition);

        $result = $this->registry->get('products');

        self::assertInstanceOf(EntityDefinitionContract::class, $result);
    }

    // ------------------------------------------------------------------
    // has()
    // ------------------------------------------------------------------

    public function test_has_returns_false_for_empty_registry(): void
    {
        self::assertFalse($this->registry->has('anything'));
    }

    public function test_has_returns_true_after_registration(): void
    {
        $this->registry->register($this->makeDefinition('invoices'));

        self::assertTrue($this->registry->has('invoices'));
    }

    public function test_has_returns_false_for_unregistered_name(): void
    {
        $this->registry->register($this->makeDefinition('invoices'));

        self::assertFalse($this->registry->has('orders'));
    }

    // ------------------------------------------------------------------
    // all()
    // ------------------------------------------------------------------

    public function test_all_returns_empty_array_when_no_definitions_registered(): void
    {
        self::assertSame([], $this->registry->all());
    }

    public function test_all_returns_all_registered_definitions_keyed_by_name(): void
    {
        $users  = $this->makeDefinition('users');
        $orders = $this->makeDefinition('orders');

        $this->registry->register($users);
        $this->registry->register($orders);

        $all = $this->registry->all();

        self::assertArrayHasKey('users', $all);
        self::assertArrayHasKey('orders', $all);
        self::assertSame($users, $all['users']);
        self::assertSame($orders, $all['orders']);
    }

    public function test_all_count_matches_number_of_distinct_registrations(): void
    {
        $this->registry->register($this->makeDefinition('a'));
        $this->registry->register($this->makeDefinition('b'));
        $this->registry->register($this->makeDefinition('c'));

        self::assertCount(3, $this->registry->all());
    }

    // ------------------------------------------------------------------
    // count()
    // ------------------------------------------------------------------

    public function test_count_returns_zero_for_empty_registry(): void
    {
        self::assertSame(0, $this->registry->count());
    }

    public function test_count_reflects_number_of_registered_definitions(): void
    {
        $this->registry->register($this->makeDefinition('x'));
        $this->registry->register($this->makeDefinition('y'));

        self::assertSame(2, $this->registry->count());
    }

    public function test_count_does_not_increase_when_overwriting_same_name(): void
    {
        $this->registry->register($this->makeDefinition('same'));
        $this->registry->register($this->makeDefinition('same'));

        self::assertSame(1, $this->registry->count());
    }
}
