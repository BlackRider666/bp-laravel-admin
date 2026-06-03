<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Infrastructure\Validation;

use BlackParadise\CoreAdmin\Application\UseCases\Entity\RuleBuilder;
use BlackParadise\CoreAdmin\Domain\Contracts\EntityDefinition\EntityDefinitionContract;
use BlackParadise\CoreAdmin\Domain\Contracts\LocaleProviderContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Validation\ValidationProviderContract;

/**
 * Decorator for ValidationProviderContract that rebuilds validation rules
 * using the locale-aware {@see RuleBuilder} instance-API.
 *
 * Purpose: the core use cases (CreateRecordUseCase / UpdateRecordUseCase) build
 * rules via the legacy static {@see RuleBuilder::fromDefinition()} which does
 * NOT expand TranslatableField rules into per-locale dot-notation keys. This
 * wrapper intercepts the validate() call, discards the incoming (locale-unaware)
 * rules, and replaces them with rules rebuilt by `new RuleBuilder($locales)->build()`.
 *
 * Wiring: UseCaseFactory creates one instance per use-case call, binding the
 * concrete EntityDefinitionContract so the wrapper has access to all fields.
 */
final readonly class LocaleAwareValidationWrapper implements ValidationProviderContract
{
    public function __construct(
        private ValidationProviderContract $inner,
        private LocaleProviderContract $localeProvider,
        private EntityDefinitionContract $definition,
    ) {}

    /**
     * Validate $data against locale-expanded rules built from the definition.
     *
     * The $rules parameter is intentionally ignored: it is produced by the
     * legacy static RuleBuilder::fromDefinition() inside the core use case and
     * therefore contains no per-locale expansions for TranslatableFields.
     *
     * Requires bp-admin-core >= 1.0.2 which guarantees the instance-API
     * RuleBuilder::build(). The composer constraint "^1.0.2" enforces this.
     *
     * @param array<string, mixed> $data
     * @param array<string, array<string>> $rules Ignored — superseded by locale-aware rebuild.
     */
    public function validate(array $data, array $rules): void
    {
        // RuleBuilder::build() is the new instance-API (bp-admin-core >= 1.0.2).
        // availableLocales() returns array<string>; RuleBuilder expects list<string>.
        $builder     = new RuleBuilder(array_values($this->localeProvider->availableLocales()));
        $localeRules = $builder->build($this->definition);

        $this->inner->validate($data, $localeRules);
    }
}
