<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Infrastructure\Persistence\FieldPersisters;

use BlackParadise\CoreAdmin\Domain\Contracts\EntityDefinition\EntityDefinitionContract;
use BlackParadise\LaravelAdmin\Exceptions\ModelLessDefinitionException;
use BlackParadise\LaravelAdmin\Support\DeferredFileOperations;
use Illuminate\Database\Eloquent\Model;

/**
 * Immutable per-write state handed to every {@see FieldPersisterContract} hook.
 */
final class PersistContext
{
    private ?Model $hostPrototype = null;

    /**
     * @param array<string, mixed> $attributes Raw write attributes (incl. `{field}__clear` flags).
     * @param Model|null $existing Host being updated / deleted; null on create.
     */
    public function __construct(
        public readonly EntityDefinitionContract $definition,
        public readonly array $attributes,
        public readonly DeferredFileOperations $files,
        public readonly ?Model $existing = null,
        public readonly bool $forceDelete = false,
        public readonly bool $softDeletable = false,
    ) {}

    public function isCreate(): bool
    {
        return !$this->existing instanceof Model;
    }

    public function isForceDelete(): bool
    {
        return $this->forceDelete;
    }

    public function isSoftDeletable(): bool
    {
        return $this->softDeletable;
    }

    /** True when deleting the host physically removes its row (cascade is allowed). */
    public function cascades(): bool
    {
        return $this->forceDelete || !$this->softDeletable;
    }

    /** Un-persisted host model instance (cast / attribute introspection). */
    public function hostPrototype(): Model
    {
        return $this->existing ?? $this->hostPrototype ??= resolve($this->definition->modelClass() ?? throw ModelLessDefinitionException::forDefinition($this->definition));
    }
}
