<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Core;

use BlackParadise\CoreAdmin\Domain\Contracts\EntityDefinition\EntityDefinitionContract;
use BlackParadise\LaravelAdmin\EntityDefinition;
use InvalidArgumentException;

/**
 * Registry that holds all registered {@see EntityDefinitionContract} instances.
 *
 * Definitions are keyed by their entity name (snake_case class basename).
 * The registry accepts any {@see EntityDefinitionContract} but the public API
 * is typed to {@see EntityDefinition} for registration (which carries resolveName()).
 * The registry is a singleton bound by {@see \BlackParadise\LaravelAdmin\DashboardServiceProvider}.
 */
final class EntityDefinitionRegistry
{
    /** @var array<string, EntityDefinitionContract> */
    private array $definitions = [];

    /** @var array<class-string, string> definition class => entity name */
    private array $namesByClass = [];

    /**
     * Register an entity definition.
     * If a definition with the same name already exists it is overwritten.
     * Uses {@see EntityDefinition::resolveName()} to derive the registry key.
     */
    public function register(EntityDefinition $definition): void
    {
        $name = $definition->resolveName();
        $this->definitions[$name] = $definition;
        $this->namesByClass[$definition::class] = $name;
    }

    /**
     * Retrieve a registered definition by its class-string (no instantiation, no name derivation).
     *
     * @throws InvalidArgumentException when no definition of that class is registered.
     */
    public function forClass(string $class): EntityDefinitionContract
    {
        return $this->forClassOrNull($class)
            ?? throw new InvalidArgumentException("EntityDefinition class '{$class}' not registered.");
    }

    public function forClassOrNull(string $class): ?EntityDefinitionContract
    {
        return isset($this->namesByClass[$class]) ? $this->definitions[$this->namesByClass[$class]] : null;
    }

    /**
     * Retrieve a registered entity definition by name.
     *
     * @throws InvalidArgumentException when the name is not registered.
     */
    public function get(string $name): EntityDefinitionContract
    {
        if (!isset($this->definitions[$name])) {
            throw new InvalidArgumentException("EntityDefinition '{$name}' not registered.");
        }

        return $this->definitions[$name];
    }

    /**
     * Check whether an entity definition is registered under the given name.
     */
    public function has(string $name): bool
    {
        return isset($this->definitions[$name]);
    }

    /**
     * Return all registered definitions keyed by entity name.
     *
     * @return array<string, EntityDefinitionContract>
     */
    public function all(): array
    {
        return $this->definitions;
    }

    /**
     * Return the number of registered entity definitions.
     */
    public function count(): int
    {
        return count($this->definitions);
    }
}
