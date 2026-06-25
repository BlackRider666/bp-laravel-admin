<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin;

use BlackParadise\CoreAdmin\Domain\Contracts\Action\ActionContract;
use BlackParadise\CoreAdmin\Domain\Contracts\EntityDefinition\EntityDefinitionContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Fields\FieldContract;
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
     * Fully-qualified Eloquent model class.
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
     * accessible — see {@see isCreatable()}.
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
     * Returns the fully-qualified Eloquent model class name.
     */
    public function modelClass(): string
    {
        return $this->model;
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
     * Returns the field names to include in full-text search.
     * Override to specify searchable fields for this entity.
     *
     * @return array<string>
     */
    public function searchFields(): array
    {
        return [];
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
     * @return array<ActionContract>
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
