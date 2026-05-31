<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Support;

use BlackParadise\CoreAdmin\Domain\Contracts\Entity\EntityRecordContract;
use BlackParadise\CoreAdmin\Domain\Contracts\EntityDefinition\EntityDefinitionContract;
use BlackParadise\CoreAdmin\Domain\Fields\Base\AbstractRelationField;
use BlackParadise\CoreAdmin\Domain\ValueObjects\EntityKey;
use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;

/**
 * Collect FK ids that must cascade-delete after the host record is removed.
 *
 * Iterates owned+embedded relation fields on the definition and resolves the
 * related record's id from the loaded host — for both belongsTo (FK column on
 * host) and hasOne (id on the related record).
 */
final readonly class OwnedDeletesCollector
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

        foreach ($definition->fields() as $field) {
            if (!$field instanceof AbstractRelationField) {
                continue;
            }
            if (!$field->isOwned()) {
                continue;
            }
            if (!$field->isEmbedded()) {
                continue;
            }

            $embeddedId = $this->extractEmbeddedId($field, $host);
            if ($embeddedId === null) {
                continue;
            }

            $embeddedDefClass = $field->embeddedDefinition();
            $embeddedDef = $this->registry->get((new $embeddedDefClass())->resolveName());

            $deletes[] = [
                'def' => $embeddedDef,
                'key' => new EntityKey((string) $embeddedId, $embeddedDef->keyType()),
            ];
        }

        return $deletes;
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
