<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Concerns;

use BlackParadise\CoreAdmin\Domain\Fields\HasManyField;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use BlackParadise\LaravelAdmin\EntityDefinition;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\OwnedCascade\OcChild;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\OwnedCascade\OcChildDefinition;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Assert;

/**
 * A soft-deletable owned-cascade host that opts into the atomic force-delete trait
 * (`Infrastructure\Persistence\Concerns\AtomicSoftDeletes`, which itself brings SoftDeletes: the host uses ONLY that trait). The class is declared at runtime and
 * only once the trait exists, so a missing trait is a failing assertion and not a fatal error.
 */
trait CreatesAtomicSoftHost
{
    public const ATOMIC_TRAIT = 'BlackParadise\\LaravelAdmin\\Infrastructure\\Persistence\\Concerns\\AtomicSoftDeletes';
    public const ATOMIC_HOST  = 'BlackParadise\\LaravelAdmin\\Tests\\Integration\\Fixtures\\OwnedCascade\\OcAtomicSoftHost';

    /** @return class-string<Model> */
    protected function declareAtomicSoftHost(): string
    {
        Assert::assertTrue(trait_exists(self::ATOMIC_TRAIT), 'AtomicSoftDeletes trait must exist.');

        if (!class_exists(self::ATOMIC_HOST, false)) {
            $childClass = OcChild::class;
            eval(<<<PHP
                namespace BlackParadise\\LaravelAdmin\\Tests\\Integration\\Fixtures\\OwnedCascade;

                final class OcAtomicSoftHost extends \\Illuminate\\Database\\Eloquent\\Model
                {
                    use \\BlackParadise\\LaravelAdmin\\Infrastructure\\Persistence\\Concerns\\AtomicSoftDeletes;

                    protected \$table = 'oc_soft_hosts';
                    protected \$guarded = [];

                    public function runForceDelete(): mixed
                    {
                        return \$this->forceDelete();
                    }

                    public function items(): \\Illuminate\\Database\\Eloquent\\Relations\\HasMany
                    {
                        return \$this->hasMany(\\{$childClass}::class, 'soft_host_id');
                    }
                }
                PHP);
        }

        $class = implode('\\', explode('\\', self::ATOMIC_HOST));
        if (!is_subclass_of($class, Model::class)) {
            Assert::fail('Atomic host fixture was not declared.');
        }

        $definition = new class extends EntityDefinition {
            public function resolveName(): string
            {
                return 'oc_atomic_soft_host';
            }

            public function fields(): array
            {
                return [
                    TextField::make('name'),
                    HasManyField::make('items', OcChild::class)->embed(OcChildDefinition::class)->owns(),
                ];
            }
        };
        $definition->model = $class;
        app()->make(EntityDefinitionRegistry::class)->register($definition);

        return $class;
    }

    /** @param class-string<Model> $class */
    protected function loadAtomicHost(string $class, int $id): Model
    {
        return (new $class())->newQuery()->findOrFail($id);
    }

    /** FQCN of the retired trait, built so static analysis and rector do not treat it as a live class. */
    protected function retiredTraitName(): string
    {
        return substr(self::ATOMIC_TRAIT, 0, -strlen('AtomicSoftDeletes')) . 'AtomicForceDeletes';
    }

    /** Calls a SoftDeletes-provided method (trashed, restore, forceDeleteQuietly, isForceDeleting) on a model. */
    protected static function callSoftDeletesMethod(Model $model, string $method): mixed
    {
        $callable = [$model, $method];
        if (!is_callable($callable)) {
            Assert::fail("Model has no {$method}(): SoftDeletes missing.");
        }

        return $callable();
    }

    /** Calls the (trait-provided) SoftDeletes::forceDelete() on a model. */
    protected function forceDeleteModel(Model $model): mixed
    {
        if (!method_exists($model, 'runForceDelete')) {
            Assert::fail('Not an atomic host fixture.');
        }

        return $model->runForceDelete();
    }
}
