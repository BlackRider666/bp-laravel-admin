<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Infrastructure\Persistence\FieldPersisters;

use BlackParadise\CoreAdmin\Domain\Contracts\Fields\FieldContract;
use BlackParadise\CoreAdmin\Domain\Fields\TranslatableField;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Casts\AsEncryptedArrayObject;
use Illuminate\Database\Eloquent\Casts\AsEncryptedCollection;
use Illuminate\Database\Eloquent\Model;

/**
 * Translatable value: JSON-encoded unless the field is managed by the model or
 * the host column already carries a self-serializing cast (avoids double encoding).
 */
final class TranslatablePersister extends AbstractFieldPersister
{
    private const SELF_SERIALIZING_CASTS = ['array', 'json', 'object', 'collection', 'translatable'];

    private const SELF_SERIALIZING_CAST_CLASSES = [
        AsArrayObject::class,
        AsCollection::class,
        AsEncryptedArrayObject::class,
        AsEncryptedCollection::class,
    ];

    public function toAttributes(FieldContract $field, mixed $value, PersistContext $context): array
    {
        if (is_array($value) && $this->shouldEncode($field, $context->hostPrototype())) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return [$field->name() => $value];
    }

    private function shouldEncode(FieldContract $field, Model $model): bool
    {
        if ($field instanceof TranslatableField && $field->isManagedByModel()) {
            return false;
        }

        $castType = $this->extractCastType($model, $field->name());
        if ($castType !== null) {
            if (in_array($castType, self::SELF_SERIALIZING_CASTS, true)) {
                return false;
            }
            if ($this->isSelfSerializingClassCast($castType)) {
                return false;
            }
        }

        return true;
    }

    private function isSelfSerializingClassCast(string $cast): bool
    {
        if (!class_exists($cast)) {
            return false;
        }
        foreach (self::SELF_SERIALIZING_CAST_CLASSES as $known) {
            if ($cast === $known || is_subclass_of($cast, $known)) {
                return true;
            }
        }

        return false;
    }

    private function extractCastType(Model $model, string $attribute): ?string
    {
        $casts = $model->getCasts();
        if (!array_key_exists($attribute, $casts)) {
            return null;
        }
        $raw      = (string) $casts[$attribute];
        $colonPos = strpos($raw, ':');

        return $colonPos === false ? $raw : substr($raw, 0, $colonPos);
    }
}
