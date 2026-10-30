<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Infrastructure\Persistence\Query;

use BlackParadise\CoreAdmin\Domain\Contracts\EntityDefinition\EntityDefinitionContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Fields\RelationFieldContract;
use BlackParadise\CoreAdmin\Domain\Entity\EntityRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Wraps Eloquent models into {@see EntityRecord} domain objects.
 */
final class EntityRecordHydrator
{
    /**
     * Relation names whose serialization must include their own sub-relations
     * (one level): embedded relations + relations with displayEagerLoad.
     *
     * @return array<string, true>
     */
    private function deepSerializeRelationNames(EntityDefinitionContract $definition): array
    {
        $names = [];
        foreach ($definition->fields() as $field) {
            if (!$field instanceof RelationFieldContract) {
                continue;
            }
            if ($field->isEmbedded() || $field->displayEagerLoad() !== []) {
                $names[$field->relationName()] = true;
            }
        }
        return $names;
    }

    /**
     * Wrap an Eloquent model in an {@see EntityRecord} domain object.
     *
     * Eloquent relation objects are serialized to plain arrays so that
     * domain records never carry Eloquent model references.
     *
     * For deep relations (embedded or with displayEagerLoad), sub-relations are
     * included in the serialized output under their relation name keys.
     * For all other relations the original flat serialization is preserved.
     */
    public function hydrate(EntityDefinitionContract $definition, Model $model): EntityRecord
    {
        $deepKeys = $this->deepSerializeRelationNames($definition);

        $serializedRelations = collect($model->getRelations())
            ->map(function (mixed $rel, string $key) use ($deepKeys): mixed {
                if ($rel === null) {
                    return null;
                }
                $deep = isset($deepKeys[$key]);
                if ($rel instanceof Model) {
                    return $deep ? $this->serializeModelDeep($rel) : $rel->attributesToArray();
                }
                if ($deep && is_iterable($rel)) {
                    $out = [];
                    foreach ($rel as $item) {
                        $out[] = $item instanceof Model ? $this->serializeModelDeep($item) : $item;
                    }
                    return $out;
                }
                return method_exists($rel, 'toArray') ? $rel->toArray() : [];
            })
            ->all();

        return new EntityRecord(
            definition: $definition,
            attributes: $model->attributesToArray(),
            relations: $serializedRelations,
        );
    }

    /**
     * Serialize a model's attributes plus its loaded relations (one level) as a
     * plain array, where sub-relations are nested under their relation name keys.
     *
     * @return array<string, mixed>
     */
    private function serializeModelDeep(Model $model): array
    {
        $data = $model->attributesToArray();
        foreach ($model->getRelations() as $name => $rel) {
            if ($rel === null) {
                $data[$name] = null;
                continue;
            }
            if ($rel instanceof Model) {
                $data[$name] = $rel->attributesToArray();
                continue;
            }
            if (is_iterable($rel)) {
                $items = [];
                foreach ($rel as $item) {
                    $items[] = $item instanceof Model ? $item->attributesToArray() : $item;
                }
                $data[$name] = $items;
                continue;
            }
            $data[$name] = method_exists($rel, 'toArray') ? $rel->toArray() : null;
        }
        return $data;
    }
}
