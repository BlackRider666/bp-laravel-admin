<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Infrastructure\Persistence\FieldPersisters;

use BlackParadise\CoreAdmin\Domain\Contracts\Fields\FieldContract;

/**
 * Field type string → {@see FieldPersisterContract}. Unknown types fall back to
 * the plain column persister. Hosts extend it via {@see register()}.
 */
final class FieldPersisterRegistry
{
    /** @var array<string, FieldPersisterContract> */
    private array $persisters = [];

    public function __construct(private readonly FieldPersisterContract $fallback) {}

    public function register(string $type, FieldPersisterContract $persister): void
    {
        $this->persisters[$type] = $persister;
    }

    public function for(FieldContract $field): FieldPersisterContract
    {
        return $this->persisters[$field->type()] ?? $this->fallback;
    }
}
