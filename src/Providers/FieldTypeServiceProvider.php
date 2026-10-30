<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Providers;

use BlackParadise\CoreAdmin\Domain\Contracts\LocaleProviderContract;
use BlackParadise\CoreAdmin\Domain\Fields\Base\AbstractField;
use BlackParadise\CoreAdmin\Domain\Fields\BelongsToField;
use BlackParadise\CoreAdmin\Domain\Fields\BelongsToManyField;
use BlackParadise\CoreAdmin\Domain\Fields\BooleanField;
use BlackParadise\CoreAdmin\Domain\Fields\DateField;
use BlackParadise\CoreAdmin\Domain\Fields\DateTimeField;
use BlackParadise\CoreAdmin\Domain\Fields\EditorField;
use BlackParadise\CoreAdmin\Domain\Fields\EnumField;
use BlackParadise\CoreAdmin\Domain\Fields\FileField;
use BlackParadise\CoreAdmin\Domain\Fields\HashedField;
use BlackParadise\CoreAdmin\Domain\Fields\HasManyField;
use BlackParadise\CoreAdmin\Domain\Fields\HasOneField;
use BlackParadise\CoreAdmin\Domain\Fields\HiddenField;
use BlackParadise\CoreAdmin\Domain\Fields\ImageField;
use BlackParadise\CoreAdmin\Domain\Fields\MorphFileField;
use BlackParadise\CoreAdmin\Domain\Fields\MorphManyField;
use BlackParadise\CoreAdmin\Domain\Fields\MorphToField;
use BlackParadise\CoreAdmin\Domain\Fields\NumberField;
use BlackParadise\CoreAdmin\Domain\Fields\PhoneField;
use BlackParadise\CoreAdmin\Domain\Fields\RelationPathField;
use BlackParadise\CoreAdmin\Domain\Fields\TextareaField;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\CoreAdmin\Domain\Fields\TranslatableField;
use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use BlackParadise\LaravelAdmin\Core\FieldTypeRegistry;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\FieldPersisters\AbstractChildSyncPersister;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\FieldPersisters\BelongsToManyPersister;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\FieldPersisters\ColumnFilePersister;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\FieldPersisters\ColumnPersister;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\FieldPersisters\FieldPersisterRegistry;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\FieldPersisters\HashedPersister;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\FieldPersisters\HasManyPersister;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\FieldPersisters\HasOnePersister;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\FieldPersisters\MorphFilePersister;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\FieldPersisters\MorphManyPersister;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\FieldPersisters\MorphToPersister;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\FieldPersisters\TranslatablePersister;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\Query\FieldQueryRegistry;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\Query\NotQueryableStrategy;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\Query\RelationPathQueryStrategy;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\Query\TranslatableQueryStrategy;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the FieldTypeRegistry and seeds it, together with the persister and query
 * registries, from a single defaults table: type => [fieldClass, persister, queryStrategy].
 */
final class FieldTypeServiceProvider extends ServiceProvider
{
    /**
     * Persister / query entries are applied only when non-null (otherwise the registry fallback is used).
     *
     * @return array<string, array{class-string<AbstractField>, class-string|null, class-string|null}>
     */
    private function defaults(): array
    {
        return [
            'text'            => [TextField::class, ColumnPersister::class, null],
            'number'          => [NumberField::class, null, null],
            'boolean'         => [BooleanField::class, null, null],
            'datetime'        => [DateTimeField::class, null, null],
            'email'           => [TextField::class, null, null],
            'image'           => [ImageField::class, ColumnFilePersister::class, null],
            'file'            => [FileField::class, ColumnFilePersister::class, null],
            'belongs_to'      => [BelongsToField::class, null, null],
            'belongs_to_many' => [BelongsToManyField::class, BelongsToManyPersister::class, NotQueryableStrategy::class],
            'has_many'        => [HasManyField::class, HasManyPersister::class, NotQueryableStrategy::class],
            'has_one'         => [HasOneField::class, HasOnePersister::class, NotQueryableStrategy::class],
            'morph_to'        => [MorphToField::class, MorphToPersister::class, RelationPathQueryStrategy::class],
            'morph_many'      => [MorphManyField::class, MorphManyPersister::class, NotQueryableStrategy::class],
            'morph_file'      => [MorphFileField::class, MorphFilePersister::class, RelationPathQueryStrategy::class],
            'enum'            => [EnumField::class, null, null],
            'editor'          => [EditorField::class, null, null],
            'hashed'          => [HashedField::class, HashedPersister::class, null],
            'textarea'        => [TextareaField::class, null, null],
            'date'            => [DateField::class, null, null],
            'phone'           => [PhoneField::class, null, null],
            'hidden'          => [HiddenField::class, null, null],
            'translatable'    => [TranslatableField::class, TranslatablePersister::class, TranslatableQueryStrategy::class],
            'relation_path'   => [RelationPathField::class, null, RelationPathQueryStrategy::class],
        ];
    }

    public function register(): void
    {
        // Child-sync persisters take the definition registry plus a lazy FieldPersisterRegistry
        // factory (the registry holds these persisters, so an eager dependency would be circular).
        foreach ([HasManyPersister::class, MorphManyPersister::class] as $persister) {
            $this->app->bind($persister, static fn($app): AbstractChildSyncPersister => new $persister(
                $app->make(EntityDefinitionRegistry::class),
                static fn(): FieldPersisterRegistry => $app->make(FieldPersisterRegistry::class),
            ));
        }

        $this->app->singleton(FieldTypeRegistry::class, function ($app): FieldTypeRegistry {
            $registry = new FieldTypeRegistry(
                $app->make(FieldPersisterRegistry::class),
                $app->make(FieldQueryRegistry::class),
            );
            // Only registers a type when the target class actually exists,
            // so the package is safe to use without bp-admin-core field classes present.
            foreach ($this->defaults() as $type => [$fieldClass]) {
                if (class_exists($fieldClass)) {
                    $registry->register($type, $fieldClass);
                }
            }

            return $registry;
        });

        // Seeded on first resolution so the registries are complete even when resolved
        // without going through FieldTypeRegistry.
        $this->app->extend(FieldPersisterRegistry::class, function (FieldPersisterRegistry $registry, $app): FieldPersisterRegistry {
            foreach ($this->defaults() as $type => [, $persister]) {
                if ($persister !== null) {
                    $registry->register($type, $app->make($persister));
                }
            }

            return $registry;
        });
        $this->app->extend(FieldQueryRegistry::class, function (FieldQueryRegistry $registry, $app): FieldQueryRegistry {
            foreach ($this->defaults() as $type => [, , $query]) {
                if ($query === TranslatableQueryStrategy::class) {
                    $registry->register($type, new TranslatableQueryStrategy(
                        static fn(): LocaleProviderContract => $app->make(LocaleProviderContract::class),
                    ));
                } elseif ($query !== null) {
                    $registry->register($type, $app->make($query));
                }
            }

            return $registry;
        });
    }
}
