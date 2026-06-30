<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Unit\Core;

use BlackParadise\CoreAdmin\Application\UseCases\Entity\CreateRecordUseCase;
use BlackParadise\CoreAdmin\Application\UseCases\Entity\UpdateRecordUseCase;
use BlackParadise\CoreAdmin\Domain\Contracts\Auth\AuthorizationProviderContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Entity\RelationOptionsProviderContract;
use BlackParadise\CoreAdmin\Domain\Contracts\EntityDefinition\EntityDefinitionContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Events\EventDispatcherContract;
use BlackParadise\CoreAdmin\Domain\Contracts\LocaleProviderContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Validation\ValidationProviderContract;
use BlackParadise\CoreAdmin\Domain\Mutators\EntityMutatorInterface;
use BlackParadise\CoreAdmin\Domain\Repositories\EntityRepositoryInterface;
use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use BlackParadise\LaravelAdmin\Core\UseCaseFactory;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * RED tests (Task 1.3) — wiring of LocaleProviderContract into use cases.
 *
 * Pins that after Task 1.4, UseCaseFactory::createRecord() / updateRecord()
 * pass LocaleProviderContract as the 6th constructor argument to the core use
 * cases and wire the raw validator directly (no validation wrapper).
 *
 * Pre-IMPL failure: UseCaseFactory passes 5 args to the now-6-arg CreateRecordUseCase /
 * UpdateRecordUseCase constructors → ArgumentCountError → ERROR → RED.
 *
 * Post-IMPL: no error, and the 'validator' property on the produced use case is
 * the exact validator the factory was given (wired directly, not wrapped).
 */
final class UseCaseFactoryLocaleWiringTest extends TestCase
{
    private UseCaseFactory $factory;

    private ValidationProviderContract $validator;

    private LocaleProviderContract $localeProvider;

    private EntityDefinitionContract $definition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator      = $this->createStub(ValidationProviderContract::class);
        $this->localeProvider = $this->createStub(LocaleProviderContract::class);
        $this->localeProvider->method('availableLocales')->willReturn(['en']);

        $this->definition = $this->createStub(EntityDefinitionContract::class);
        $this->definition->method('fields')->willReturn([]);

        $this->factory = new UseCaseFactory(
            repository: $this->createStub(EntityRepositoryInterface::class),
            mutator: $this->createStub(EntityMutatorInterface::class),
            authorization: $this->createStub(AuthorizationProviderContract::class),
            validator: $this->validator,
            dispatcher: $this->createStub(EventDispatcherContract::class),
            registry: new EntityDefinitionRegistry(),
            relationOptions: $this->createStub(RelationOptionsProviderContract::class),
            localeProvider: $this->localeProvider,
        );
    }

    /**
     * After Task 1.4, createRecord() must not throw ArgumentCountError and must
     * wire the raw validator directly (no validation wrapper) into the use case.
     *
     * Pre-IMPL: createRecord() passes 5 args to 6-arg ctor → ArgumentCountError → ERROR → RED.
     */
    public function test_create_record_does_not_wrap_validator(): void
    {
        $useCase = $this->factory->createRecord($this->definition);

        self::assertInstanceOf(CreateRecordUseCase::class, $useCase);

        // The validator wired into the use case must be the exact provider the
        // factory was given — i.e. wired directly, not wrapped.
        $reflection   = new ReflectionClass($useCase);
        $validatorProp = $reflection->getProperty('validator');

        self::assertSame($this->validator, $validatorProp->getValue($useCase));
    }

    /**
     * After Task 1.4, updateRecord() must wire the raw validator directly too.
     *
     * Pre-IMPL: updateRecord() passes 5 args to 6-arg ctor → ArgumentCountError → ERROR → RED.
     */
    public function test_update_record_does_not_wrap_validator(): void
    {
        $useCase = $this->factory->updateRecord($this->definition);

        self::assertInstanceOf(UpdateRecordUseCase::class, $useCase);

        $reflection    = new ReflectionClass($useCase);
        $validatorProp = $reflection->getProperty('validator');

        self::assertSame($this->validator, $validatorProp->getValue($useCase));
    }

    /**
     * After Task 1.4, the LocaleProviderContract passed to the factory must be
     * wired as the 6th arg of CreateRecordUseCase, enabling locale-aware rule expansion.
     *
     * Pre-IMPL: ArgumentCountError → ERROR → RED.
     */
    public function test_create_record_receives_the_same_locale_provider_as_factory(): void
    {
        $useCase = $this->factory->createRecord($this->definition);

        $reflection       = new ReflectionClass($useCase);
        $localeProp       = $reflection->getProperty('localeProvider');

        self::assertSame($this->localeProvider, $localeProp->getValue($useCase));
    }

    /**
     * After Task 1.4, the LocaleProviderContract passed to the factory must be
     * wired as the 6th arg of UpdateRecordUseCase.
     *
     * Pre-IMPL: ArgumentCountError → ERROR → RED.
     */
    public function test_update_record_receives_the_same_locale_provider_as_factory(): void
    {
        $useCase = $this->factory->updateRecord($this->definition);

        $reflection  = new ReflectionClass($useCase);
        $localeProp  = $reflection->getProperty('localeProvider');

        self::assertSame($this->localeProvider, $localeProp->getValue($useCase));
    }
}
