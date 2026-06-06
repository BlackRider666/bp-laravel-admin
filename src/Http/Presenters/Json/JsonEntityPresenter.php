<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Http\Presenters\Json;

use BlackParadise\CoreAdmin\Domain\Contracts\Entity\EntityRecordContract;
use BlackParadise\CoreAdmin\Domain\Contracts\EntityDefinition\EntityDefinitionContract;
use BlackParadise\CoreAdmin\Domain\Query\PaginatedResult;
use BlackParadise\LaravelAdmin\Http\Presenters\EntityPresenterInterface;
use Symfony\Component\HttpFoundation\Response;

final class JsonEntityPresenter implements EntityPresenterInterface
{
    public function index(PaginatedResult $paginated, array $fields, EntityDefinitionContract $definition): Response
    {
        return response()->json([
            'data' => array_map(fn(EntityRecordContract $record): array => $this->project($record, $fields), $paginated->items),
        ]);
    }

    public function create(array $fields, EntityDefinitionContract $definition): Response
    {
        return response()->json([
            'entity' => $definition->name(),
            'action' => 'create',
            'fields' => array_map(fn($f): array => ['name' => $f->name(), 'type' => $f->type()], $fields),
        ]);
    }

    public function store(EntityRecordContract $created, EntityDefinitionContract $definition): Response
    {
        return response()->json(['data' => $created->toArray()], 201);
    }

    public function show(EntityRecordContract $record, array $fields, EntityDefinitionContract $definition): Response
    {
        return response()->json(['data' => $this->project($record, $fields)]);
    }

    public function edit(EntityRecordContract $record, array $fields, EntityDefinitionContract $definition): Response
    {
        return response()->json([
            'entity' => $definition->name(),
            'action' => 'edit',
            'data'   => $this->project($record, $fields),
            'fields' => array_map(fn($f): array => ['name' => $f->name(), 'type' => $f->type()], $fields),
        ]);
    }

    /**
     * Project the record through the field list — only expose columns whose name
     * matches a FieldContract declared in $fields. Prevents leaking hidden/hashed
     * columns that are not part of the visible field set.
     *
     * @param array<mixed> $fields
     * @return array<string, mixed>
     */
    private function project(EntityRecordContract $record, array $fields): array
    {
        $names = array_map(static fn(mixed $f): string => $f->name(), $fields);
        return array_intersect_key($record->toArray(), array_flip($names));
    }

    public function update(EntityRecordContract $updated, EntityDefinitionContract $definition, string $id): Response
    {
        return response()->json(['data' => $updated->toArray()]);
    }

    public function destroy(EntityDefinitionContract $definition, string $id): Response
    {
        return response()->json(null, 204);
    }

    public function bulkDestroyResult(
        int $deletedCount,
        array $failedIds,
        array $notFoundIds,
        EntityDefinitionContract $definition,
    ): Response {
        return response()->json([
            'deleted'      => $deletedCount,
            'failed_ids'   => array_values($failedIds),
            'not_found_ids' => array_values($notFoundIds),
            'entity'       => $definition->name(),
        ]);
    }

    public function unauthorized(): Response
    {
        return response()->json(['message' => 'Unauthorized.'], 403);
    }

    public function notFound(): Response
    {
        return response()->json(['message' => 'Not found.'], 404);
    }

    public function validationError(array $errors): Response
    {
        return response()->json(['message' => 'Validation failed.', 'errors' => $errors], 422);
    }

    public function actionResult(string $message, ?string $rowId = null): Response
    {
        return response()->json(['message' => $message, 'id' => $rowId]);
    }
}
