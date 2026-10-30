<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Infrastructure\Persistence\FieldPersisters;

use BlackParadise\CoreAdmin\Domain\Contracts\EntityDefinition\EntityDefinitionContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Fields\FieldContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Fields\RelationFieldContract;
use BlackParadise\CoreAdmin\Domain\Fields\BelongsToManyField;
use BlackParadise\CoreAdmin\Domain\Fields\HasManyField;
use BlackParadise\CoreAdmin\Domain\Fields\MorphManyField;
use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared hasMany / morphMany child sync engine.
 *
 *  - 'replace' (default): per-model delete of every existing child (events +
 *      SoftDeletes fire), then create every incoming child.
 *  - 'merge': update children whose `id` matches, create the rest, delete
 *      existing children absent from the incoming set.
 *  - 'append': create only; never touch existing children.
 */
abstract class AbstractChildSyncPersister extends AbstractFieldPersister
{
    /**
     * @param Closure():FieldPersisterRegistry $persisters lazy: the registry holds this persister, so resolving it eagerly would loop
     */
    public function __construct(
        private readonly EntityDefinitionRegistry $definitions,
        private readonly Closure $persisters,
    ) {}

    public function afterSave(FieldContract $field, mixed $value, Model $host, PersistContext $context): void
    {
        if ($field instanceof RelationFieldContract) {
            $this->applyChildSync($host, $field, $value, $context);
        }
    }

    private function strategyOf(RelationFieldContract $field): string
    {
        return $field instanceof HasManyField || $field instanceof MorphManyField
            ? $field->getStrategy()
            : 'replace';
    }

    /**
     * @param array<string, mixed> $attrs
     * @return array<string, mixed>
     */
    private function normalizeChildAttrs(array $attrs): array
    {
        return array_map(
            static fn(mixed $v): mixed => (is_string($v) && $v === '') ? null : $v,
            $attrs,
        );
    }

    private function applyChildSync(
        Model $host,
        RelationFieldContract $field,
        mixed $value,
        PersistContext $context,
    ): void {
        if (!is_array($value)) {
            return;
        }

        [$childDefinition, $pivotFields] = $this->childBelongsToManyFields($field);

        $relationName = $field->relationName();
        $relation     = $host->{$relationName}();
        $strategy     = $this->strategyOf($field);

        if ($strategy === 'append') {
            foreach ($value as $childAttrs) {
                if (!is_array($childAttrs)) {
                    continue;
                }
                $this->createChild($relation, $this->normalizeChildAttrs($childAttrs), $pivotFields, $childDefinition, $context);
            }
            return;
        }

        if ($strategy === 'merge') {
            $existing = $relation->get()->keyBy(fn($m) => $m->getKey());
            $incomingIds = [];

            foreach ($value as $childAttrs) {
                if (!is_array($childAttrs)) {
                    continue;
                }
                $childAttrs = $this->normalizeChildAttrs($childAttrs);
                $id = $childAttrs['id'] ?? null;
                if ($id !== null && $existing->has($id)) {
                    [$childAttrs, $pivots] = $this->splitPivotAttrs($childAttrs, $pivotFields);
                    $existing[$id]->update($childAttrs);
                    $this->syncChildPivots($existing[$id], $pivots, $pivotFields, $childDefinition, $context);
                    $incomingIds[] = $id;
                } else {
                    // Strip a possibly bogus id; let the DB assign it.
                    unset($childAttrs['id']);
                    $this->createChild($relation, $childAttrs, $pivotFields, $childDefinition, $context);
                }
            }

            // Loose comparison: incoming ids may arrive as strings from JSON
            // while the model returns int/string depending on column type.
            $normalizedIncoming = array_map(
                static fn(mixed $v): string => (string) $v,
                $incomingIds,
            );
            foreach ($existing as $key => $model) {
                if (!in_array((string) $key, $normalizedIncoming, true)) {
                    $model->delete();
                }
            }
            return;
        }

        // Default: 'replace' — per-model delete so events + SoftDeletes fire.
        $relation->get()->each->delete();

        foreach ($value as $childAttrs) {
            if (!is_array($childAttrs)) {
                continue;
            }
            $this->createChild($relation, $this->normalizeChildAttrs($childAttrs), $pivotFields, $childDefinition, $context);
        }
    }

    /**
     * belongsToMany fields of the embedded child definition, keyed by field name.
     *
     * @return array{0: EntityDefinitionContract|null, 1: array<string, BelongsToManyField>}
     */
    private function childBelongsToManyFields(RelationFieldContract $field): array
    {
        $class = $field->embeddedDefinition();
        if ($class === null) {
            return [null, []];
        }

        $definition = $this->definitions->forClass($class);
        $result     = [];
        foreach ($definition->fields() as $childField) {
            if ($childField instanceof BelongsToManyField) {
                $result[$childField->name()] = $childField;
            }
        }

        return [$definition, $result];
    }

    /**
     * Pivot values are not columns: pull them out before the child row is written.
     *
     * @param array<string, mixed> $attrs
     * @param array<string, BelongsToManyField> $pivotFields
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function splitPivotAttrs(array $attrs, array $pivotFields): array
    {
        $pivots = [];
        foreach (array_keys($pivotFields) as $name) {
            if (array_key_exists($name, $attrs)) {
                $pivots[$name] = $attrs[$name];
                unset($attrs[$name]);
            }
        }

        return [$attrs, $pivots];
    }

    /**
     * @param array<string, mixed> $attrs
     * @param array<string, BelongsToManyField> $pivotFields
     */
    private function createChild(mixed $relation, array $attrs, array $pivotFields, ?EntityDefinitionContract $childDefinition, PersistContext $context): void
    {
        [$attrs, $pivots] = $this->splitPivotAttrs($attrs, $pivotFields);
        $child = $relation->create($attrs);
        $this->syncChildPivots($child, $pivots, $pivotFields, $childDefinition, $context);
    }

    /**
     * Pivot sync runs after the child row exists (it needs the child key).
     *
     * @param array<string, mixed> $pivots
     * @param array<string, BelongsToManyField> $pivotFields
     */
    private function syncChildPivots(
        Model $child,
        array $pivots,
        array $pivotFields,
        ?EntityDefinitionContract $childDefinition,
        PersistContext $context,
    ): void {
        if ($pivots === [] || !$childDefinition instanceof EntityDefinitionContract) {
            return;
        }

        $registry     = ($this->persisters)();
        $childContext = new PersistContext($childDefinition, $pivots, $context->files, $child);
        foreach ($pivots as $name => $value) {
            $registry->for($pivotFields[$name])->afterSave($pivotFields[$name], $value, $child, $childContext);
        }
    }
}
