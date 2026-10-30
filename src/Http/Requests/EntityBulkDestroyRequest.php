<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Http\Requests;

use BlackParadise\CoreAdmin\Domain\Contracts\EntityDefinition\EntityDefinitionContract;
use BlackParadise\CoreAdmin\Domain\ValueObjects\Capability;
use BlackParadise\CoreAdmin\Domain\ValueObjects\EntityKey;
use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use BlackParadise\LaravelAdmin\Http\Presenters\EntityPresenterInterface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

final class EntityBulkDestroyRequest extends FormRequest
{
    private ?EntityDefinitionContract $cachedDefinition = null;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Capability gate runs before `ids` validation: an entity that does not
     * allow delete exposes no bulk-delete surface at all (404, never 422).
     */
    protected function prepareForValidation(): void
    {
        if (!$this->definition()->capabilities()->allows(Capability::Delete)) {
            throw new HttpResponseException(
                $this->container->make(EntityPresenterInterface::class)->notFound(),
            );
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $itemRules = ['required'];
        $itemRules[] = $this->definition()->keyType() === 'int'
            ? 'integer'
            : 'string';

        return [
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => $itemRules,
        ];
    }

    /**
     * @return array<int, EntityKey>
     */
    public function entityKeys(): array
    {
        $keyType = $this->definition()->keyType();
        $keys = [];

        foreach ((array) $this->validated('ids', []) as $rawId) {
            if ($rawId === '') {
                continue;
            }
            if ($rawId === null) {
                continue;
            }
            $value = $keyType === 'int' ? (int) $rawId : (string) $rawId;
            $keys[] = new EntityKey($value, $keyType);
        }

        return $keys;
    }

    public function definition(): EntityDefinitionContract
    {
        return $this->cachedDefinition ??= $this->container
            ->make(EntityDefinitionRegistry::class)
            ->get((string) $this->route('entity'));
    }
}
