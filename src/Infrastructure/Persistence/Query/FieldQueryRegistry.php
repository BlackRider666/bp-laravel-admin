<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Infrastructure\Persistence\Query;

use BlackParadise\CoreAdmin\Domain\Contracts\EntityDefinition\EntityDefinitionContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Fields\FieldContract;
use BlackParadise\CoreAdmin\Domain\Exceptions\InvalidDefinitionException;

/**
 * Field type string → {@see FieldQueryStrategyContract}. Unknown types fall back to the plain
 * column strategy. Hosts extend it via {@see register()}.
 */
final class FieldQueryRegistry
{
    /** @var array<string, FieldQueryStrategyContract> */
    private array $strategies = [];

    public function __construct(private readonly FieldQueryStrategyContract $fallback) {}

    public function register(string $type, FieldQueryStrategyContract $strategy): void
    {
        $this->strategies[$type] = $strategy;
    }

    public function for(FieldContract $field): FieldQueryStrategyContract
    {
        return $this->strategies[$field->type()] ?? $this->fallback;
    }

    /**
     * Every field that is marked searchable, sortable or filterable but is not queryable.
     *
     * @param list<FieldContract>|null $fields pre-built fields() of the definition (built here when omitted)
     * @return list<array{field: string, type: string, flags: string}>
     */
    public function nonQueryableFields(EntityDefinitionContract $definition, ?array $fields = null): array
    {
        $searchable = array_flip($definition->searchFields());
        $found = [];

        foreach ($fields ?? $definition->fields() as $field) {
            if ($this->for($field)->isQueryable()) {
                continue;
            }

            $flags = [];
            if (isset($searchable[$field->name()])) {
                $flags[] = 'searchable';
            }
            if ($field->isSortable()) {
                $flags[] = 'sortable';
            }
            if ($field->isFilterable()) {
                $flags[] = 'filterable';
            }

            if ($flags !== []) {
                $found[] = ['field' => $field->name(), 'type' => $field->type(), 'flags' => implode('/', $flags)];
            }
        }

        return $found;
    }

    /**
     * Reject definitions that mark a non-queryable field as searchable, sortable or filterable.
     *
     * @throws InvalidDefinitionException naming the offending field.
     */
    public function assertQueryable(EntityDefinitionContract $definition): void
    {
        foreach ($this->nonQueryableFields($definition) as $violation) {
            throw new InvalidDefinitionException($this->nonQueryableMessage($definition, $violation));
        }
    }

    /** @param array{field: string, type: string, flags: string} $violation */
    public function nonQueryableMessage(EntityDefinitionContract $definition, array $violation): string
    {
        return sprintf(
            "Field '%s' (type '%s') in %s cannot be %s: it is not backed by a queryable column.",
            $violation['field'],
            $violation['type'],
            $definition::class,
            $violation['flags'],
        );
    }
}
