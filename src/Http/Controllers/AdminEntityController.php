<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Http\Controllers;

use BlackParadise\CoreAdmin\Domain\Action\ActionContext;
use BlackParadise\CoreAdmin\Domain\Commands\SaveEntityGraphCommand;
use BlackParadise\CoreAdmin\Domain\Contracts\EntityDefinition\EntityDefinitionContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Fields\FieldContract;
use BlackParadise\CoreAdmin\Domain\Exceptions\ActionNotFoundException;
use BlackParadise\CoreAdmin\Domain\Exceptions\CapabilityDeniedException;
use BlackParadise\CoreAdmin\Domain\Exceptions\EntityNotFoundException;
use BlackParadise\CoreAdmin\Domain\Exceptions\InvalidActionScopeException;
use BlackParadise\CoreAdmin\Domain\Exceptions\UnauthorizedException;
use BlackParadise\CoreAdmin\Domain\Exceptions\ValidationException;
use BlackParadise\CoreAdmin\Domain\ValueObjects\Capability;
use BlackParadise\CoreAdmin\Domain\ValueObjects\EntityKey;
use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use BlackParadise\LaravelAdmin\Core\UseCaseFactory;
use BlackParadise\LaravelAdmin\Http\Presenters\EntityPresenterInterface;
use BlackParadise\LaravelAdmin\Http\Requests\EntityActionRequest;
use BlackParadise\LaravelAdmin\Http\Requests\EntityBulkDestroyRequest;
use BlackParadise\LaravelAdmin\Http\Requests\EntityIndexRequest;
use BlackParadise\LaravelAdmin\Http\Requests\EntityWriteRequest;
use BlackParadise\LaravelAdmin\Support\I18nErrorTranslator;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Thin CRUD controller for all admin entity routes.
 *
 * Each method:
 *   1. Resolves the EntityDefinitionContract via Form Request or registry.
 *   2. Runs the appropriate use case (writes run inside the use case's write scope).
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
        private readonly I18nErrorTranslator $errorTranslator,
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
        if (!$this->allows($definition, Capability::Create)) {
            return $this->presenter->notFound();
        }

        try {
            $fields = $this->useCases->buildFormView($definition)->execute('create');

            return $this->presenter->create($fields, $definition);
        } catch (CapabilityDeniedException) {
            return $this->presenter->notFound();
        } catch (UnauthorizedException) {
            return $this->presenter->unauthorized();
        }
    }

    public function store(EntityWriteRequest $request): Response
    {
        $definition = $request->definition();

        // Embed-only definitions have no standalone create surface.
        if (!$this->allows($definition, Capability::Create)) {
            return $this->presenter->notFound();
        }

        try {
            $created = $this->useCases->saveEntityGraph()->execute(
                new SaveEntityGraphCommand($definition, null, $request->attributesForWrite()),
            );
        } catch (ValidationException $e) {
            return $this->presenter->validationError($this->errorTranslator->translate($e->errors()));
        } catch (CapabilityDeniedException) {
            return $this->presenter->notFound();
        } catch (UnauthorizedException) {
            return $this->presenter->unauthorized();
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

        if (!$this->allows($definition, Capability::Update)) {
            return $this->presenter->notFound();
        }

        try {
            $record = $this->useCases->findRecord($definition)->execute($key);
            $fields = $this->useCases->buildFormView($definition)->execute('update');

            return $this->presenter->edit($record, $fields, $definition);
        } catch (CapabilityDeniedException) {
            return $this->presenter->notFound();
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

        if (!$this->allows($definition, Capability::Update)) {
            return $this->presenter->notFound();
        }

        try {
            $updated = $this->useCases->saveEntityGraph()->execute(
                new SaveEntityGraphCommand($definition, $key, $request->attributesForWrite()),
            );
        } catch (ValidationException $e) {
            return $this->presenter->validationError($this->errorTranslator->translate($e->errors()));
        } catch (CapabilityDeniedException) {
            return $this->presenter->notFound();
        } catch (UnauthorizedException) {
            return $this->presenter->unauthorized();
        } catch (EntityNotFoundException) {
            return $this->presenter->notFound();
        }

        return $this->presenter->update($updated, $definition, (string) $key);
    }

    public function destroy(Request $request): Response
    {
        $definition = $this->registry->get($this->entityName($request));
        $key = $this->entityKey($request);

        if (!$this->allows($definition, Capability::Delete)) {
            return $this->presenter->notFound();
        }

        try {
            $this->useCases->deleteEntityGraph()->execute($definition, $key);
        } catch (CapabilityDeniedException) {
            return $this->presenter->notFound();
        } catch (UnauthorizedException) {
            return $this->presenter->unauthorized();
        } catch (EntityNotFoundException) {
            return $this->presenter->notFound();
        }

        return $this->presenter->destroy($definition, (string) $key);
    }

    public function bulkDestroy(EntityBulkDestroyRequest $request): Response
    {
        $definition = $request->definition();

        if (!$this->allows($definition, Capability::Delete)) {
            return $this->presenter->notFound();
        }

        $useCase = $this->useCases->deleteEntityGraph();

        $deleted = 0;
        /** @var list<string> $failedIds */
        $failedIds = [];
        /** @var list<string> $notFoundIds */
        $notFoundIds = [];

        // Partial-success policy: every key is its own write scope (own transaction,
        // files and events), so one failing key never affects the others.
        foreach ($request->entityKeys() as $key) {
            try {
                $useCase->execute($definition, $key);
                $deleted++;
            } catch (EntityNotFoundException) {
                // Record vanished between selection and delete — treat as already done.
                $notFoundIds[] = (string) $key;
            } catch (Throwable $e) {
                if (!$e instanceof UnauthorizedException) {
                    report($e);
                }
                $failedIds[] = (string) $key;
            }
        }

        return $this->presenter->bulkDestroyResult($deleted, $failedIds, $notFoundIds, $definition);
    }

    public function action(EntityActionRequest $request): Response
    {
        $definition = $request->definition();
        $keys = $request->entityKeys();

        try {
            $result = $this->useCases->executeAction()->execute(
                $definition,
                $request->actionName(),
                new ActionContext($definition, $request->actionScope(), $keys, $request->actionInput()),
            );
        } catch (ActionNotFoundException) {
            return $this->presenter->notFound();
        } catch (UnauthorizedException) {
            return $this->presenter->unauthorized();
        } catch (InvalidActionScopeException $e) {
            return $this->presenter->validationError(['action' => [$e->getMessage()]]);
        } catch (ValidationException $e) {
            return $this->presenter->validationError(
                $this->prefixActionInputKeys($this->errorTranslator->translate($e->errors())),
            );
        }

        return $this->presenter->actionResult($result, $definition);
    }

    /**
     * Action-form fields are posted as `input[field]`, so their errors are keyed `input.field`.
     *
     * @param array<string, array<int, string>> $errors
     * @return array<string, array<int, string>>
     */
    private function prefixActionInputKeys(array $errors): array
    {
        $prefixed = [];
        foreach ($errors as $key => $messages) {
            $prefixed['input.' . $key] = $messages;
        }

        return $prefixed;
    }

    /**
     * Embed-only / read-only definitions expose no matching write surface.
     * Reads the definition's capabilities (not its concrete class).
     */
    private function allows(EntityDefinitionContract $definition, Capability $capability): bool
    {
        return $definition->capabilities()->allows($capability);
    }

    private function entityKey(Request $request): EntityKey
    {
        /** @var EntityKey $key */
        $key = $request->attributes->get('entity_key');
        return $key;
    }
}
