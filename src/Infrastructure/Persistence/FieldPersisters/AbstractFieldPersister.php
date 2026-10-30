<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Infrastructure\Persistence\FieldPersisters;

use BlackParadise\CoreAdmin\Domain\Contracts\Fields\FieldContract;
use BlackParadise\CoreAdmin\Domain\Fields\Base\AbstractField;
use Illuminate\Database\Eloquent\Model;

/**
 * No-op defaults so concrete persisters override only the hooks they need.
 */
abstract class AbstractFieldPersister implements FieldPersisterContract
{
    public function handles(FieldContract $field, array $attributes): bool
    {
        return array_key_exists($field->name(), $attributes)
            || array_key_exists($field->name() . '__clear', $attributes);
    }

    public function toAttributes(FieldContract $field, mixed $value, PersistContext $context): array
    {
        return [];
    }

    public function afterSave(FieldContract $field, mixed $value, Model $host, PersistContext $context): void {}

    public function beforeDelete(FieldContract $field, Model $host, PersistContext $context): void {}

    /**
     * Truthy `{field}__clear` flag, ignored for required / requiredOnCreate fields
     * (defence in depth; the core RuleBuilder already rejects those with 422).
     */
    protected function isClearRequested(FieldContract $field, PersistContext $context): bool
    {
        $flag = $context->attributes[$field->name() . '__clear'] ?? null;
        if ($flag === null || $flag === '' || !filter_var($flag, FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        return !$this->isRequiredFile($field);
    }

    protected function isRequiredFile(FieldContract $field): bool
    {
        if ($field instanceof AbstractField && $field->isRequiredOnCreate()) {
            return true;
        }

        return in_array('required', $field->expandRules([], 'create')[$field->name()] ?? [], true);
    }
}
