<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Unit\Core;

use BlackParadise\CoreAdmin\Domain\Fields\Base\AbstractField;
use BlackParadise\CoreAdmin\Domain\Fields\NumberField;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\Core\FieldTypeRegistry;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Unit tests for FieldTypeRegistry.
 *
 * Verifies register, resolve, has, and all behaviour including error paths.
 */
final class FieldTypeRegistryTest extends TestCase
{
    private FieldTypeRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new FieldTypeRegistry();
    }

    // ------------------------------------------------------------------
    // register()
    // ------------------------------------------------------------------

    public function test_register_adds_type_mapping(): void
    {
        $this->registry->register('text', TextField::class);

        self::assertTrue($this->registry->has('text'));
    }

    public function test_register_allows_overwriting_existing_type(): void
    {
        $this->registry->register('text', TextField::class);
        $this->registry->register('text', NumberField::class);

        self::assertSame(NumberField::class, $this->registry->resolve('text'));
    }

    public function test_register_throws_when_class_does_not_exist(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Field class 'NonExistent\\Field' does not exist.");

        $this->registry->register('ghost', 'NonExistent\\Field');
    }

    public function test_register_throws_when_class_does_not_extend_abstract_field(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must extend');

        $this->registry->register('bad', stdClass::class);
    }

    // ------------------------------------------------------------------
    // resolve()
    // ------------------------------------------------------------------

    public function test_resolve_returns_registered_class(): void
    {
        $this->registry->register('text', TextField::class);

        self::assertSame(TextField::class, $this->registry->resolve('text'));
    }

    public function test_resolve_throws_when_type_not_registered(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Field type 'unknown' not registered.");

        $this->registry->resolve('unknown');
    }

    public function test_resolve_returns_class_string_that_extends_abstract_field(): void
    {
        $this->registry->register('number', NumberField::class);

        $class = $this->registry->resolve('number');

        self::assertTrue(is_subclass_of($class, AbstractField::class));
    }

    // ------------------------------------------------------------------
    // has()
    // ------------------------------------------------------------------

    public function test_has_returns_false_for_empty_registry(): void
    {
        self::assertFalse($this->registry->has('text'));
    }

    public function test_has_returns_true_after_registration(): void
    {
        $this->registry->register('text', TextField::class);

        self::assertTrue($this->registry->has('text'));
    }

    public function test_has_returns_false_for_unregistered_type(): void
    {
        $this->registry->register('text', TextField::class);

        self::assertFalse($this->registry->has('image'));
    }

    // ------------------------------------------------------------------
    // all()
    // ------------------------------------------------------------------

    public function test_all_returns_empty_array_when_no_types_registered(): void
    {
        self::assertSame([], $this->registry->all());
    }

    public function test_all_returns_all_registered_mappings(): void
    {
        $this->registry->register('text', TextField::class);
        $this->registry->register('number', NumberField::class);

        $all = $this->registry->all();

        self::assertArrayHasKey('text', $all);
        self::assertArrayHasKey('number', $all);
        self::assertSame(TextField::class, $all['text']);
        self::assertSame(NumberField::class, $all['number']);
    }

    public function test_all_count_reflects_registered_types(): void
    {
        $this->registry->register('text', TextField::class);
        $this->registry->register('number', NumberField::class);

        self::assertCount(2, $this->registry->all());
    }
}
