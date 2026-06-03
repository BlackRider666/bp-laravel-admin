<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Infrastructure\Persistence;

use BlackParadise\CoreAdmin\Domain\Contracts\Entity\RelationOptionsProviderContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Fields\RelationFieldContract;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent-backed {@see RelationOptionsProviderContract}.
 *
 * Selects the target model's primary key + display field, ordered by the
 * display field, capped at $limit. The primary key is resolved via
 * {@see Model::getKeyName()} so non-`id` keys (UUID, slug, composite) work
 * without configuration.
 *
 * Stateless — safe to bind as a singleton.
 */
final readonly class EloquentRelationOptionsProvider implements RelationOptionsProviderContract
{
    public function options(RelationFieldContract $field, int $limit = 1000): array
    {
        $targetClass = $field->target();
        if ($targetClass === '' || !class_exists($targetClass)) {
            return [];
        }
        if (!is_subclass_of($targetClass, Model::class)) {
            return [];
        }

        $model = new $targetClass();

        $keyName       = $model->getKeyName();
        $displayField  = $field->displayField();
        $effectiveLimit = max(1, $limit);

        // Deduplicate columns when displayField === keyName to avoid a
        // "duplicate column" error on databases that disallow it.
        $columns = $displayField === $keyName
            ? [$keyName]
            : [$keyName, $displayField];

        $query = $model::query()
            ->select($columns)
            ->orderBy($displayField)
            ->limit($effectiveLimit);

        $constraints = $field->optionConstraints();
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

        return $options;
    }
}
