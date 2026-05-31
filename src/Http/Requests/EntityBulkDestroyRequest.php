<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Http\Requests;

use BlackParadise\CoreAdmin\Domain\Contracts\EntityDefinition\EntityDefinitionContract;
use BlackParadise\CoreAdmin\Domain\ValueObjects\EntityKey;
use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use Illuminate\Foundation\Http\FormRequest;

final class EntityBulkDestroyRequest extends FormRequest
{
    private ?EntityDefinitionContract $cachedDefinition = null;

    public function authorize(): bool
    {
        return true;
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
