<?php

declare(strict_types=1);

use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\EntityDefinition;

// ---------------------------------------------------------------------------
// Probe classes (safe to declare now that fields() is no longer abstract)
// ---------------------------------------------------------------------------

final class MemoProbeDefinition extends EntityDefinition
{
    public string $model = stdClass::class;
    public int $buildCount = 0;

    protected function defineFields(): array
    {
        $this->buildCount++;
        return [TextField::make('name')];
    }
}

final class LegacyProbeDefinition extends EntityDefinition
{
    public string $model = stdClass::class;

    public function fields(): array
    {
        return [TextField::make('title')];
    }
}

// ---------------------------------------------------------------------------
// Structural / contract tests (RED before impl, GREEN after)
// ---------------------------------------------------------------------------

test('fields() is no longer abstract on the base definition', function (): void {
    expect((new ReflectionMethod(EntityDefinition::class, 'fields'))->isAbstract())->toBeFalse();
});

test('base definition exposes a defineFields() extension point', function (): void {
    $method = new ReflectionMethod(EntityDefinition::class, 'defineFields');
    expect($method->isProtected())->toBeTrue();
});

// ---------------------------------------------------------------------------
// Behavioural tests (memoization + legacy compatibility)
// ---------------------------------------------------------------------------

test('fields() memoizes defineFields() — builds once, returns same array', function (): void {
    $definition = new MemoProbeDefinition();

    $first  = $definition->fields();
    $second = $definition->fields();

    expect($definition->buildCount)->toBe(1)
        ->and($second)->toBe($first)
        ->and($first[0]->name())->toBe('name');
});

test('legacy definitions overriding fields() directly still work', function (): void {
    $definition = new LegacyProbeDefinition();

    expect($definition->fields()[0]->name())->toBe('title');
});

test('resolveName() computes preg_replace only once', function (): void {
    $definition = new MemoProbeDefinition();

    expect($definition->resolveName())->toBe('memo_probe_definition')
        ->and($definition->resolveName())->toBe('memo_probe_definition');

    $ref = new ReflectionProperty(EntityDefinition::class, 'resolvedNameCache');
    expect($ref->getValue($definition))->toBe('memo_probe_definition');
});
