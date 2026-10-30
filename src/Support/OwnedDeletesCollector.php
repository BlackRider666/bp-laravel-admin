<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Support;

use BlackParadise\CoreAdmin\Domain\Contracts\Entity\EntityRecordContract;
use BlackParadise\CoreAdmin\Domain\Contracts\EntityDefinition\EntityDefinitionContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Persistence\OwnedDeletesCollectorContract;
use BlackParadise\CoreAdmin\Domain\Fields\Base\AbstractRelationField;
use BlackParadise\CoreAdmin\Domain\ValueObjects\EntityKey;
use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use BlackParadise\LaravelAdmin\Exceptions\ModelLessDefinitionException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

/**
 * Collect owned records that must cascade-delete together with the host.
 *
 *  - belongsTo / hasOne owned+embedded: resolved from the loaded host (as before).
 *  - hasMany / morphMany owned+embedded: children are loaded through the host relation.
 *    For a soft-deletable host a plain delete keeps them (A54); they are only collected
 *    for a force-delete ({@see collectForForceDelete()}) or a non-soft-deletable host (A55).
 */
final readonly class OwnedDeletesCollector implements OwnedDeletesCollectorContract
{
    public function __construct(
        private EntityDefinitionRegistry $registry,
    ) {}

    /**
     * @return array<int, array{def: EntityDefinitionContract, key: EntityKey}>
     */
    public function collect(EntityDefinitionContract $definition, EntityRecordContract $host): array
    {
        $deletes = [];
        $softHost = $this->isSoftDeletable($definition);

        foreach ($this->ownedEmbedded($definition) as $field) {
            if ($this->isCollection($field)) {
                if (!$softHost) {
                    array_push($deletes, ...$this->collectChildren($definition, $host, $field));
                }
                continue;
            }

            $embeddedId = $this->extractEmbeddedId($field, $host);
            if ($embeddedId === null) {
                continue;
            }

            $embeddedDef = $this->embeddedDefinition($field);
            $deletes[] = [
                'def' => $embeddedDef,
                'key' => new EntityKey((string) $embeddedId, $embeddedDef->keyType()),
            ];
        }

        return $deletes;
    }

    /**
     * Owned hasMany/morphMany children of a host being PERMANENTLY removed.
     *
     * @return array<int, array{def: EntityDefinitionContract, key: EntityKey}>
     */
    public function collectForForceDelete(EntityDefinitionContract $definition, EntityRecordContract $host): array
    {
        $deletes = [];
        foreach ($this->ownedEmbedded($definition) as $field) {
            if ($this->isCollection($field)) {
                array_push($deletes, ...$this->collectChildren($definition, $host, $field));
            }
        }

        return $deletes;
    }

    public function isSoftDeletable(EntityDefinitionContract $definition): bool
    {
        $modelClass = $definition->modelClass();

        // Model-less definitions have no Eloquent model, hence no soft deletes.
        return $modelClass !== null
            && in_array(SoftDeletes::class, class_uses_recursive($modelClass), true);
    }

    /**
     * @return list<AbstractRelationField>
     */
    private function ownedEmbedded(EntityDefinitionContract $definition): array
    {
        $fields = [];
        foreach ($definition->fields() as $field) {
            if ($field instanceof AbstractRelationField && $field->isOwned() && $field->isEmbedded()) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    private function isCollection(AbstractRelationField $field): bool
    {
        return in_array($field->relationKind(), ['hasMany', 'morphMany'], true);
    }

    private function embeddedDefinition(AbstractRelationField $field): EntityDefinitionContract
    {
        $class = $field->embeddedDefinition() ?? throw new LogicException(
            sprintf('Embedded relation "%s" declares no embedded definition.', $field->name()),
        );

        return $this->registry->forClass($class);
    }

    /**
     * @return list<array{def: EntityDefinitionContract, key: EntityKey}>
     */
    private function collectChildren(
        EntityDefinitionContract $definition,
        EntityRecordContract $host,
        AbstractRelationField $field,
    ): array {
        // Stub host with the known PK: the relation query needs nothing else
        // (and works for trashed hosts too).
        $modelClass = $definition->modelClass()
            ?? throw ModelLessDefinitionException::forDefinition($definition);
        /** @var Model $hostModel */
        $hostModel = resolve($modelClass)
            ->newInstance()
            ->forceFill([$definition->keyField() => $host->id()]);
        $hostModel->exists = true;

        $childDef = $this->embeddedDefinition($field);
        $children = [];

        $related = $hostModel->getRelationValue($field->relationName());
        if (!$related instanceof Collection) {
            return [];
        }

        foreach ($related as $child) {
            $childKey = $child->getKey();
            $children[] = [
                'def' => $childDef,
                'key' => new EntityKey(is_int($childKey) ? $childKey : (string) $childKey, $childDef->keyType()),
            ];
        }

        return $children;
    }

    private function extractEmbeddedId(AbstractRelationField $field, EntityRecordContract $host): mixed
    {
        if ($field->relationKind() === 'belongsTo') {
            return $host->get($field->name());
        }

        if ($field->relationKind() === 'hasOne') {
            $rel = $host->relation($field->relationName());
            return is_array($rel) ? ($rel['id'] ?? null) : null;
        }

        return null;
    }
}
