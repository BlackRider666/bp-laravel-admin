<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\ModelLess;

use BlackParadise\CoreAdmin\Domain\Contracts\Entity\EntityRecordContract;
use BlackParadise\CoreAdmin\Domain\Contracts\EntityDefinition\EntityDefinitionContract;
use BlackParadise\CoreAdmin\Domain\Entity\EntityRecord;
use BlackParadise\CoreAdmin\Domain\Query\Criteria;
use BlackParadise\CoreAdmin\Domain\Query\PaginatedResult;
use BlackParadise\CoreAdmin\Domain\Repositories\EntityRepositoryInterface;
use BlackParadise\CoreAdmin\Domain\ValueObjects\EntityKey;

/** Custom (non-Eloquent) read source over {@see ArrayStore}. No constructor deps: resolvable by the container. */
final class ArrayRepository implements EntityRepositoryInterface
{
    public function list(EntityDefinitionContract $entityDefinition, Criteria $criteria): PaginatedResult
    {
        $items = array_values(array_map(
            static fn(array $row): EntityRecordContract => new EntityRecord($entityDefinition, $row),
            ArrayStore::$rows,
        ));

        return new PaginatedResult($items, count($items), 1, max(1, $criteria->perPage));
    }

    public function find(EntityDefinitionContract $entityDefinition, EntityKey $key): ?EntityRecordContract
    {
        $row = ArrayStore::$rows[(int) $key->value] ?? null;

        return $row === null ? null : new EntityRecord($entityDefinition, $row);
    }

    public function exists(EntityDefinitionContract $entityDefinition, EntityKey $key): bool
    {
        return isset(ArrayStore::$rows[(int) $key->value]);
    }
}
