<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Infrastructure\Persistence\FieldPersisters;

use BlackParadise\CoreAdmin\Domain\Contracts\Fields\FieldContract;
use BlackParadise\CoreAdmin\Domain\Fields\MorphToField;
use Illuminate\Database\Eloquent\Model;

/**
 * morphTo persists two host columns ({name}_type / {name}_id) rather than the
 * field name itself; the type is normalised to the model's morph-class (alias
 * when an enforced morph map exists).
 */
final class MorphToPersister extends AbstractFieldPersister
{
    public function handles(FieldContract $field, array $attributes): bool
    {
        return $field instanceof MorphToField
            && array_key_exists($field->typeColumn(), $attributes)
            && array_key_exists($field->idColumn(), $attributes);
    }

    public function toAttributes(FieldContract $field, mixed $value, PersistContext $context): array
    {
        if (!$field instanceof MorphToField) {
            return [];
        }
        $typeCol = $field->typeColumn();
        $idCol   = $field->idColumn();
        $rawType = (string) ($context->attributes[$typeCol] ?? '');

        if (class_exists($rawType) && is_subclass_of($rawType, Model::class)) {
            $rawType = (new $rawType())->getMorphClass();
        }

        return [$typeCol => $rawType, $idCol => $context->attributes[$idCol]];
    }
}
