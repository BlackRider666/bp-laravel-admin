<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Support;

use BlackParadise\CoreAdmin\Domain\Contracts\Entity\EntityRecordContract;
use BlackParadise\CoreAdmin\Domain\Contracts\EntityDefinition\EntityDefinitionContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Persistence\EmbeddedChildWriterContract;
use BlackParadise\CoreAdmin\Domain\Fields\Base\AbstractRelationField;
use BlackParadise\LaravelAdmin\Exceptions\ModelLessDefinitionException;
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
 * the hasMany persister ({@see \BlackParadise\LaravelAdmin\Infrastructure\Persistence\FieldPersisters\HasManyPersister}).
 */
final readonly class EmbeddedChildWriter implements EmbeddedChildWriterContract
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

        // Build a stub host model with the known PK instead of re-fetching from
        // the database. Eloquent only needs the primary-key value to populate the
        // FK on the child when writing through the relation; a full SELECT is
        // wasteful because the host record was just created / updated by the caller.
        /** @var Model $hostModel */
        $hostModel = resolve($hostDefinition->modelClass() ?? throw ModelLessDefinitionException::forDefinition($hostDefinition))
            ->newInstance()
            ->forceFill([$hostDefinition->keyField() => $host->id()]);
        $hostModel->exists = true;

        foreach ($defer as $info) {
            $field = $info['field'];
            $payload = $info['payload'];

            // Persist through the host relation — Eloquent sets the FK itself.
            $hostModel->{$field->relationName()}()->create($payload);
        }
    }
}
