<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ModelLess;

use BlackParadise\CoreAdmin\Domain\Contracts\Entity\EntityRecordContract;
use BlackParadise\CoreAdmin\Domain\Contracts\EntityDefinition\EntityDefinitionContract;
use BlackParadise\CoreAdmin\Domain\Entity\EntityRecord;
use BlackParadise\CoreAdmin\Domain\Exceptions\EntityNotFoundException;
use BlackParadise\CoreAdmin\Domain\Mutators\EntityMutatorInterface;
use BlackParadise\CoreAdmin\Domain\ValueObjects\EntityKey;

/** Custom (non-Eloquent) write sink over {@see ArrayStore}. No constructor deps: resolvable by the container. */
final class ArrayMutator implements EntityMutatorInterface
{
    public function create(EntityRecordContract $record): EntityRecordContract
    {
        $id = ArrayStore::nextId();
        $row = array_merge($record->toArray(), ['id' => $id]);
        ArrayStore::$rows[$id] = $row;

        return new EntityRecord($record->definition(), $row);
    }

    public function update(EntityKey $key, EntityRecordContract $record): EntityRecordContract
    {
        $id = (int) $key->value;
        if (!isset(ArrayStore::$rows[$id])) {
            throw new EntityNotFoundException($record->definition()->name(), $id);
        }
        $row = array_merge(ArrayStore::$rows[$id], $record->toArray(), ['id' => $id]);
        ArrayStore::$rows[$id] = $row;

        return new EntityRecord($record->definition(), $row);
    }

    public function delete(EntityKey $key, EntityDefinitionContract $entityDefinition): bool
    {
        $id = (int) $key->value;
        if (!isset(ArrayStore::$rows[$id])) {
            return false;
        }
        unset(ArrayStore::$rows[$id]);

        return true;
    }
}
