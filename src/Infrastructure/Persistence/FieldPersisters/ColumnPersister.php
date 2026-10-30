<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Infrastructure\Persistence\FieldPersisters;

use BlackParadise\CoreAdmin\Domain\Contracts\Fields\FieldContract;

/** Plain host column; also the fallback for unknown field types. */
final class ColumnPersister extends AbstractFieldPersister
{
    public function toAttributes(FieldContract $field, mixed $value, PersistContext $context): array
    {
        return [$field->name() => $value];
    }
}
