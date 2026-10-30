<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Infrastructure\Persistence;

use BlackParadise\CoreAdmin\Domain\Contracts\Entity\EntityRecordContract;
use BlackParadise\CoreAdmin\Domain\Contracts\EntityDefinition\EntityDefinitionContract;
use BlackParadise\CoreAdmin\Domain\Query\Criteria;
use BlackParadise\CoreAdmin\Domain\Query\PaginatedResult;
use BlackParadise\CoreAdmin\Domain\Repositories\EntityRepositoryInterface;
use BlackParadise\CoreAdmin\Domain\ValueObjects\EntityKey;
use BlackParadise\LaravelAdmin\Exceptions\ModelLessDefinitionException;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\Query\CriteriaApplier;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\Query\EagerLoadPlanner;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\Query\EntityRecordHydrator;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\Query\FieldQueryRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent implementation of {@see EntityRepositoryInterface}: a thin orchestrator.
 *
 * Criteria translation lives in {@see CriteriaApplier} (per-field-type SQL in
 * {@see FieldQueryRegistry} strategies), eager-load planning in {@see EagerLoadPlanner} and
 * record wrapping in {@see EntityRecordHydrator}.
 */
final readonly class EloquentEntityRepository implements EntityRepositoryInterface
{
    public function __construct(
        private CriteriaApplier $criteria,
        private EagerLoadPlanner $eagerLoad,
        private EntityRecordHydrator $hydrator,
        private FieldQueryRegistry $queries,
    ) {}

    public function list(EntityDefinitionContract $entityDefinition, Criteria $criteria): PaginatedResult
    {
        $query = $this->newQuery($entityDefinition);

        $this->criteria->apply($query, $entityDefinition, $criteria);

        $paginator = $query->paginate(
            max(1, $criteria->perPage),
            $this->listColumns($entityDefinition),
            'page',
            max(1, $criteria->page),
        );

        /** @var array<Model> $models */
        $models = $paginator->items();

        return new PaginatedResult(
            items: array_map(fn(Model $m): EntityRecordContract => $this->hydrator->hydrate($entityDefinition, $m), $models),
            total: $paginator->total(),
            page: $paginator->currentPage(),
            perPage: $paginator->perPage(),
        );
    }

    public function find(EntityDefinitionContract $entityDefinition, EntityKey $key): ?EntityRecordContract
    {
        /** @var Model|null $model */
        $model = $this->newQuery($entityDefinition)->find($this->castKey($key));

        return $model === null ? null : $this->hydrator->hydrate($entityDefinition, $model);
    }

    public function exists(EntityDefinitionContract $entityDefinition, EntityKey $key): bool
    {
        /** @var Model $instance */
        $instance = resolve($entityDefinition->modelClass() ?? throw ModelLessDefinitionException::forDefinition($entityDefinition));

        return $instance->newQuery()
            ->where($entityDefinition->keyField(), $this->castKey($key))
            ->exists();
    }

    /**
     * @return Builder<Model>
     */
    private function newQuery(EntityDefinitionContract $definition): Builder
    {
        /** @var Model $instance */
        $instance = resolve($definition->modelClass() ?? throw ModelLessDefinitionException::forDefinition($definition));
        $query    = $instance->newQuery();

        $relations = $this->eagerLoad->plan($definition);
        if ($relations !== []) {
            $query->with($relations);
        }

        return $query;
    }

    /**
     * Column list for the list SELECT: primary key + every column-backed field. Falls back to
     * ['*'] as soon as a field strategy cannot derive its backing columns.
     *
     * @return array<string>
     */
    private function listColumns(EntityDefinitionContract $definition): array
    {
        $columns = [$definition->keyField()];

        foreach ($definition->fields() as $field) {
            $projection = $this->queries->for($field)->projection($field);
            if ($projection === null) {
                return ['*'];
            }
            array_push($columns, ...$projection);
        }

        return array_values(array_unique($columns));
    }

    private function castKey(EntityKey $key): int|string
    {
        return $key->type === 'int' ? (int) $key->value : (string) $key->value;
    }
}
