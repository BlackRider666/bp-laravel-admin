<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Unit\Core;

use BlackParadise\CoreAdmin\Application\UseCases\Entity\BuildFormViewUseCase;
use BlackParadise\CoreAdmin\Application\UseCases\Entity\CreateRecordUseCase;
use BlackParadise\CoreAdmin\Application\UseCases\Entity\DeleteRecordUseCase;
use BlackParadise\CoreAdmin\Application\UseCases\Entity\FindRecordUseCase;
use BlackParadise\CoreAdmin\Application\UseCases\Entity\ListRecordsUseCase;
use BlackParadise\CoreAdmin\Application\UseCases\Entity\UpdateRecordUseCase;
use BlackParadise\CoreAdmin\Domain\Contracts\Auth\AuthorizationProviderContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Entity\RelationOptionsProviderContract;
use BlackParadise\CoreAdmin\Domain\Contracts\EntityDefinition\EntityDefinitionContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Events\EventDispatcherContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Validation\ValidationProviderContract;
use BlackParadise\CoreAdmin\Domain\Mutators\EntityMutatorInterface;
use BlackParadise\CoreAdmin\Domain\Repositories\EntityRepositoryInterface;
use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use BlackParadise\LaravelAdmin\Core\UseCaseFactory;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for UseCaseFactory.
 *
 * Verifies that each factory method instantiates the correct use case type
 * while injecting the shared dependencies supplied to the factory.
 * All dependencies are stubs — no behaviour assertions needed here.
 */
final class UseCaseFactoryTest extends TestCase
{
    private UseCaseFactory $factory;
    private EntityRepositoryInterface $repository;
    private EntityMutatorInterface $mutator;
    private AuthorizationProviderContract $authorization;
    private ValidationProviderContract $validator;
    private EventDispatcherContract $dispatcher;
    private EntityDefinitionContract $definition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository    = $this->createStub(EntityRepositoryInterface::class);
        $this->mutator       = $this->createStub(EntityMutatorInterface::class);
        $this->authorization = $this->createStub(AuthorizationProviderContract::class);
        $this->validator     = $this->createStub(ValidationProviderContract::class);
        $this->dispatcher    = $this->createStub(EventDispatcherContract::class);
        $this->definition    = $this->createStub(EntityDefinitionContract::class);

        $this->factory = new UseCaseFactory(
            repository: $this->repository,
            mutator: $this->mutator,
            authorization: $this->authorization,
            validator: $this->validator,
            dispatcher: $this->dispatcher,
            registry: new EntityDefinitionRegistry(),
            relationOptions: $this->createStub(RelationOptionsProviderContract::class),
        );
    }

    public function test_list_records_returns_list_records_use_case(): void
    {
        $useCase = $this->factory->listRecords($this->definition);

        self::assertInstanceOf(ListRecordsUseCase::class, $useCase);
    }

    public function test_find_record_returns_find_record_use_case(): void
    {
        $useCase = $this->factory->findRecord($this->definition);

        self::assertInstanceOf(FindRecordUseCase::class, $useCase);
    }

    public function test_create_record_returns_create_record_use_case(): void
    {
        $useCase = $this->factory->createRecord($this->definition);

        self::assertInstanceOf(CreateRecordUseCase::class, $useCase);
    }

    public function test_update_record_returns_update_record_use_case(): void
    {
        $useCase = $this->factory->updateRecord($this->definition);

        self::assertInstanceOf(UpdateRecordUseCase::class, $useCase);
    }

    public function test_delete_record_returns_delete_record_use_case(): void
    {
        $useCase = $this->factory->deleteRecord($this->definition);

        self::assertInstanceOf(DeleteRecordUseCase::class, $useCase);
    }

    public function test_build_form_view_returns_build_form_view_use_case(): void
    {
        $useCase = $this->factory->buildFormView($this->definition);

        self::assertInstanceOf(BuildFormViewUseCase::class, $useCase);
    }

    public function test_factory_creates_distinct_instances_per_call(): void
    {
        $first  = $this->factory->listRecords($this->definition);
        $second = $this->factory->listRecords($this->definition);

        self::assertNotSame($first, $second);
    }

    public function test_factory_uses_different_definitions_per_call(): void
    {
        $definitionA = $this->createStub(EntityDefinitionContract::class);
        $definitionB = $this->createStub(EntityDefinitionContract::class);

        $useCaseA = $this->factory->listRecords($definitionA);
        $useCaseB = $this->factory->listRecords($definitionB);

        self::assertInstanceOf(ListRecordsUseCase::class, $useCaseA);
        self::assertInstanceOf(ListRecordsUseCase::class, $useCaseB);
        self::assertNotSame($useCaseA, $useCaseB);
    }
}
