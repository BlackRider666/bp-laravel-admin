<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Core;

use BlackParadise\CoreAdmin\Application\UseCases\Action\ExecuteActionUseCase;
use BlackParadise\CoreAdmin\Application\UseCases\Entity\BuildFormViewUseCase;
use BlackParadise\CoreAdmin\Application\UseCases\Entity\CreateRecordUseCase;
use BlackParadise\CoreAdmin\Application\UseCases\Entity\DeleteEntityGraphUseCase;
use BlackParadise\CoreAdmin\Application\UseCases\Entity\DeleteRecordUseCase;
use BlackParadise\CoreAdmin\Application\UseCases\Entity\FindRecordUseCase;
use BlackParadise\CoreAdmin\Application\UseCases\Entity\ListRecordsUseCase;
use BlackParadise\CoreAdmin\Application\UseCases\Entity\ResolveEmbeddedRelationsUseCase;
use BlackParadise\CoreAdmin\Application\UseCases\Entity\RuleBuilder;
use BlackParadise\CoreAdmin\Application\UseCases\Entity\SaveEntityGraphUseCase;
use BlackParadise\CoreAdmin\Application\UseCases\Entity\UpdateRecordUseCase;
use BlackParadise\CoreAdmin\Domain\Contracts\Action\ActionResolverContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Auth\AuthorizationProviderContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Entity\EntityRecordContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Entity\RelationOptionsProviderContract;
use BlackParadise\CoreAdmin\Domain\Contracts\EntityDefinition\EntityDefinitionContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Events\EventDispatcherContract;
use BlackParadise\CoreAdmin\Domain\Contracts\LocaleProviderContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Persistence\EmbeddedChildWriterContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Persistence\OwnedDeletesCollectorContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Persistence\WriteScopeContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Validation\ValidationProviderContract;
use BlackParadise\CoreAdmin\Domain\Mutators\EntityMutatorInterface;
use BlackParadise\CoreAdmin\Domain\Repositories\EntityRepositoryInterface;
use BlackParadise\CoreAdmin\Domain\ValueObjects\EntityKey;
use LogicException;

