<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Unit\Infrastructure\Validation;

use BlackParadise\CoreAdmin\Domain\Contracts\EntityDefinition\EntityDefinitionContract;
use BlackParadise\CoreAdmin\Domain\Contracts\LocaleProviderContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Validation\ValidationProviderContract;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\CoreAdmin\Domain\Fields\TranslatableField;
use BlackParadise\LaravelAdmin\Infrastructure\Validation\LocaleAwareValidationWrapper;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for LocaleAwareValidationWrapper (Bug #5-wiring fix).
 *
 * Verifies that the wrapper rebuilds rules using the instance RuleBuilder
 * with locales from the LocaleProviderContract, expanding TranslatableField
 * rules into per-locale dot-notation keys.
 */
final class LocaleAwareValidationWrapperTest extends TestCase
{
    public function test_delegates_validate_to_inner_provider(): void
    {
        $inner = $this->createMock(ValidationProviderContract::class);
        $inner->expects($this->once())->method('validate');

        $localeProvider = $this->createStub(LocaleProviderContract::class);
        $localeProvider->method('availableLocales')->willReturn(['en']);

        $definition = $this->createStub(EntityDefinitionContract::class);
        $definition->method('fields')->willReturn([]);

        $wrapper = new LocaleAwareValidationWrapper($inner, $localeProvider, $definition);

        $wrapper->validate(['name' => 'Alice'], []);
    }

    public function test_passes_locale_expanded_rules_when_rulebuilder_supports_build(): void
    {
        $capturedRules = null;

        $inner = $this->createMock(ValidationProviderContract::class);
        $inner->expects($this->once())
            ->method('validate')
            ->willReturnCallback(function (array $data, array $rules) use (&$capturedRules): void {
                $capturedRules = $rules;
            });

        $localeProvider = $this->createStub(LocaleProviderContract::class);
        $localeProvider->method('availableLocales')->willReturn(['en', 'uk']);

        // TranslatableField with required rule
        $titleField = TranslatableField::make('title')->required();

        $definition = $this->createStub(EntityDefinitionContract::class);
        $definition->method('fields')->willReturn([$titleField]);

        $wrapper = new LocaleAwareValidationWrapper($inner, $localeProvider, $definition);

        // Stale rules from legacy fromDefinition() — just 'title' without locale expansion.
        $staleRules = ['title' => ['required']];
        $wrapper->validate(['title' => ['en' => 'Hello', 'uk' => 'Привіт']], $staleRules);

        // RuleBuilder::build() is available (bp-admin-core >= 1.0.2).
        // The wrapper expands TranslatableField rules into per-locale dot-notation keys.
        self::assertArrayHasKey('title.en', $capturedRules ?? []);
        self::assertArrayHasKey('title.uk', $capturedRules ?? []);
        self::assertArrayNotHasKey('title', $capturedRules ?? []);
    }

    public function test_non_translatable_fields_are_not_expanded(): void
    {
        $capturedRules = null;

        $inner = $this->createMock(ValidationProviderContract::class);
        $inner->expects($this->once())
            ->method('validate')
            ->willReturnCallback(function (array $data, array $rules) use (&$capturedRules): void {
                $capturedRules = $rules;
            });

        $localeProvider = $this->createStub(LocaleProviderContract::class);
        $localeProvider->method('availableLocales')->willReturn(['en', 'uk']);

        $nameField = TextField::make('name')->required();

        $definition = $this->createStub(EntityDefinitionContract::class);
        $definition->method('fields')->willReturn([$nameField]);

        $wrapper = new LocaleAwareValidationWrapper($inner, $localeProvider, $definition);

        $wrapper->validate(['name' => 'Alice'], ['name' => ['required']]);

        self::assertArrayHasKey('name', $capturedRules ?? []);
        self::assertArrayNotHasKey('name.en', $capturedRules ?? []);
        self::assertArrayNotHasKey('name.uk', $capturedRules ?? []);
    }
}
