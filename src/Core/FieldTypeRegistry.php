<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Core;

use BlackParadise\CoreAdmin\Domain\Fields\Base\AbstractField;
use InvalidArgumentException;

/**
 * Registry that maps field type strings to their concrete {@see AbstractField} class names.
 *
 * Default field types are registered by {@see \BlackParadise\LaravelAdmin\DashboardServiceProvider}.
 * Additional types can be registered from application service providers.
 */
final class FieldTypeRegistry
{
    /** @var array<string, class-string<AbstractField>> */
    private array $map = [];

    /**
     * Register a field type mapping.
     *
     * @param class-string<AbstractField> $fieldClass
     * @throws InvalidArgumentException when the class does not exist or does not extend AbstractField.
     */
    public function register(string $type, string $fieldClass): void
    {
        if (!class_exists($fieldClass)) {
            throw new InvalidArgumentException("Field class '{$fieldClass}' does not exist.");
        }

        if (!is_subclass_of($fieldClass, AbstractField::class)) {
            throw new InvalidArgumentException(
                "Field class '{$fieldClass}' must extend " . AbstractField::class . '.',
            );
        }

        $this->map[$type] = $fieldClass;
    }

    /**
     * Resolve the concrete field class for a given type string.
     *
     * @return class-string<AbstractField>
     * @throws InvalidArgumentException when the type is not registered.
     */
    public function resolve(string $type): string
    {
        if (!isset($this->map[$type])) {
            throw new InvalidArgumentException("Field type '{$type}' not registered.");
        }

        return $this->map[$type];
    }

    /**
     * Check whether a field type is registered.
     */
    public function has(string $type): bool
    {
        return isset($this->map[$type]);
    }

    /**
     * Return all registered type-to-class mappings.
     *
     * @return array<string, class-string<AbstractField>>
     */
    public function all(): array
    {
        return $this->map;
    }
}
