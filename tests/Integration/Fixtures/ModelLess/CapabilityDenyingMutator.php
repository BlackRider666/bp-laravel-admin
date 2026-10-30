<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ModelLess;

use BlackParadise\CoreAdmin\Domain\Contracts\Entity\EntityRecordContract;
use BlackParadise\CoreAdmin\Domain\Contracts\EntityDefinition\EntityDefinitionContract;
use BlackParadise\CoreAdmin\Domain\Exceptions\CapabilityDeniedException;
use BlackParadise\CoreAdmin\Domain\Mutators\EntityMutatorInterface;
use BlackParadise\CoreAdmin\Domain\ValueObjects\Capability;
use BlackParadise\CoreAdmin\Domain\ValueObjects\EntityKey;

/** Simulates a core use case raising CapabilityDeniedException past the controller's own pre-check. */
final class CapabilityDenyingMutator implements EntityMutatorInterface
{
    public function create(EntityRecordContract $record): EntityRecordContract
    {
        throw new CapabilityDeniedException($record->definition()->name(), Capability::Create);
    }

    public function update(EntityKey $key, EntityRecordContract $record): EntityRecordContract
    {
        throw new CapabilityDeniedException($record->definition()->name(), Capability::Update);
    }

    public function delete(EntityKey $key, EntityDefinitionContract $entityDefinition): bool
    {
        throw new CapabilityDeniedException($entityDefinition->name(), Capability::Delete);
    }
}
