<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Providers;

use BlackParadise\CoreAdmin\Domain\Contracts\Action\ActionResolverContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Entity\RelationOptionsProviderContract;
use BlackParadise\CoreAdmin\Domain\Contracts\EntityDefinition\EntityDefinitionContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Events\EventDispatcherContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Files\FileStorageProviderContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Persistence\EmbeddedChildWriterContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Persistence\OwnedDeletesCollectorContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Persistence\WriteScopeContract;
use BlackParadise\CoreAdmin\Domain\Contracts\TransactionContract;
use BlackParadise\CoreAdmin\Domain\Entity\EntityRecord;
use BlackParadise\CoreAdmin\Domain\Events\EntityDeleted;
use BlackParadise\CoreAdmin\Domain\Mutators\EntityMutatorInterface;
use BlackParadise\CoreAdmin\Domain\Repositories\EntityRepositoryInterface;
use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use BlackParadise\LaravelAdmin\Core\EntityPersistenceResolver;
use BlackParadise\LaravelAdmin\Infrastructure\Actions\ContainerActionResolver;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\EloquentEntityMutator;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\EloquentEntityRepository;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\EloquentRelationOptionsProvider;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\FieldPersisters\ColumnPersister;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\FieldPersisters\FieldPersisterRegistry;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\FieldPersisters\PersistContext;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\LaravelTransaction;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\LaravelWriteScope;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\Query\ColumnQueryStrategy;
use BlackParadise\LaravelAdmin\Infrastructure\Persistence\Query\FieldQueryRegistry;
use BlackParadise\LaravelAdmin\Support\DeferredFileOperations;
use BlackParadise\LaravelAdmin\Support\EmbeddedChildWriter;
use BlackParadise\LaravelAdmin\Support\OwnedDeletesCollector;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Throwable;
use WeakMap;

/**
 * Persistence bindings: repository, mutator, transaction and the (initially fallback-only)
 * persister / query registries. Per-type entries are seeded by {@see FieldTypeServiceProvider}.
 */
