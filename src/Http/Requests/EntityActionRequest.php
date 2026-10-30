<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Http\Requests;

use BlackParadise\CoreAdmin\Domain\Action\ActionScope;
use BlackParadise\CoreAdmin\Domain\Contracts\EntityDefinition\EntityDefinitionContract;
use BlackParadise\CoreAdmin\Domain\ValueObjects\EntityKey;
use BlackParadise\LaravelAdmin\Core\EntityDefinitionRegistry;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Transport for entity action routes: scope + keys come from the route/body,
 * the action input from the `input` array. Authorization and validation of the
 * action itself live in ExecuteActionUseCase.
 */
final class EntityActionRequest extends FormRequest
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
        return [
            'keys'   => ['sometimes', 'array'],
            'keys.*' => ['required'],
            'input'  => ['sometimes', 'array'],
        ];
    }

    public function definition(): EntityDefinitionContract
    {
        return $this->cachedDefinition ??= $this->container
            ->make(EntityDefinitionRegistry::class)
            ->get((string) $this->route('entity'));
    }

    public function actionName(): string
    {
        return (string) $this->route('action');
    }

    public function actionScope(): ActionScope
    {
        if ($this->route('id') !== null) {
            return ActionScope::Row;
        }

        return $this->rawKeys() === [] ? ActionScope::Entity : ActionScope::Bulk;
    }

    /**
     * @return list<EntityKey>
     */
    public function entityKeys(): array
    {
        $type = $this->definition()->keyType();
        $keys = [];

        foreach ($this->rawKeys() as $raw) {
            $keys[] = new EntityKey($type === 'int' ? (int) $raw : (string) $raw, $type);
        }

        return $keys;
    }

    /**
     * @return array<string, mixed>
     */
    public function actionInput(): array
    {
        /** @var array<string, mixed> $input */
        $input = (array) $this->validated('input', []);

        return $input;
    }

    /**
     * @return list<int|string>
     */
    private function rawKeys(): array
    {
        $id = $this->route('id');
        if ($id !== null) {
            return [$id];
        }

        $raw = [];
        foreach ((array) $this->validated('keys', []) as $key) {
            if (is_int($key) || (is_string($key) && $key !== '')) {
                $raw[] = $key;
            }
        }

        return $raw;
    }
}
