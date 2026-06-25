<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Core;

use BlackParadise\CoreAdmin\Application\UseCases\Entity\BuildFormViewUseCase;
use BlackParadise\CoreAdmin\Application\UseCases\Entity\CreateRecordUseCase;
use BlackParadise\CoreAdmin\Application\UseCases\Entity\DeleteRecordUseCase;
use BlackParadise\CoreAdmin\Application\UseCases\Entity\FindRecordUseCase;
use BlackParadise\CoreAdmin\Application\UseCases\Entity\ListRecordsUseCase;
use BlackParadise\CoreAdmin\Application\UseCases\Entity\ResolveEmbeddedRelationsUseCase;
use BlackParadise\CoreAdmin\Application\UseCases\Entity\UpdateRecordUseCase;
use BlackParadise\CoreAdmin\Domain\Contracts\Auth\AuthorizationProviderContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Entity\EntityRecordContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Entity\RelationOptionsProviderContract;
use BlackParadise\CoreAdmin\Domain\Contracts\EntityDefinition\EntityDefinitionContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Events\EventDispatcherContract;
use BlackParadise\CoreAdmin\Domain\Contracts\LocaleProviderContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Validation\ValidationProviderContract;
use BlackParadise\CoreAdmin\Domain\Mutators\EntityMutatorInterface;
use BlackParadise\CoreAdmin\Domain\Repositories\EntityRepositoryInterface;
use BlackParadise\CoreAdmin\Domain\ValueObjects\EntityKey;
use BlackParadise\LaravelAdmin\Infrastructure\Validation\LocaleAwareValidationWrapper;

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
    ) {}

    public function listRecords(EntityDefinitionContract $def): ListRecordsUseCase
    {
        return new ListRecordsUseCase($this->repository, $this->authorization, $def);
    }

    public function findRecord(EntityDefinitionContract $def): FindRecordUseCase
    {
        return new FindRecordUseCase($this->repository, $this->authorization, $def);
    }

    public function createRecord(EntityDefinitionContract $def): CreateRecordUseCase
    {
        return new CreateRecordUseCase(
            $this->mutator,
            $this->authorization,
            $def,
            new LocaleAwareValidationWrapper($this->validator, $this->localeProvider, $def, 'create'),
            $this->dispatcher,
        );
    }

    public function updateRecord(EntityDefinitionContract $def): UpdateRecordUseCase
    {
        return new UpdateRecordUseCase(
            $this->mutator,
            $this->authorization,
            $def,
            new LocaleAwareValidationWrapper($this->validator, $this->localeProvider, $def, 'update'),
            $this->dispatcher,
        );
    }

    public function deleteRecord(EntityDefinitionContract $def): DeleteRecordUseCase
    {
        return new DeleteRecordUseCase(
            $this->repository,
            $this->mutator,
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
            createRecord: fn(EntityDefinitionContract $def, EntityRecordContract $rec): EntityRecordContract => $this->createRecord($def)->execute($rec),
            updateRecord: fn(EntityDefinitionContract $def, EntityKey $key, EntityRecordContract $rec): EntityRecordContract => $this->updateRecord($def)->execute($key, $rec),
            resolveDefinition: fn(string $defClass): EntityDefinitionContract => $this->registry->get((new $defClass())->resolveName()),
            validateRecord: function (EntityDefinitionContract $def, array $attrs, array $skip = []): void {
                // LocaleAwareValidationWrapper rebuilds locale-expanded rules from $def.
                // Throws ValidationException on failure — this converts DB 500s into 422s
                // for hasMany/morphMany children that have required fields.
                // $skip contains back-FK field names that the ORM auto-assigns on write;
                // they must be excluded from child validation so the form can omit them.
                (new LocaleAwareValidationWrapper($this->validator, $this->localeProvider, $def, 'create', $skip))
                    ->validate($attrs, []);
            },
        );
    }
}
