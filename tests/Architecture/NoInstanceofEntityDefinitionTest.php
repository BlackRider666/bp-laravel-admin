<?php

declare(strict_types=1);

/*
 * A31/Task 4.5: shared behaviour (isCreatable / filters / name resolution) lives on
 * EntityDefinitionContract, so production code must never branch on the concrete
 * BlackParadise\LaravelAdmin\EntityDefinition base class.
 */

test('src has no instanceof EntityDefinition checks', function (): void {
    $offenders = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/src'));

    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo) {
            continue;
        }
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $code = (string) file_get_contents($file->getPathname());
        // Concrete base class only: `instanceof EntityDefinition` / `instanceof \...\LaravelAdmin\EntityDefinition`,
        // not `EntityDefinitionContract` / `EntityDefinitionRegistry`.
        if (preg_match('/instanceof\s+\\\\?(?:\w+\\\\)*EntityDefinition(?!\w)/', $code) === 1) {
            $offenders[] = substr($file->getPathname(), strlen(dirname(__DIR__, 2)) + 1);
        }
    }

    expect($offenders)->toBe([]);
});
