<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Infrastructure\Persistence\FieldPersisters;

use BlackParadise\CoreAdmin\Domain\Contracts\Fields\FieldContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Fields\RelationFieldContract;
use Illuminate\Database\Eloquent\Model;

/** hasOne upsert after the host save; an explicit empty payload deletes the child. */
final class HasOnePersister extends AbstractFieldPersister
{
    public function afterSave(FieldContract $field, mixed $value, Model $host, PersistContext $context): void
    {
        if (!$field instanceof RelationFieldContract) {
            return;
        }
        $relation = $host->{$field->relationName()}();

        if ($value === null || $value === '' || (is_array($value) && $value === [])) {
            $existing = $relation->first();
            if ($existing !== null) {
                $existing->delete();
            }

            return;
        }
        if (!is_array($value)) {
            return;
        }

        $existing = $relation->first();
        if ($existing !== null) {
            $existing->update($value);
        } else {
            $relation->create($value);
        }
    }
}
