<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Infrastructure\Persistence;

use BlackParadise\CoreAdmin\Domain\Contracts\EntityDefinition\EntityDefinitionContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Fields\RelationFieldContract;
use BlackParadise\CoreAdmin\Domain\Fields\BelongsToManyField;
use Illuminate\Database\Eloquent\Model;

/**
 * Applies side-effect relation writes for {@see EloquentEntityMutator}:
 *  - belongsToMany sync (basic + pivot payloads),
 *  - hasOne upsert,
 *  - hasMany / morphMany full-replace sync.
 *
 * Also extracts the relation payload subset of incoming attributes so the
 * mutator can keep its column-side filter pure, and detaches pivots before
 * host delete to avoid orphan rows.
 *
 * Stateless — safe to bind as a singleton.
 */
final class RelationWriter
{
    /**
     * Relation types that require post-host side-effects (apply after create/update).
     * belongsTo/morphTo лишаються колонковими (FK на host).
     */
    private const SIDE_EFFECT_RELATION_TYPES = [
        'belongs_to_many',
        'has_many',
        'has_one',
        'morph_many',
    ];

    /**
     * Витягнути payload для relation fields, що вимагають side-effect
     * (apply після host create/update).
     *
     * @param array<string, mixed> $attributes
     * @return array<string, mixed> Map field-name => payload
     */
    public function extractRelationPayload(
        EntityDefinitionContract $definition,
        array $attributes,
    ): array {
        $payload = [];
        foreach ($definition->fields() as $field) {
            if (!in_array($field->type(), self::SIDE_EFFECT_RELATION_TYPES, true)) {
                continue;
            }
            if (array_key_exists($field->name(), $attributes)) {
                $payload[$field->name()] = $attributes[$field->name()];
            }
        }
        return $payload;
    }

    /**
     * Застосовує side-effect relations (belongsToMany/hasMany/hasOne/morphMany)
     * до host моделі. Викликається після host create або update в транзакції.
     *
     * @param array<string, mixed> $relationPayload Map field-name => payload
     */
    public function applyAll(
        Model $host,
        EntityDefinitionContract $definition,
        array $relationPayload,
    ): void {
        if ($relationPayload === []) {
            return;
        }

        foreach ($definition->fields() as $field) {
            if (!$field instanceof RelationFieldContract) {
                continue;
            }
            $name = $field->name();
            if (!array_key_exists($name, $relationPayload)) {
                continue;
            }
            $value = $relationPayload[$name];

            match ($field->relationKind()) {
                'belongsToMany' => $this->applyBelongsToMany($host, $field, $value),
                'hasOne'        => $this->applyHasOne($host, $field, $value),
                'hasMany'       => $this->applyHasMany($host, $field, $value),
                'morphMany'     => $this->applyMorphMany($host, $field, $value),
                default         => null, // belongsTo / morphTo — колонкові, вже записані
            };
        }
    }

    /**
     * Detach pivot rows for all belongsToMany relation fields before host delete.
     * Without this, pivot tables accumulate orphan rows.
     */
    public function detachBelongsToManyPivots(
        Model $host,
        EntityDefinitionContract $definition,
    ): void {
        foreach ($definition->fields() as $field) {
            if (!$field instanceof RelationFieldContract) {
                continue;
            }
            if ($field->relationKind() !== 'belongsToMany') {
                continue;
            }
            $host->{$field->relationName()}()->detach();
        }
    }

    /**
     * @param mixed $value Масив ID (basic) або [id => pivot_attrs] (pre-shaped sync payload).
     */
    private function applyBelongsToMany(
        Model $host,
        RelationFieldContract $field,
        mixed $value,
    ): void {
        if (!is_array($value)) {
            return;
        }

        $relationName = $field->relationName();

        if ($this->isPreShapedSyncPayload($value)) {
            // Apply scope-guard to the keys of the pre-shaped payload when the field
            // declares optionConstraints(). The payload arrives from HTTP form data and
            // must not bypass the allowed-option set even when shaped as [id => pivot_attrs].
            $constraints = $field->optionConstraints();
            if ($constraints !== []) {
                $allowedIds = $this->filterConstrainedIds($field, array_keys($value));
                $allowedNormalized = array_map(static fn(mixed $v): string => (string) $v, $allowedIds);
                $value = array_filter(
                    $value,
                    static fn(int|string $k): bool => in_array((string) $k, $allowedNormalized, true),
                    ARRAY_FILTER_USE_KEY,
                );
            }
            $host->{$relationName}()->sync($value);
            return;
        }

        // Strictly coerce to ids: accept numeric scalars and non-empty strings.
        // Booleans / null are rejected — they would otherwise pass is_scalar().
        // Floats with no fractional part (e.g. JSON-decoded integers) are cast to int.
        $ids = [];
        foreach ($value as $v) {
            if (is_int($v)) {
                $ids[] = $v;
            } elseif (is_string($v) && $v !== '') {
                $ids[] = $v;
            } elseif (is_float($v) && !is_nan($v) && !is_infinite($v)) {
                // Cast whole-number floats to int so type stays array<int, int|string>.
                $ids[] = (int) $v;
            }
            // bool, null, arrays, objects intentionally skipped.
        }

        // Filter out IDs that violate optionConstraints (scope guard).
        $ids = $this->filterConstrainedIds($field, $ids);

        $syncPayload = $this->buildBelongsToManySyncPayload($field, $ids, $host);

        $host->{$relationName}()->sync($syncPayload);
    }

