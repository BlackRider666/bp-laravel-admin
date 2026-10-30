<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Infrastructure\Persistence\Query;

use BlackParadise\CoreAdmin\Domain\Contracts\EntityDefinition\EntityDefinitionContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Fields\RelationFieldContract;
use BlackParadise\CoreAdmin\Domain\Fields\MorphFileField;
use BlackParadise\CoreAdmin\Domain\Fields\RelationPathField;

/**
 * Decides which Eloquent relations a definition needs eager-loaded for list/find.
 */
final class EagerLoadPlanner
{
    /**
     * Collect Eloquent relation names for eager-loading:
     *   - RelationFieldContract → relationName() + nested paths for embedded/displayEagerLoad
     *   - RelationPathField → relationPrefix() (supports nested dot-paths for Laravel)
     *   - MorphFileField → getMorphName() (morph relation stored on host model)
     *
     * Deduplicates so two RelationPathFields under the same relation produce one ->with().
     *
     * @return array<string>
     */
    public function plan(EntityDefinitionContract $definition): array
    {
        $relations = [];
        foreach ($definition->fields() as $field) {
            if ($field instanceof RelationFieldContract) {
                $relationName             = $field->relationName();
                $relations[$relationName] = true;
                foreach ($this->nestedRelationPaths($field) as $path) {
                    $relations[$relationName . '.' . $path] = true;
                }
                continue;
            }
            if ($field instanceof RelationPathField) {
                $relations[$field->relationPrefix()] = true;
                continue;
            }
            // MorphFileField extends AbstractField (not RelationFieldContract) but
            // uses a morph relation on the host model — must be eager-loaded for list.
            if ($field instanceof MorphFileField) {
                $relations[$field->getMorphName()] = true;
            }
        }
        return array_keys($relations);
    }

    /**
     * Nested (one level) relation paths under $field:
     *   - embedded → every RelationFieldContract in the embedded definition
     *   - displayEagerLoad() → declared relations for the computed display label
     *
     * @return list<string>
     */
    private function nestedRelationPaths(RelationFieldContract $field): array
    {
        $paths = [];

        if ($field->isEmbedded()) {
            $embeddedClass = $field->embeddedDefinition();
            if ($embeddedClass !== null && class_exists($embeddedClass)) {
                /** @var EntityDefinitionContract $embeddedDef */
                $embeddedDef = new $embeddedClass();
                foreach ($embeddedDef->fields() as $sub) {
                    if ($sub instanceof RelationFieldContract) {
                        $paths[$sub->relationName()] = true;
                    }
                }
            }
        }

        foreach ($field->displayEagerLoad() as $rel) {
            $paths[$rel] = true;
        }

        return array_keys($paths);
    }
}
