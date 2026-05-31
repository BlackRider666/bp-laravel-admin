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

        /** @var Model $model */
        $model = new $targetClass();

        $keyName       = $model->getKeyName();
        $displayField  = $field->displayField();
        $effectiveLimit = max(1, $limit);

        $rows = $model::query()
            ->select([$keyName, $displayField])
            ->orderBy($displayField)
            ->limit($effectiveLimit)
            ->get();

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