    /**
     * Scope-guard: remove IDs that do not belong to the constrained option set.
     *
     * When the field declares optionConstraints() (e.g. `->whereOption('guard_name', 'web')`)
     * we resolve the target model via the relation, query the allowed IDs, and
     * discard any incoming ID that is not in the allowed set.
     *
     * No-op when there are no constraints (avoids an unnecessary DB query).
     *
     * @param array<int, int|string> $ids
     * @return array<int, int|string>
     */
    private function filterConstrainedIds(RelationFieldContract $field, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $constraints = $field->optionConstraints();
        if ($constraints === []) {
            return $ids;
        }

        $targetClass = $field->target();
        if ($targetClass === '' || !class_exists($targetClass) || !is_subclass_of($targetClass, Model::class)) {
            return $ids;
        }

        $targetModel = new $targetClass();
        $keyName     = $targetModel->getKeyName();

        $query = $targetModel::query()->whereIn($keyName, $ids);
        foreach ($constraints as $constraint) {
            $query->where($constraint['column'], $constraint['value']);
        }

        $allowedIds = $query->pluck($keyName)->all();

        // Normalize to string comparison so int/string key types don't cause mismatches.
        $allowedNormalized = array_map(static fn(mixed $v): string => (string) $v, $allowedIds);

        return array_values(
            array_filter(
                $ids,
                static fn(int|string $id): bool => in_array((string) $id, $allowedNormalized, true),
            ),
        );
    }

    /**
     * Detect Laravel sync() shape: associative array mapped to array values
     * (id => pivot_attrs). Empty arrays are NOT pre-shaped (treated as id lists).
     *
     * @param array<mixed> $value
     */
    private function isPreShapedSyncPayload(array $value): bool
    {
        if ($value === []) {
            return false;
        }
        foreach ($value as $v) {
            if (!is_array($v)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Build Laravel sync() payload: ids alone or [id => pivot_attrs].
     *
     * @param array<int, int|string> $ids
     * @return array<int|string, array<string, mixed>>|array<int, int|string>
     */
    private function buildBelongsToManySyncPayload(
        RelationFieldContract $field,
        array $ids,
        Model $host,
    ): array {
        if (!$field instanceof BelongsToManyField) {
            return $ids;
        }

        $staticPivot    = $field->getPivotData();
        $dynamicPivotFn = $field->getPivotPayloadCallback();

        if ($staticPivot === [] && $dynamicPivotFn === null) {
            return $ids;
        }

        $hostAttrs = $host->getAttributes();
        $result    = [];
        foreach ($ids as $id) {
            $pivot = $staticPivot;
            if ($dynamicPivotFn !== null) {
                $pivot = array_merge($pivot, $dynamicPivotFn($id, $hostAttrs));
            }
            $result[$id] = $pivot;
        }
        return $result;
    }

    private function applyHasOne(
        Model $host,
        RelationFieldContract $field,
        mixed $value,
    ): void {
        if ($value === null || $value === '') {
            return;
        }
        if (!is_array($value)) {
            return;
        }

        $relationName = $field->relationName();
        $relation     = $host->{$relationName}();
        $existing     = $relation->first();

        if ($existing !== null) {
            $existing->update($value);
        } else {
            $relation->create($value);
        }
    }

    /**
     * Apply a hasMany write using the strategy declared on the field.
     *
     * See {@see applyChildSync()} for strategy semantics.
     *
     * @param mixed $value Array of child attribute arrays; non-arrays are skipped.
     */
    private function applyHasMany(
        Model $host,
        RelationFieldContract $field,
        mixed $value,
    ): void {
        $this->applyChildSync($host, $field, $value);
    }

    /**
     * Apply a morphMany write using the strategy declared on the field.
     *
     * See {@see applyChildSync()} for strategy semantics.
     *
     * @param mixed $value Array of child attribute arrays; non-arrays are skipped.
     */
    private function applyMorphMany(
        Model $host,
        RelationFieldContract $field,
        mixed $value,
    ): void {
        $this->applyChildSync($host, $field, $value);
    }

    /**
     * Shared has_many / morph_many sync engine.
     *
     * Strategy is read from the field via getStrategy() if the method exists;
     * otherwise defaults to 'replace' (back-compat).
     *
     *  - 'replace': delete every existing child via per-model delete (fires
     *      events, respects SoftDeletes), then create every incoming as new.
     *  - 'merge': update children with a matching `id`, create children with
     *      no `id`, delete existing children whose id is absent from incoming.
     *  - 'append': create only — never delete or touch existing children.
     *
     * @param mixed $value Array of child attribute arrays; non-arrays are skipped.
     */
    private function applyChildSync(
        Model $host,
        RelationFieldContract $field,
        mixed $value,
    ): void {
        if (!is_array($value)) {
            return;
        }

        $relationName = $field->relationName();
        $relation     = $host->{$relationName}();
        $strategy     = $this->resolveStrategy($field);

        if ($strategy === 'append') {
            foreach ($value as $childAttrs) {
                if (!is_array($childAttrs)) {
                    continue;
                }
                $relation->create($childAttrs);
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
                $id = $childAttrs['id'] ?? null;
                if ($id !== null && $existing->has($id)) {
                    $existing[$id]->update($childAttrs);
                    $incomingIds[] = $id;
                } else {
                    // Strip a possibly bogus id; let the DB assign it.
                    unset($childAttrs['id']);
                    $relation->create($childAttrs);
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
            $relation->create($childAttrs);
        }
    }

    /**
     * Pull a write strategy from the field; fall back to 'replace' when the
     * field type does not expose getStrategy() (back-compat for any future
     * relation field without it).
     */
    private function resolveStrategy(RelationFieldContract $field): string
    {
        if (method_exists($field, 'getStrategy')) {
            $strategy = $field->getStrategy();
            if (is_string($strategy) && in_array($strategy, ['replace', 'merge', 'append'], true)) {
                return $strategy;
            }
        }
        return 'replace';
    }
}
