<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Unit\Infrastructure\Validation;

use BlackParadise\CoreAdmin\Domain\Validation\ParameterizedRule;
use BlackParadise\CoreAdmin\Domain\Validation\Rule;
use BlackParadise\CoreAdmin\Domain\Validation\RuleSet;
use BlackParadise\LaravelAdmin\Infrastructure\Validation\RuleSetToLaravelConverter;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for RuleSetToLaravelConverter.
 *
 * Verifies that every RuleSet variant is correctly serialised to a flat
 * string array compatible with Laravel's Validator::make() rules format.
 */
final class RuleSetToLaravelConverterTest extends TestCase
{
    // ------------------------------------------------------------------
    // Empty set
    // ------------------------------------------------------------------

    public function test_convert_empty_rule_set_returns_empty_array(): void
    {
        $ruleSet = new RuleSet();

        $result = RuleSetToLaravelConverter::convert($ruleSet);

        self::assertSame([], $result);
    }

    // ------------------------------------------------------------------
    // Simple (non-parameterized) rules
    // ------------------------------------------------------------------

    public function test_convert_single_simple_rule_returns_its_value(): void
    {
        $ruleSet = new RuleSet([Rule::Required]);

        $result = RuleSetToLaravelConverter::convert($ruleSet);

        self::assertSame(['required'], $result);
    }

    public function test_convert_multiple_simple_rules_preserves_order(): void
    {
        $ruleSet = new RuleSet([Rule::Required, Rule::String, Rule::Email]);

        $result = RuleSetToLaravelConverter::convert($ruleSet);

        self::assertSame(['required', 'string', 'email'], $result);
    }

    public function test_convert_nullable_rule_produces_nullable_string(): void
    {
        $ruleSet = new RuleSet([Rule::Nullable]);

        $result = RuleSetToLaravelConverter::convert($ruleSet);

        self::assertSame(['nullable'], $result);
    }

    // ------------------------------------------------------------------
    // Parameterized rules
    // ------------------------------------------------------------------

    public function test_convert_parameterized_rule_with_scalar_value_uses_colon_syntax(): void
    {
        $ruleSet = new RuleSet([new ParameterizedRule('max', '255')]);

        $result = RuleSetToLaravelConverter::convert($ruleSet);

        self::assertSame(['max:255'], $result);
    }

    public function test_convert_parameterized_rule_with_numeric_string_value(): void
    {
        $ruleSet = new RuleSet([new ParameterizedRule('min', '1')]);

        $result = RuleSetToLaravelConverter::convert($ruleSet);

        self::assertSame(['min:1'], $result);
    }

    public function test_convert_parameterized_rule_with_array_value_joins_with_comma(): void
    {
        $ruleSet = new RuleSet([new ParameterizedRule('in', ['active', 'inactive', 'pending'])]);

        $result = RuleSetToLaravelConverter::convert($ruleSet);

        self::assertSame(['in:active,inactive,pending'], $result);
    }

    public function test_convert_parameterized_exists_rule(): void
    {
        $ruleSet = new RuleSet([new ParameterizedRule('exists', 'users,id')]);

        $result = RuleSetToLaravelConverter::convert($ruleSet);

        self::assertSame(['exists:users,id'], $result);
    }

    // ------------------------------------------------------------------
    // Mixed rule sets
    // ------------------------------------------------------------------

    public function test_convert_mixed_simple_and_parameterized_rules(): void
    {
        $ruleSet = new RuleSet([
            Rule::Required,
            Rule::String,
            new ParameterizedRule('max', '100'),
        ]);

        $result = RuleSetToLaravelConverter::convert($ruleSet);

        self::assertSame(['required', 'string', 'max:100'], $result);
    }

    public function test_convert_nullable_with_parameterized_rules(): void
    {
        $ruleSet = new RuleSet([
            Rule::Nullable,
            Rule::String,
            new ParameterizedRule('max', '500'),
        ]);

        $result = RuleSetToLaravelConverter::convert($ruleSet);

        self::assertSame(['nullable', 'string', 'max:500'], $result);
    }

    public function test_convert_returns_array_of_strings(): void
    {
        $ruleSet = new RuleSet([
            Rule::Required,
            new ParameterizedRule('between', '1,99'),
        ]);

        $result = RuleSetToLaravelConverter::convert($ruleSet);

        foreach ($result as $rule) {
            self::assertIsString($rule);
        }
    }

    // ------------------------------------------------------------------
    // Idempotency — calling convert twice gives the same result
    // ------------------------------------------------------------------

    public function test_convert_is_idempotent(): void
    {
        $ruleSet = new RuleSet([Rule::Required, new ParameterizedRule('max', '255')]);

        $first  = RuleSetToLaravelConverter::convert($ruleSet);
        $second = RuleSetToLaravelConverter::convert($ruleSet);

        self::assertSame($first, $second);
    }
}
