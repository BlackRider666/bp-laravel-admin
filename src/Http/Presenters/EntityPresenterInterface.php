<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Http\Presenters;

use BlackParadise\CoreAdmin\Domain\Contracts\Entity\EntityRecordContract;
use BlackParadise\CoreAdmin\Domain\Contracts\EntityDefinition\EntityDefinitionContract;
use BlackParadise\CoreAdmin\Domain\Query\PaginatedResult;
use Symfony\Component\HttpFoundation\Response;

interface EntityPresenterInterface
{
    public function index(PaginatedResult $paginated, array $fields, EntityDefinitionContract $definition): Response;

    public function create(array $fields, EntityDefinitionContract $definition): Response;

    public function store(EntityRecordContract $created, EntityDefinitionContract $definition): Response;

    public function show(EntityRecordContract $record, array $fields, EntityDefinitionContract $definition): Response;

    public function edit(EntityRecordContract $record, array $fields, EntityDefinitionContract $definition): Response;

    public function update(EntityRecordContract $updated, EntityDefinitionContract $definition, string $id): Response;

    public function destroy(EntityDefinitionContract $definition, string $id): Response;

    /**
     * Render the outcome of a bulk delete operation.
     *
     * @param int $deletedCount Number of records successfully removed.
     * @param list<string> $failedIds Ids that authorization rejected.
     * @param list<string> $notFoundIds Ids that no longer existed at delete time.
     */
    public function bulkDestroyResult(
        int $deletedCount,
        array $failedIds,
        array $notFoundIds,
        EntityDefinitionContract $definition,
    ): Response;

    public function unauthorized(): Response;

    public function notFound(): Response;

    /** @param array<string, array<string>> $errors */
    public function validationError(array $errors): Response;
}
