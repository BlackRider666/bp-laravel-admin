<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Infrastructure\Persistence\FieldPersisters;

use BlackParadise\CoreAdmin\Domain\Contracts\Fields\FieldContract;
use BlackParadise\CoreAdmin\Domain\Contracts\ValueHasherContract;

/** Empty value keeps the stored hash; already-hashed values are stored as-is. */
final class HashedPersister extends AbstractFieldPersister
{
    public function __construct(private readonly ValueHasherContract $hasher) {}

    public function toAttributes(FieldContract $field, mixed $value, PersistContext $context): array
    {
        if (in_array($value, ['', null, false], true) || (is_string($value) && trim($value) === '')) {
            return [];
        }
        if (is_string($value) && $this->hasher->isHashed($value)) {
            return [$field->name() => $value];
        }

        return [$field->name() => $this->hasher->hash((string) $value)];
    }
}
