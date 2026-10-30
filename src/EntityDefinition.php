<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin;

use BlackParadise\CoreAdmin\Domain\Contracts\Action\ActionContract;
use BlackParadise\CoreAdmin\Domain\Contracts\EntityDefinition\EntityDefinitionContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Fields\FieldContract;
use BlackParadise\CoreAdmin\Domain\Fields\Base\AbstractField;
use BlackParadise\CoreAdmin\Domain\ValueObjects\Capability;
use BlackParadise\CoreAdmin\Domain\ValueObjects\EntityCapabilities;
use Illuminate\Support\Str;
use LogicException;

/**
 * Abstract base class for all BPAdmin entity definitions.
 *
 * Developers extend this class in their application (e.g. App\BPAdmin\Users)
 * to configure how an entity is displayed and managed in the admin panel.
 *
 * The entity name is automatically derived from the class name using snake_case:
 * Users → users, OrderItems → order_items.
 *
 * Implement {@see defineFields()} to return the list of field definitions for this entity.
 */
abstract class EntityDefinition implements EntityDefinitionContract
{
    /**
     * Fully-qualified Eloquent model class. Left unset by model-less definitions
     * (custom {@see repository()} / {@see mutator()}, no Eloquent model).
     *
     * @var class-string
     */
    public string $model;

    /**
     * The primary key field name on the model.
     */
    public string $primaryKey = 'id';

    /**
     * The primary key type ('int' or 'string').
     */
    public string $primaryKeyType = 'int';

    /**
     * Default number of records per paginated page.
     */
    public int $perPage = 15;

    /**
     * Whether records of this entity can be created directly through the admin
     * panel. Set to false for embed-only definitions (registered solely as
     * embed targets) so their create form and store endpoint are not directly
     * accessible — see {@see isCreatable()}. Capabilities (including this flag) govern
     * the standalone surfaces only; embedded children are governed by authorization.
     */
    public bool $creatable = true;

    /** @var array<FieldContract>|null */
    private ?array $cachedFields = null;

    private ?string $resolvedNameCache = null;

    private ?string $labelCache = null;

    /**
     * Derive the entity name from the class name.
     * Users → users, OrderItems → order_items.
     *
     * Implemented in pure PHP to avoid an unnecessary Str::snake() call
     * for this single hot-path operation.
     */
    public function resolveName(): string
    {
        if ($this->resolvedNameCache !== null) {
            return $this->resolvedNameCache;
        }
        $pos   = strrpos(static::class, '\\');
        $class = $pos !== false ? substr(static::class, $pos + 1) : static::class;

        return $this->resolvedNameCache = strtolower(
            (string) preg_replace('/[A-Z]/', '_$0', lcfirst($class)),
        );
    }

    /**
     * {@inheritDoc}
     *
     * Returns the snake_case entity name derived from the class name.
     */
    public function name(): string
    {
        return $this->resolveName();
    }

    /**
     * {@inheritDoc}
     *
     * Returns a human-readable label derived from the entity name.
     * Override to provide a custom label.
     */
    public function label(): string
    {
        return $this->labelCache ??= Str::headline($this->resolveName());
    }

    /**
     * {@inheritDoc}
     *
     * Returns the primary key field name used on the Eloquent model.
     */
    public function keyField(): string
    {
        return $this->primaryKey;
    }

    /**
     * {@inheritDoc}
     *
     * Returns the primary key type ('int' or 'string').
     */
    public function keyType(): string
    {
        return $this->primaryKeyType;
    }

    /**
     * {@inheritDoc}
     *
     * Returns the fully-qualified Eloquent model class name, or null for a
     * model-less definition.
     */
    public function modelClass(): ?string
    {
        return $this->model ?? null;
    }

    /**
     * Class-string of a custom {@see \BlackParadise\CoreAdmin\Domain\Repositories\EntityRepositoryInterface}
     * used for reads of this definition, or null for the globally bound (Eloquent) repository.
     *
     * @return class-string|null
     */
    public function repository(): ?string
    {
        return null;
    }

    /**
     * Class-string of a custom {@see \BlackParadise\CoreAdmin\Domain\Mutators\EntityMutatorInterface}
     * used for writes of this definition, or null for the globally bound (Eloquent) mutator.
     *
     * @return class-string|null
     */
    public function mutator(): ?string
    {
        return null;
    }

    /**
     * Write capabilities of this definition. Derived from {@see isCreatable()} by default;
     * override (e.g. `EntityCapabilities::readOnly()`) for finer control.
     */
    public function capabilities(): EntityCapabilities
    {
        $all = EntityCapabilities::all();

        return $this->isCreatable() ? $all : $all->without(Capability::Create);
    }

    /**
     * {@inheritDoc}
     *
     * Returns the default number of records per page for list views.
     */
    public function defaultPerPage(): int
    {
        return $this->perPage;
    }

    /**
     * {@inheritDoc}
     *
     * Returns the field names to include in full-text search. By default
     * derived from the (memoized) {@see fields()}: every AbstractField marked
     * with `->searchable()`, in field order. Override to specify the list
     * explicitly; an override always wins.
     *
     * @return array<string>
     */
    public function searchFields(): array
    {
        $names = [];

        foreach ($this->fields() as $field) {
            if ($field instanceof AbstractField && $field->isSearchable()) {
                $names[] = $field->name();
            }
        }

        return $names;
    }

    /**
     * {@inheritDoc}
     *
     * Returns the {@see $creatable} flag. Override the property (or this method)
     * to mark an entity as embed-only / not directly creatable.
     */
    public function isCreatable(): bool
    {
        return $this->creatable;
    }

    /**
     * Memoized field definitions for this entity.
     *
     * Concrete definitions implement {@see defineFields()}; the result is
     * built once per instance (definitions are registry singletons, so this
     * is effectively once per request). Legacy definitions may still override
     * fields() directly — they simply opt out of memoization.
     *
     * @return array<FieldContract>
     */
    public function fields(): array
    {
        return $this->cachedFields ??= $this->defineFields();
    }

    /**
     * Build the field definitions for this entity.
     *
     * Override this method in concrete definitions to declare fields.
     * Legacy definitions that override {@see fields()} directly continue
     * to work unchanged — they simply opt out of memoization.
     *
     * @return array<FieldContract>
     */
    protected function defineFields(): array
    {
        throw new LogicException(
            static::class . ' must implement defineFields() (or override fields()).',
        );
    }

    /**
     * Return custom action definitions for this entity.
     * Override to add bulk or row-level actions.
     *
     * Entries are class-strings, resolved via ActionResolverContract.
     *
     * @return list<class-string<ActionContract>>
     */
    public function actions(): array
    {
        return [];
    }

    /**
     * Return filter definitions for this entity.
     * Override to add sidebar or header filters.
     *
     * @return array<mixed>
     */
    public function filters(): array
    {
        return [];
    }
}
