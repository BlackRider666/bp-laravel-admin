<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Support;

use BlackParadise\CoreAdmin\Domain\Contracts\Entity\EntityRecordContract;
use BlackParadise\CoreAdmin\Domain\Contracts\EntityDefinition\EntityDefinitionContract;
use BlackParadise\CoreAdmin\Domain\Fields\Base\AbstractRelationField;
use Illuminate\Database\Eloquent\Model;

/**
 * Persists deferred hasOne-embed payloads that ResolveEmbeddedRelationsUseCase
 * defers until the host's primary key is available.
 *
 * Children are written through the host's Eloquent relation so the foreign key
 * back to the host is assigned automatically. Writing through the relation
 * (rather than the embedded create use case) is deliberate: the column filter
 * in {@see \BlackParadise\LaravelAdmin\Infrastructure\Persistence\EloquentEntityMutator}
 * keeps only fields the embedded definition declares, which would strip an
 * injected FK column the author has no reason to expose as a form field. It
 * also mirrors how hasMany-embed children are persisted by
 * {@see \BlackParadise\LaravelAdmin\Infrastructure\Persistence\RelationWriter}.
 */
final readonly class EmbeddedChildWriter
{
    /**
     * @param array<string, array{field: AbstractRelationField, payload: array<string, mixed>}> $defer
     */
    public function writeAll(
        EntityDefinitionContract $hostDefinition,
        EntityRecordContract $host,
        array $defer,
    ): void {
        if ($defer === []) {
            return;
        }

        /** @var Model $hostModel */
        $hostModel = resolve($hostDefinition->modelClass())
            ->newQuery()
            ->findOrFail($host->id());

        foreach ($defer as $info) {
            $field = $info['field'];
            $payload = $info['payload'];

            // Persist through the host relation — Eloquent sets the FK itself.
            $hostModel->{$field->relationName()}()->create($payload);
        }
    }
}
