<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Core;

use BlackParadise\CoreAdmin\Domain\Contracts\Entity\EntityRecordContract;
use BlackParadise\CoreAdmin\Domain\Contracts\EntityDefinition\EntityDefinitionContract;
use BlackParadise\CoreAdmin\Domain\Query\Criteria;
use BlackParadise\CoreAdmin\Domain\Query\PaginatedResult;
use BlackParadise\CoreAdmin\Domain\Repositories\EntityRepositoryInterface;
use BlackParadise\CoreAdmin\Domain\ValueObjects\EntityKey;

/**
 * Repository that forwards every call to the repository selected for the definition.
 * Lets the graph use cases (which take a single repository) honour per-definition sources.
 */
final readonly class RoutedEntityRepository implements EntityRepositoryInterface
{
    public function __construct(
        private EntityPersistenceResolver $resolver,
    ) {}

    public function list(EntityDefinitionContract $entityDefinition, Criteria $criteria): PaginatedResult
    {
        return $this->resolver->repositoryFor($entityDefinition)->list($entityDefinition, $criteria);
    }

    public function find(EntityDefinitionContract $entityDefinition, EntityKey $key): ?EntityRecordContract
    {
        return $this->resolver->repositoryFor($entityDefinition)->find($entityDefinition, $key);
    }

    public function exists(EntityDefinitionContract $entityDefinition, EntityKey $key): bool
    {
        return $this->resolver->repositoryFor($entityDefinition)->exists($entityDefinition, $key);
    }
}
