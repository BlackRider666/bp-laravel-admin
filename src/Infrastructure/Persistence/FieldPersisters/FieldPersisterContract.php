<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Infrastructure\Persistence\FieldPersisters;

use BlackParadise\CoreAdmin\Domain\Contracts\Fields\FieldContract;
use Illuminate\Database\Eloquent\Model;

/**
 * Per-field-type write strategy used by the Eloquent mutator.
 *
 * Lifecycle of one write:
 *  1. {@see handles()}      — does this write carry anything for the field?
 *  2. {@see toAttributes()} — host columns to persist (before the host save).
 *  3. {@see afterSave()}    — side effects once the host row exists (same transaction).
 *  4. {@see beforeDelete()} — cascade/cleanup when the host is removed.
 */
interface FieldPersisterContract
{
    /**
     * @param array<string, mixed> $attributes Raw write attributes.
     */
    public function handles(FieldContract $field, array $attributes): bool;

    /**
     * @return array<string, mixed> Host column => value pairs.
     */
    public function toAttributes(FieldContract $field, mixed $value, PersistContext $context): array;

    public function afterSave(FieldContract $field, mixed $value, Model $host, PersistContext $context): void;

    public function beforeDelete(FieldContract $field, Model $host, PersistContext $context): void;
}
