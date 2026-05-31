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

    /**
     * Register an entity definition.
     * If a definition with the same name already exists it is overwritten.
     * Uses {@see EntityDefinition::resolveName()} to derive the registry key.
     */
    public function register(EntityDefinition $definition): void
    {
        $this->definitions[$definition->resolveName()] = $definition;
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
