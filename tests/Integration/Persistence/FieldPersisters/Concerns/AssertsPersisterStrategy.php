<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Persistence\FieldPersisters\Concerns;

use BlackParadise\CoreAdmin\Domain\Contracts\Fields\FieldContract;
use ReflectionMethod;

/**
 * Shared shape assertions for one FieldPersister strategy (Task 2.1, A18/A19).
 * Behavioural parity (DB/disk state) is pinned by the Characterization suite.
 *
 * Class names are built from strings on purpose: the classes do not exist before Task 2.2
 * and tests must not fatal / fail static analysis because of that.
 */
trait AssertsPersisterStrategy
{
    protected static function persisterClass(string $shortName): string
    {
        return 'BlackParadise\\LaravelAdmin\\Infrastructure\\Persistence\\FieldPersisters\\' . $shortName;
    }

    protected function assertStrategyClassShape(string $shortName): void
    {
        $class = self::persisterClass($shortName);
        self::assertTrue(class_exists($class), $class . ' must exist');
        self::assertContains(self::persisterClass('FieldPersisterContract'), class_implements($class));
        self::assertTrue(is_subclass_of($class, self::persisterClass('AbstractFieldPersister')), $class . ' must extend AbstractFieldPersister');
    }

    protected function assertStrategyHooks(string $shortName): void
    {
        $class = self::persisterClass($shortName);
        self::assertTrue(class_exists($class), $class . ' must exist');
        foreach (['toAttributes' => 3, 'afterSave' => 4, 'beforeDelete' => 3] as $hook => $arity) {
            self::assertTrue(method_exists($class, $hook), $class . '::' . $hook . ' must exist');
            self::assertSame($arity, (new ReflectionMethod($class, $hook))->getNumberOfParameters(), $hook . ' arity');
        }
    }

    protected function assertRegistryResolves(FieldContract $field, string $shortName): void
    {
        $registryClass = self::persisterClass('FieldPersisterRegistry');
        self::assertTrue(class_exists($registryClass), 'FieldPersisterRegistry must exist');
        $registry = app()->make($registryClass);

        self::assertSame(self::persisterClass($shortName), get_debug_type($registry->for($field)));
    }
}
