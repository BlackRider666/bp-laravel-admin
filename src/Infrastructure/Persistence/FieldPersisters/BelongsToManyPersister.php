<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Infrastructure\Persistence\FieldPersisters;

use BlackParadise\CoreAdmin\Domain\Contracts\Fields\FieldContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Fields\RelationFieldContract;
use BlackParadise\CoreAdmin\Domain\Fields\BelongsToManyField;
use Illuminate\Database\Eloquent\Model;

/**
 * belongsToMany: pivot sync after the host save (id list or pre-shaped
 * `[id => pivot_attrs]`, optionConstraints scope-guard, static/dynamic pivot data).
 * Pivot rows are detached before the host row is physically removed (D6).
 */
final class BelongsToManyPersister extends AbstractFieldPersister
{
    public function afterSave(FieldContract $field, mixed $value, Model $host, PersistContext $context): void
    {
        if ($field instanceof RelationFieldContract) {
            $this->sync($host, $field, $value);
        }
    }

    public function beforeDelete(FieldContract $field, Model $host, PersistContext $context): void
    {
        if (!$field instanceof RelationFieldContract || !$context->cascades()) {
            return;
        }
        $host->{$field->relationName()}()->detach();
    }

    /**
     * @param mixed $value Масив ID (basic) або [id => pivot_attrs] (pre-shaped sync payload).
     */
    private function sync(
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
            // Merge static/dynamic pivot data into each pre-shaped record entry.
            if ($field instanceof BelongsToManyField) {
                $static  = $field->getPivotData();
                $dynamic = $field->getPivotPayloadCallback();
                if ($static !== [] || $dynamic !== null) {
                    $hostAttrs = $host->getAttributes();
                    $merged = [];
                    foreach ($value as $id => $pivotAttrs) {
                        $pivot = $static;
                        if ($dynamic !== null) {
                            $pivot = array_merge($pivot, $dynamic($id, $hostAttrs));
                        }
                        // Explicit per-record attrs win over field-level defaults.
                        $merged[$id] = array_merge($pivot, is_array($pivotAttrs) ? $pivotAttrs : []);
                    }
                    $value = $merged;
                }
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
            } elseif (is_float($v) && is_finite($v) && floor($v) === $v) {
                // Accept only whole-number floats (e.g. JSON-decoded integers like 1.0).
                // Non-integral floats like 1.9 are silently discarded to prevent
                // attaching a wrong record by flooring the value.
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
}
