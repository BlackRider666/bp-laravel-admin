<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Http\Requests;

use BlackParadise\CoreAdmin\Domain\Contracts\EntityDefinition\EntityDefinitionContract;
use BlackParadise\CoreAdmin\Domain\Contracts\Fields\FieldContract;
use BlackParadise\CoreAdmin\Domain\Query\Criteria;
use BlackParadise\CoreAdmin\Domain\Query\Filter;
use BlackParadise\CoreAdmin\Domain\Query\Sort;
use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates index-page query parameters and assembles a {@see Criteria} VO.
 *
 * Validation is restricted to HTTP-level shape (sort direction, integer types,
 * filter map). Field-level rules belong to the use-case layer via
 * {@see \BlackParadise\CoreAdmin\Domain\Contracts\Validation\ValidationProviderContract}.
 */
final class EntityIndexRequest extends FormRequest
{
    private ?EntityDefinitionContract $cachedDefinition = null;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $sortable = array_map(fn(FieldContract $f): string => $f->name(), $this->definition()->fields());

        return [
            'sort_by'  => ['nullable', 'string', Rule::in($sortable)],
            'sort_dir' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'page'     => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'q'        => ['nullable', 'string', 'max:255'],
            'filter'   => ['nullable', 'array'],
        ];
    }

    public function criteria(): Criteria
    {
        $definition = $this->definition();
        $validated = $this->validated();

        $sortBy = $validated['sort_by'] ?? null;
        $sortDir = $validated['sort_dir'] ?? 'asc';
        $sort = $sortBy ? [new Sort((string) $sortBy, (string) $sortDir)] : [];

        $filters = $this->buildFilters((array) ($validated['filter'] ?? []), $definition);

        return new Criteria(
            filters: $filters,
            sort: $sort,
            page: (int) ($validated['page'] ?? 1),
            perPage: (int) ($validated['per_page'] ?? $definition->defaultPerPage()),
            search: isset($validated['q']) && $validated['q'] !== '' ? (string) $validated['q'] : null,
        );
    }

    /**
     * @param array<string, mixed> $input
     * @return array<int, Filter>
     */
    private function buildFilters(array $input, EntityDefinitionContract $definition): array
    {
        $allowed = array_map(
            fn(FieldContract $f): string => $f->name(),
            array_filter($definition->fields(), fn(FieldContract $f): bool => $f->isFilterable()),
        );

        $filters = [];
        foreach ($input as $field => $value) {
            if (!in_array($field, $allowed, true)) {
                continue;
            }
            if ($value === '') {
                continue;
            }
            if ($value === null) {
                continue;
            }
            $filters[] = new Filter($field, $value);
        }
        return $filters;
    }

    public function definition(): EntityDefinitionContract
    {
        return $this->cachedDefinition ??= $this->container
            ->make(EntityDefinitionRegistry::class)
            ->get((string) $this->route('entity'));
    }
}
