<?php

declare(strict_types=1);

use BlackParadise\LaravelAdmin\Console\InstallCommand;

/**
 * Unit tests for InstallCommand scaffold generation.
 *
 * Verifies that the generated EntityDefinition stub emits the correct
 * defineFields() convention (Task 3: defineFields() migration).
 *
 * buildStub() is private — accessed via Reflection to test the stub
 * content in isolation, without booting Laravel or writing files.
 */

test('generated stub contains protected defineFields() signature', function (): void {
    $command = new InstallCommand();

    $stub = callBuildStub($command, 'Post', 'App\\Models\\Post');

    expect($stub)->toContain('protected function defineFields(): array');
});

test('generated stub does NOT contain public fields() signature', function (): void {
    $command = new InstallCommand();

    $stub = callBuildStub($command, 'Post', 'App\\Models\\Post');

    expect($stub)->not->toContain('public function fields(): array');
});

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function callBuildStub(InstallCommand $command, string $className, string $modelClass): string
{
    $method = new ReflectionMethod($command, 'buildStub');

    return (string) $method->invoke($command, $className, $modelClass);
}