final readonly class UseCaseFactory
{
    public function __construct(
        private EntityRepositoryInterface $repository,
        private EntityMutatorInterface $mutator,
        private AuthorizationProviderContract $authorization,
        private ValidationProviderContract $validator,
        private EventDispatcherContract $dispatcher,
        private EntityDefinitionRegistry $registry,
        private RelationOptionsProviderContract $relationOptions,
        private LocaleProviderContract $localeProvider,
        // Graph / action collaborators: optional so the read-side factory stays constructible
        // without them; the container always injects them (all four contracts are bound).
        private ?WriteScopeContract $writeScope = null,
        private ?EmbeddedChildWriterContract $embeddedWriter = null,
        private ?OwnedDeletesCollectorContract $ownedCollector = null,
        private ?ActionResolverContract $actionResolver = null,
        // Per-definition repository/mutator selection; when absent the global pair above is used.
        private ?EntityPersistenceResolver $persistence = null,
    ) {}

    private function repositoryFor(EntityDefinitionContract $def): EntityRepositoryInterface
    {
        return $this->persistence?->repositoryFor($def) ?? $this->repository;
    }

    private function mutatorFor(EntityDefinitionContract $def): EntityMutatorInterface
    {
        return $this->persistence?->mutatorFor($def) ?? $this->mutator;
    }

    /** Repository for use cases that serve several definitions (graph): routes per definition. */
    private function routedRepository(): EntityRepositoryInterface
    {
        return $this->persistence instanceof EntityPersistenceResolver ? new RoutedEntityRepository($this->persistence) : $this->repository;
    }

    private function routedMutator(): EntityMutatorInterface
    {
        return $this->persistence instanceof EntityPersistenceResolver ? new RoutedEntityMutator($this->persistence) : $this->mutator;
    }

    public function saveEntityGraph(): SaveEntityGraphUseCase
    {
        return new SaveEntityGraphUseCase(
            $this->authorization,
            $this->required($this->writeScope, 'WriteScopeContract'),
            $this->routedRepository(),
            $this->resolveEmbeddedRelations(),
            $this->required($this->embeddedWriter, 'EmbeddedChildWriterContract'),
            fn(EntityDefinitionContract $def): CreateRecordUseCase => $this->createRecord($def),
            fn(EntityDefinitionContract $def): UpdateRecordUseCase => $this->updateRecord($def),
        );
    }

    public function deleteEntityGraph(): DeleteEntityGraphUseCase
    {
        return new DeleteEntityGraphUseCase(
            $this->authorization,
            $this->required($this->writeScope, 'WriteScopeContract'),
            $this->routedRepository(),
            $this->routedMutator(),
            $this->required($this->ownedCollector, 'OwnedDeletesCollectorContract'),
            $this->dispatcher,
        );
    }

    public function executeAction(): ExecuteActionUseCase
    {
        return new ExecuteActionUseCase(
            $this->required($this->actionResolver, 'ActionResolverContract'),
            $this->authorization,
            $this->validator,
            $this->localeProvider,
            $this->required($this->writeScope, 'WriteScopeContract'),
            $this->dispatcher,
        );
    }

    /**
     * @template T of object
     * @param T|null $dependency
     * @return T
     */
    private function required(?object $dependency, string $name): object
    {
        return $dependency ?? throw new LogicException($name . ' was not injected into UseCaseFactory.');
    }

    public function listRecords(EntityDefinitionContract $def): ListRecordsUseCase
    {
        return new ListRecordsUseCase($this->repositoryFor($def), $this->authorization, $def);
    }

    public function findRecord(EntityDefinitionContract $def): FindRecordUseCase
    {
        return new FindRecordUseCase($this->repositoryFor($def), $this->authorization, $def);
    }

    public function createRecord(EntityDefinitionContract $def): CreateRecordUseCase
    {
        return new CreateRecordUseCase(
            $this->mutatorFor($def),
            $this->authorization,
            $def,
            $this->validator,
            $this->dispatcher,
            $this->localeProvider,
        );
    }

    /** Embedded-child create: skips the standalone Capability::Create gate; authorization still applies. */
    private function createEmbeddedChild(EntityDefinitionContract $def): CreateRecordUseCase
    {
        return new CreateRecordUseCase(
            $this->mutatorFor($def),
            $this->authorization,
            $def,
            $this->validator,
            $this->dispatcher,
            $this->localeProvider,
            enforceCapability: false,
        );
    }

    /** Embedded-child update: skips the standalone Capability::Update gate; authorization still applies. */
    private function updateEmbeddedChild(EntityDefinitionContract $def): UpdateRecordUseCase
    {
        return new UpdateRecordUseCase(
            $this->mutatorFor($def),
            $this->authorization,
            $def,
            $this->validator,
            $this->dispatcher,
            $this->localeProvider,
            enforceCapability: false,
        );
    }

    public function updateRecord(EntityDefinitionContract $def): UpdateRecordUseCase
    {
        return new UpdateRecordUseCase(
            $this->mutatorFor($def),
            $this->authorization,
            $def,
            $this->validator,
            $this->dispatcher,
            $this->localeProvider,
        );
    }

    public function deleteRecord(EntityDefinitionContract $def): DeleteRecordUseCase
    {
        return new DeleteRecordUseCase(
            $this->repositoryFor($def),
            $this->mutatorFor($def),
            $this->authorization,
            $def,
            $this->dispatcher,
        );
    }

    public function buildFormView(EntityDefinitionContract $def): BuildFormViewUseCase
    {
        return new BuildFormViewUseCase($this->authorization, $def, $this->relationOptions);
    }

    /**
     * Build the embedded-relation resolver. The use case is framework-pure: this
     * factory wires its closures back to the existing per-definition use cases
     * and to the {@see EntityDefinitionRegistry} so the controller stays thin.
     */
    public function resolveEmbeddedRelations(): ResolveEmbeddedRelationsUseCase
    {
        return new ResolveEmbeddedRelationsUseCase(
            // Capabilities govern standalone surfaces only; embedded children are governed by
            // authorization (still checked first inside each use case).
            createRecord: fn(EntityDefinitionContract $def, EntityRecordContract $rec): EntityRecordContract => $this->createEmbeddedChild($def)->execute($rec),
            updateRecord: fn(EntityDefinitionContract $def, EntityKey $key, EntityRecordContract $rec): EntityRecordContract => $this->updateEmbeddedChild($def)->execute($key, $rec),
            resolveDefinition: fn(string $defClass): EntityDefinitionContract => $this->registry->forClass($defClass),
            validateRecord: function (EntityDefinitionContract $def, array $attrs, array $skip = []): void {
                // Build locale-expanded rules from $def (same path the core create use case
                // uses) and validate the child through the marker-resolving provider. Throws
                // ValidationException on failure — converting DB 500s into 422s for
                // hasMany/morphMany children that carry required fields.
                $rules = (new RuleBuilder($this->localeProvider->availableLocales(), 'create'))
                    ->build($def, array_keys($attrs));

                // $skip contains back-FK field names that the ORM auto-assigns on write;
                // they must be excluded from child validation so the form can omit them.
                foreach ($skip as $field) {
                    unset($rules[$field]);
                }

                $this->validator->validate($attrs, $rules);
            },
        );
    }
}
