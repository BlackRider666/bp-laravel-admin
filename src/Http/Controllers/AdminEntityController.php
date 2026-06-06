<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Http\Controllers;

use BlackParadise\CoreAdmin\Application\Exceptions\EntityNotFoundException;
use BlackParadise\CoreAdmin\Domain\Contracts\Action\ActionContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Auth\AuthorizationProviderContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Entity\EntityRecordContract;
use BlackParadise\CoreAdmin\Domain\Contracts\EntityDefinition\EntityDefinitionContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Fields\FieldContract;
use BlackParadise\CoreAdmin\Domain\Contracts\TransactionContract;
use BlackParadise\CoreAdmin\Domain\Entity\EntityRecord;
use BlackParadise\CoreAdmin\Domain\Exceptions\UnauthorizedException;
use BlackParadise\CoreAdmin\Domain\Exceptions\ValidationException;
use BlackParadise\CoreAdmin\Domain\ValueObjects\EntityKey;
use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use BlackParadise\LaravelAdmin\Core\UseCaseFactory;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Http\Presenters\EntityPresenterInterface;
use BlackParadise\LaravelAdmin\Http\Requests\EntityBulkDestroyRequest;
use BlackParadise\LaravelAdmin\Http\Requests\EntityIndexRequest;
use BlackParadise\LaravelAdmin\Http\Requests\EntityWriteRequest;
use BlackParadise\LaravelAdmin\Support\DeferredFileOperations;
use BlackParadise\LaravelAdmin\Support\EmbeddedChildWriter;
use BlackParadise\LaravelAdmin\Support\I18nErrorTranslator;
use BlackParadise\LaravelAdmin\Support\OwnedDeletesCollector;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Thin CRUD controller for all admin entity routes.
 *
 * Each method:
 *   1. Resolves the {@see EntityDefinitionContract} via Form Request or registry.
 *   2. Runs the appropriate use case (inside a transaction when mutating).
 *   3. Delegates response formatting to {@see EntityPresenterInterface} —
 *      always called OUTSIDE the transaction so presenters can perform HTTP
 *      concerns (redirects, flash messages) without holding DB locks.
 */
final class AdminEntityController extends AbstractAdminController
{
    public function __construct(
        EntityDefinitionRegistry $registry,
        private readonly UseCaseFactory $useCases,
        private readonly EntityPresenterInterface $presenter,
        private readonly EmbeddedChildWriter $embeddedChildWriter,
        private readonly OwnedDeletesCollector $ownedDeletesCollector,
        private readonly I18nErrorTranslator $errorTranslator,
        private readonly TransactionContract $transactions,
        private readonly AuthorizationProviderContract $authorization,
        private readonly DeferredFileOperations $deferredFiles,
    ) {
        parent::__construct($registry);
    }

    public function index(EntityIndexRequest $request): Response
    {
        $definition = $request->definition();

        try {
            $paginated = $this->useCases->listRecords($definition)->execute($request->criteria());

            $fields = array_values(array_filter(
                $definition->fields(),
                fn(FieldContract $f): bool => $f->visibleOnList(),
            ));

            return $this->presenter->index($paginated, $fields, $definition);
        } catch (UnauthorizedException) {
            return $this->presenter->unauthorized();
        }
    }

    public function create(Request $request): Response
    {
        $definition = $this->registry->get($this->entityName($request));

        // Embed-only definitions have no standalone create surface.
        if ($definition instanceof EntityDefinition && !$definition->isCreatable()) {
            return $this->presenter->notFound();
        }

        try {
            $fields = $this->useCases->buildFormView($definition)->execute('create');

            return $this->presenter->create($fields, $definition);
        } catch (UnauthorizedException) {
            return $this->presenter->unauthorized();
        }
    }

    public function store(EntityWriteRequest $request): Response
    {
        $definition = $request->definition();

        // Embed-only definitions have no standalone create surface.
        if ($definition instanceof EntityDefinition && !$definition->isCreatable()) {
            return $this->presenter->notFound();
        }

        $raw = $request->attributesForWrite();
        $committed = false;

        // Signal that the controller owns the outermost transaction boundary.
        // The mutator will see hasOuterScope() === true and skip its own self-flush,
        // deferring to the finally block below.
        $this->deferredFiles->beginOuterScope();

        try {
            /** @var EntityRecordContract $created */
            $created = $this->transactions->executeInTransaction(function () use ($definition, $raw): EntityRecordContract {
                $resolved = $this->useCases->resolveEmbeddedRelations()->resolveOnStore($definition, $raw);

                $created = $this->useCases->createRecord($definition)->execute(
                    new EntityRecord($definition, $resolved['attributes']),
                );

                // Embedded child writes are part of the same write — keep them inside
                // the transaction so a child failure rolls back the parent row too.
                $this->embeddedChildWriter->writeAll($definition, $created, $resolved['defer']);

                return $created;
            });
            $committed = true;
        } catch (ValidationException $e) {
            return $this->presenter->validationError($this->errorTranslator->translate($e->errors()));
        } catch (UnauthorizedException) {
            return $this->presenter->unauthorized();
        } finally {
            $this->deferredFiles->endOuterScope();
            $committed ? $this->deferredFiles->commit() : $this->deferredFiles->rollback();
        }

        return $this->presenter->store($created, $definition);
    }

    public function show(Request $request): Response
    {
        $definition = $this->registry->get($this->entityName($request));
        $key = $this->entityKey($request);

        try {
            $record = $this->useCases->findRecord($definition)->execute($key);

            $fields = array_values(array_filter(
                $definition->fields(),
                fn(FieldContract $f): bool => $f->visibleOnShow(),
            ));

            return $this->presenter->show($record, $fields, $definition);
        } catch (UnauthorizedException) {
            return $this->presenter->unauthorized();
        } catch (EntityNotFoundException) {
            return $this->presenter->notFound();
        }
    }

