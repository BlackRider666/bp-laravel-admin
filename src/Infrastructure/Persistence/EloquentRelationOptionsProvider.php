<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Infrastructure\Persistence;

use BlackParadise\CoreAdmin\Domain\Contracts\Entity\RelationOptionsProviderContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Fields\RelationFieldContract;
use BlackParadise\CoreAdmin\Domain\Fields\MorphToField;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent-backed {@see RelationOptionsProviderContract}.
 *
 * Selects the target model's primary key + display field, ordered by the
 * display field, capped at $limit. The primary key is resolved via
 * {@see Model::getKeyName()} so non-`id` keys (UUID, slug, composite) work
 * without configuration.
 *
 * Caches results per instance; bound as request-scoped.
 */
final class EloquentRelationOptionsProvider implements RelationOptionsProviderContract
{
    /** @var array<string, list<array{id: int|string, label: string}>> */
    private array $optionsCache = [];

    /** @var array<string, list<array{value: string, label: string, options: list<array{id: int|string, label: string}>}>> */
    private array $morphCache = [];

    public function options(RelationFieldContract $field, int $limit = 1000): array
    {
        $targetClass = $field->target();
        if ($targetClass === '' || !class_exists($targetClass)) {
            return [];
        }
        if (!is_subclass_of($targetClass, Model::class)) {
            return [];
        }

        $model          = new $targetClass();
        $keyName        = $model->getKeyName();
        $displayField   = $field->displayField();
        $effectiveLimit = max(1, $limit);
        $constraints    = $field->optionConstraints();
        $hasCallback    = $field->hasDisplayCallback();

        $cacheKey = $targetClass . '|' . $displayField . '|' . $effectiveLimit . '|'
            . serialize($constraints)
            . '|' . ($hasCallback ? 'cb:' . spl_object_hash($field) : 'col');
        if (isset($this->optionsCache[$cacheKey])) {
            return $this->optionsCache[$cacheKey];
        }

        if ($hasCallback) {
            $query = $model::query();
            $eager = $field->displayEagerLoad();
            if ($eager !== []) {
                $query->with($eager);
            }
            $orderColumn = $field->displayOrderColumn();
            if ($orderColumn !== null) {
                $query->orderBy($orderColumn);
            }
            $query->limit($effectiveLimit);
            foreach ($constraints as $constraint) {
                $query->where($constraint['column'], $constraint['value']);
            }

            $options = [];
            foreach ($query->get() as $row) {
                $options[] = [
                    'id'    => $row->{$keyName},
                    'label' => $field->resolveDisplayLabel($row->toArray(), $displayField),
                ];
            }
            if ($orderColumn === null) {
                usort($options, static fn(array $a, array $b): int => strcmp($a['label'], $b['label']));
            }

            return $this->optionsCache[$cacheKey] = $options;
        }

        // Deduplicate columns when displayField === keyName to avoid a
        // "duplicate column" error on databases that disallow it.
        $columns = $displayField === $keyName
            ? [$keyName]
            : [$keyName, $displayField];

        $query = $model::query()
            ->select($columns)
            ->orderBy($displayField)
            ->limit($effectiveLimit);

        foreach ($constraints as $constraint) {
            $query->where($constraint['column'], $constraint['value']);
        }

        $rows = $query->get();

        $options = [];
        foreach ($rows as $row) {
            $options[] = [
                'id'    => $row->{$keyName},
                'label' => (string) $row->{$displayField},
            ];
        }

        return $this->optionsCache[$cacheKey] = $options;
    }

    /**
     * Per-type option lists for a morphTo field's allowed targets.
     *
     * Each entry carries the morph-map-aware type value ({@see Model::getMorphClass()}
     * returns the registered alias when an enforced morph map exists, otherwise the
     * FQCN), the human label, and that type's records as {id,label}.
     *
     * @return list<array{value: string, label: string, options: list<array{id: int|string, label: string}>}>
     */
    public function morphOptions(RelationFieldContract $field): array
    {
        if (!$field instanceof MorphToField) {
            return [];
        }

        $cacheKey = serialize($field->morphTypeMap());

        return $this->morphCache[$cacheKey] ??= $this->buildMorphOptions($field);
    }

    /**
     * Build the per-type options list for a MorphToField without caching.
     *
     * @return list<array{value: string, label: string, options: list<array{id: int|string, label: string}>}>
     */
    private function buildMorphOptions(MorphToField $field): array
    {
        $out = [];
        foreach ($field->morphTypeMap() as $class => $config) {
            if (!class_exists($class)) {
                continue;
            }
            if (!is_subclass_of($class, Model::class)) {
                continue;
            }
            $model   = new $class();
            $keyName = $model->getKeyName();
            $display = $config['display'];

            $columns = $display === $keyName ? [$keyName] : [$keyName, $display];
            $rows    = $class::query()->select($columns)->orderBy($display)->limit(1000)->get();

            $options = [];
            foreach ($rows as $row) {
                $options[] = ['id' => $row->{$keyName}, 'label' => (string) $row->{$display}];
            }

            $out[] = [
                'value'   => $model->getMorphClass(),
                'label'   => $config['label'],
                'options' => $options,
            ];
        }

        return $out;
    }
}
