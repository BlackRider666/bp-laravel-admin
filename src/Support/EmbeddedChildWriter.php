<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Support;

use BlackParadise\CoreAdmin\Domain\Contracts\Entity\EntityRecordContract;
use BlackParadise\CoreAdmin\Domain\Contracts\EntityDefinition\EntityDefinitionContract;
use BlackParadise\CoreAdmin\Domain\Entity\EntityRecord;
use BlackParadise\CoreAdmin\Domain\Fields\Base\AbstractRelationField;
use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use BlackParadise\LaravelAdmin\Core\UseCaseFactory;

/**
 * Persists deferred hasOne-embed payloads that ResolveEmbeddedRelationsUseCase
 * defers until the host's primary key is available.
 */
final readonly class EmbeddedChildWriter
{
    public function __construct(
        private EntityDefinitionRegistry $registry,
        private UseCaseFactory $useCases,
        private HasOneFkResolver $fkResolver,
    ) {}

    /**
     * @param array<string, array{field: AbstractRelationField, payload: array<string, mixed>}> $defer
     */
    public function writeAll(
        EntityDefinitionContract $hostDefinition,
        EntityRecordContract $host,
        array $defer,
    ): void {
        foreach ($defer as $info) {
            $field = $info['field'];
            $payload = $info['payload'];

            $payload[$this->fkResolver->resolve($hostDefinition, $field)] = $host->id();

            $embeddedDefClass = $field->embeddedDefinition();
            $embeddedDef = $this->registry->get((new $embeddedDefClass())->resolveName());

            $this->useCases->createRecord($embeddedDef)->execute(
                new EntityRecord($embeddedDef, $payload),
            );
        }
    }
}
