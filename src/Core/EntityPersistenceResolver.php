<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Core;

use BlackParadise\CoreAdmin\Domain\Contracts\EntityDefinition\EntityDefinitionContract;
use BlackParadise\CoreAdmin\Domain\Mutators\EntityMutatorInterface;
use BlackParadise\CoreAdmin\Domain\Repositories\EntityRepositoryInterface;
use Illuminate\Contracts\Container\Container;

/**
 * Picks the repository / mutator for a definition: the definition's own class-string
 * (resolved through the container) or, when it declares none, the global binding.
 *
 * Not cached on purpose: instances are produced by the container on every call, so lifetime
 * (singleton vs transient) is governed by the container binding of each repository/mutator
 * class, and the resolver stays stateless (readonly) and safe under Octane.
 */
final readonly class EntityPersistenceResolver
{
    public function __construct(
        private Container $container,
    ) {}

    public function repositoryFor(EntityDefinitionContract $definition): EntityRepositoryInterface
    {
        $class = $definition->repository();

        /** @var EntityRepositoryInterface */
        return $this->container->make($class ?? EntityRepositoryInterface::class);
    }

    public function mutatorFor(EntityDefinitionContract $definition): EntityMutatorInterface
    {
        $class = $definition->mutator();

        /** @var EntityMutatorInterface */
        return $this->container->make($class ?? EntityMutatorInterface::class);
    }
}