final class PersistenceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(EntityRepositoryInterface::class, EloquentEntityRepository::class);
        $this->app->bind(TransactionContract::class, LaravelTransaction::class);
        $this->app->scoped(RelationOptionsProviderContract::class, EloquentRelationOptionsProvider::class);

        $this->app->singleton(
            FieldPersisterRegistry::class,
            static fn($app): FieldPersisterRegistry => new FieldPersisterRegistry($app->make(ColumnPersister::class)),
        );
        $this->app->singleton(
            FieldQueryRegistry::class,
            static fn($app): FieldQueryRegistry => new FieldQueryRegistry($app->make(ColumnQueryStrategy::class)),
        );
        $this->app->bind(EntityMutatorInterface::class, EloquentEntityMutator::class);
        // DeferredFileOperations: one instance per request so the mutator and
        // controller share the same collector within a single HTTP request.
        $this->app->scoped(DeferredFileOperations::class);
        // One write scope per request: tracks nesting depth, so it must be shared.
        $this->app->scoped(WriteScopeContract::class, LaravelWriteScope::class);
        $this->app->bind(EmbeddedChildWriterContract::class, EmbeddedChildWriter::class);
        $this->app->bind(OwnedDeletesCollectorContract::class, OwnedDeletesCollector::class);
        $this->app->bind(ActionResolverContract::class, ContainerActionResolver::class);
        // Per-definition repository()/mutator() selection (global bindings above are the default).
        $this->app->bind(EntityPersistenceResolver::class);
    }

    public function boot(): void
    {
        $this->registerForceDeletedFileCleanup();
    }

    /**
     * Register global Eloquent listeners that run every field's persister
     * `beforeDelete()` hook (force context) when a soft-deletable model is
     * permanently removed (prune / forceDelete, also outside the admin).
     *
     *  - `forceDeleting` (host row still present): DB cascade (pivot detach, owned
     *    rows) and collection of file paths, so FK constraints without ON DELETE
     *    CASCADE are satisfied.
     *  - `forceDeleted`: removes the collected files from each field's disk. Inside
     *    an outer DB transaction the removal waits for the commit, so a rollback
     *    keeps both the row and its files.
     *
     * File cleanup is best-effort: failures are reported, never rethrown.
     */
    private function registerForceDeletedFileCleanup(): void
    {
        /** @var WeakMap<Model, DeferredFileOperations> $pending */
        $pending = new WeakMap();

        Event::listen('eloquent.forceDeleting: *', function (string $event, array $payload) use ($pending): void {
            $model = $payload[0] ?? null;
            if (!$model instanceof Model
                || !in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
                return;
            }

            $registry = $this->app->make(FieldPersisterRegistry::class);
            $files    = new DeferredFileOperations($this->app->make(FileStorageProviderContract::class));

            foreach ($this->app->make(EntityDefinitionRegistry::class)->all() as $definition) {
                if ($definition->modelClass() !== $model::class) {
                    continue;
                }
                $context = new PersistContext($definition, [], $files, $model, true, true);
                foreach ($definition->fields() as $field) {
                    $registry->for($field)->beforeDelete($field, $model, $context);
                }
                $pending[$model] = $files;

                return;
            }
        });

        // The owned-children cascade runs in `deleting` (after every `forceDeleting` listener).
        // Listeners never open or close a transaction: atomicity comes from an ambient
        // transaction (the admin path always has one) or from the opt-in AtomicSoftDeletes
        // trait. Outside both, the cascade is not atomic by contract.
        Event::listen('eloquent.deleting: *', function (string $event, array $payload) use ($pending): void {
            $model = $payload[0] ?? null;
            if (!$model instanceof Model
                || !isset($pending[$model])
                || !method_exists($model, 'isForceDeleting')
                || !$model->isForceDeleting()) {
                return;
            }

            foreach ($this->app->make(EntityDefinitionRegistry::class)->all() as $definition) {
                if ($definition->modelClass() !== $model::class) {
                    continue;
                }

                try {
                    $this->cascadeOwnedChildren($definition, $model, $pending[$model]);
                } catch (Throwable $e) {
                    unset($pending[$model]);

                    throw $e;
                }

                return;
            }
        });

        Event::listen('eloquent.forceDeleted: *', function (string $event, array $payload) use ($pending): void {
            $model = $payload[0] ?? null;
            if (!$model instanceof Model || !isset($pending[$model])) {
                return;
            }
            $files = $pending[$model];
            unset($pending[$model]);

            $flush = static function () use ($files): void {
                try {
                    $files->commit();
                } catch (Throwable $e) {
                    report($e);
                }
            };

            $connection = $model->getConnection();
            if ($connection->transactionLevel() > 0) {
                $connection->afterCommit($flush);
            } else {
                $flush();
            }
        });
    }

    /**
     * A55: a permanent (force) delete of a soft-deletable host removes its owned
     * hasMany/morphMany children through the (decorated) mutator, running their
     * persisters (files, pivots) and recording one EntityDeleted per child. The host row
     * is still present here, so child rows never hit a foreign key on the host.
     *
     * Atomicity requires the children and the host to share the host's connection transaction
     * (ambient transaction or the AtomicSoftDeletes trait, used instead of SoftDeletes); children on another connection
     * are not covered by it.
     */
    private function cascadeOwnedChildren(EntityDefinitionContract $definition, Model $model, DeferredFileOperations $hostFiles): void
    {
        $children = $this->app->make(OwnedDeletesCollector::class)->collectForForceDelete(
            $definition,
            new EntityRecord($definition, [$definition->keyField() => $model->getKey()]),
        );
        if ($children === []) {
            return;
        }

        $mutator    = $this->app->make(EntityMutatorInterface::class);
        $dispatcher = $this->app->make(EventDispatcherContract::class);

        // No commit scope of its own: the caller owns the transaction, the children's file
        // deletions join the host's deferred set and run only after the real commit.
        $shared = $this->app->make(DeferredFileOperations::class);
        $mark   = $shared->mark();
        $shared->beginOuterScope();

        try {
            foreach ($children as $child) {
                if ($mutator->delete($child['key'], $child['def'])) {
                    $dispatcher->dispatch(new EntityDeleted($child['key']));
                }
            }
        } catch (Throwable $e) {
            $shared->rollbackTo($mark);

            throw $e;
        } finally {
            $shared->endOuterScope();
        }

        $hostFiles->absorb($shared->takeSince($mark));
    }
}
