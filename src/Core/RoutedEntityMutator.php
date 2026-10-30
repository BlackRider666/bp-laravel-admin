<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Core;

use BlackParadise\CoreAdmin\Domain\Contracts\Entity\EntityRecordContract;
use BlackParadise\CoreAdmin\Domain\Contracts\EntityDefinition\EntityDefinitionContract;
use BlackParadise\CoreAdmin\Domain\Mutators\EntityMutatorInterface;
use BlackParadise\CoreAdmin\Domain\ValueObjects\EntityKey;

/**
 * Mutator that forwards every call to the mutator selected for the record's definition.
 * Lets the graph use cases (which take a single mutator) honour per-definition writers.
 */
final readonly class RoutedEntityMutator implements EntityMutatorInterface
{
    public function __construct(
        private EntityPersistenceResolver $resolver,
    ) {}

    public function create(EntityRecordContract $record): EntityRecordContract
    {
        return $this->resolver->mutatorFor($record->definition())->create($record);
    }

    public function update(EntityKey $key, EntityRecordContract $record): EntityRecordContract
    {
        return $this->resolver->mutatorFor($record->definition())->update($key, $record);
    }

    public function delete(EntityKey $key, EntityDefinitionContract $entityDefinition): bool
    {
        return $this->resolver->mutatorFor($entityDefinition)->delete($key, $entityDefinition);
    }
}