    public function edit(Request $request): Response
    {
        $definition = $this->registry->get($this->entityName($request));
        $key = $this->entityKey($request);

        try {
            $record = $this->useCases->findRecord($definition)->execute($key);
            $fields = $this->useCases->buildFormView($definition)->execute('update');

            return $this->presenter->edit($record, $fields, $definition);
        } catch (UnauthorizedException) {
            return $this->presenter->unauthorized();
        } catch (EntityNotFoundException) {
            return $this->presenter->notFound();
        }
    }

    public function update(EntityWriteRequest $request): Response
    {
        $definition = $request->definition();
        $key = $this->entityKey($request);
        $raw = $request->attributesForWrite();
        $committed = false;

        // Signal that the controller owns the outermost transaction boundary.
        $this->deferredFiles->beginOuterScope();

        try {
            /** @var EntityRecordContract $updated */
            $updated = $this->transactions->executeInTransaction(function () use ($definition, $key, $raw): EntityRecordContract {
                $currentHost = $this->useCases->findRecord($definition)->execute($key);

                $resolved = $this->useCases->resolveEmbeddedRelations()
                    ->resolveOnUpdate($definition, $currentHost, $raw);

                $updated = $this->useCases->updateRecord($definition)->execute(
                    $key,
                    new EntityRecord($definition, $resolved['attributes']),
                );

                $this->embeddedChildWriter->writeAll($definition, $updated, $resolved['defer']);

                return $updated;
            });
            $committed = true;
        } catch (ValidationException $e) {
            return $this->presenter->validationError($this->errorTranslator->translate($e->errors()));
        } catch (UnauthorizedException) {
            return $this->presenter->unauthorized();
        } catch (EntityNotFoundException) {
            return $this->presenter->notFound();
        } finally {
            $this->deferredFiles->endOuterScope();
            $committed ? $this->deferredFiles->commit() : $this->deferredFiles->rollback();
        }

        return $this->presenter->update($updated, $definition, (string) $key);
    }

    public function destroy(Request $request): Response
    {
        $definition = $this->registry->get($this->entityName($request));
        $key = $this->entityKey($request);
        $committed = false;

        // Signal that the controller owns the outermost transaction boundary.
        $this->deferredFiles->beginOuterScope();

        try {
            $this->transactions->executeInTransaction(function () use ($definition, $key): void {
                $this->deleteHostWithOwned($definition, $key);
            });
            $committed = true;
        } catch (UnauthorizedException) {
            return $this->presenter->unauthorized();
        } catch (EntityNotFoundException) {
            return $this->presenter->notFound();
        } finally {
            $this->deferredFiles->endOuterScope();
            $committed ? $this->deferredFiles->commit() : $this->deferredFiles->rollback();
        }

        return $this->presenter->destroy($definition, (string) $key);
    }

    public function bulkDestroy(EntityBulkDestroyRequest $request): Response
    {
        $definition = $request->definition();

        $deleted = 0;
        /** @var list<string> $failedIds */
        $failedIds = [];
        /** @var list<string> $notFoundIds */
        $notFoundIds = [];

        foreach ($request->entityKeys() as $key) {
            try {
                $this->transactions->executeInTransaction(fn() => $this->deleteHostWithOwned($definition, $key));
                $this->deferredFiles->commit();
                $deleted++;
            } catch (UnauthorizedException) {
                // Partial-success policy: skip records the caller cannot delete,
                // report them back so the UI can surface a warning.
                $this->deferredFiles->rollback();
                $failedIds[] = (string) $key;
            } catch (EntityNotFoundException) {
                // Record vanished between selection and delete — treat as already done.
                $this->deferredFiles->rollback();
                $notFoundIds[] = (string) $key;
            }
        }

        return $this->presenter->bulkDestroyResult($deleted, $failedIds, $notFoundIds, $definition);
    }

    public function action(Request $request): Response
    {
        $definition = $this->registry->get($this->entityName($request));
        $actionName = (string) $request->route('action');

        /** @var ActionContract|null $action */
        $action = collect($definition->actions())
            ->first(fn(ActionContract $a): bool => $a->name() === $actionName);

        if ($action === null) {
            return $this->presenter->notFound();
        }

        if (!$this->authorization->can('action.' . $actionName, $definition)) {
            return $this->presenter->unauthorized();
        }

        // ActionContract currently exposes only metadata (name/label/scope/permission/
        // confirm/meta) and does not declare an execute() method. Until an
        // ExecuteActionUseCase + dispatcher contract land in bp-admin-core,
        // we authorise the call, then return a flash-only response. This keeps
        // controllers thin and avoids leaking business logic here.
        $rowId = $request->route('id');
        $message = $action->label() . ' was dispatched.';

        return $this->presenter->actionResult($message, $rowId !== null ? (string) $rowId : null);
    }

    private function entityKey(Request $request): EntityKey
    {
        /** @var EntityKey $key */
        $key = $request->attributes->get('entity_key');
        return $key;
    }

    private function deleteHostWithOwned(EntityDefinitionContract $definition, EntityKey $key): void
    {
        $host = $this->useCases->findRecord($definition)->execute($key);
        $ownedDeletes = $this->ownedDeletesCollector->collect($definition, $host);

        $this->useCases->deleteRecord($definition)->execute($key);

        foreach ($ownedDeletes as $del) {
            $this->useCases->deleteRecord($del['def'])->execute($del['key']);
        }
    }
}
