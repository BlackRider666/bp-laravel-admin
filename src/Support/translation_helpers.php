<?php

declare(strict_types=1);

use BlackParadise\CoreAdmin\Domain\Contracts\EntityDefinition\EntityDefinitionContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Fields\FieldContract;

if (!function_exists('bp_field_label')) {
    /**
     * Returns the translated label for a field, or falls back to the field's own label().
     *
     * Note: checks only the current locale — does not cascade to the Laravel
     * fallback locale. Missing translations fall through to the FieldContract's
     * own label() rather than leaking another language's string.
     */
    function bp_field_label(EntityDefinitionContract $definition, FieldContract $field): string
    {
        $key = 'bpadmin::entities.' . $definition->name() . '.' . $field->name();

        return trans()->has($key) ? __($key) : $field->label();
    }
}

if (!function_exists('bp_entity_label')) {
    /**
     * Returns the translated label for an entity, or falls back to the definition's own label().
     *
     * Note: checks only the current locale — does not cascade to the Laravel
     * fallback locale. Missing translations fall through to the FieldContract's
     * own label() rather than leaking another language's string.
     */
    function bp_entity_label(EntityDefinitionContract $definition): string
    {
        $key = 'bpadmin::entities.' . $definition->name() . '._label';

        return trans()->has($key) ? __($key) : $definition->label();
    }
}

if (!function_exists('bp_morph_file_meta')) {
    /**
     * Normalise a morph_file value (array or object) into a flat array.
     *
     * Both `morph-file.blade.php` (form input) and `field-display.blade.php`
     * (read-only display) accept the same value shapes; this helper avoids
     * duplicating the extraction logic in every blade context.
     *
     * @param mixed $value The raw morph_file value from EntityRecord.
     * @return array{path: string|null, name: string|null, mime: string|null, isImage: bool}
     */
    function bp_morph_file_meta(mixed $value): array
    {
        $path = null;
        $name = null;
        $mime = null;

        if (is_array($value)) {
            $path = $value['path'] ?? null;
            $name = $value['name'] ?? null;
            $mime = $value['mime_type'] ?? null;
        } elseif (is_object($value)) {
            $path = $value->path ?? null;
            $name = $value->name ?? null;
            $mime = $value->mime_type ?? null;
        }

        return [
            'path'    => $path,
            'name'    => $name,
            'mime'    => $mime,
            'isImage' => is_string($mime) && str_starts_with($mime, 'image/'),
        ];
    }
}
