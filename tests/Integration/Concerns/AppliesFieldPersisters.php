<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Concerns;

use BlackParadise\CoreAdmin\Domain\Contracts\EntityDefinition\EntityDefinitionContract;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\FieldPersisters\FieldPersisterRegistry;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\FieldPersisters\PersistContext;
use BlackParadise\LaravelAdmin\Support\DeferredFileOperations;
use Illuminate\Database\Eloquent\Model;

/**
 * Drives the real FieldPersister registry's afterSave() for every payload key
 * of an already-persisted host (replacement for the removed RelationWriter::applyAll()).
 */
trait AppliesFieldPersisters
{
    /**
     * @param array<string, mixed> $payload
     */
    private function applyPersisters(Model $host, EntityDefinitionContract $definition, array $payload): void
    {
        $registry = resolve(FieldPersisterRegistry::class);
        $context = new PersistContext($definition, $payload, resolve(DeferredFileOperations::class), $host);

        foreach ($definition->fields() as $field) {
            if (!array_key_exists($field->name(), $payload)) {
                continue;
            }

            $registry->for($field)->afterSave($field, $payload[$field->name()], $host, $context);
        }
    }
}
