<?php

namespace BlackParadise\LaravelAdmin\Http\Requests\Entity;

use BlackParadise\LaravelAdmin\Core\Builders\FormBuilder\FormFactory;

class UpdateAbstractEntityRequest extends BaseAbstractEntityRequest
{
    public function rules(): array
    {
        if ($this->BPModel->rules['update'] !== null) {
            return $this->BPModel->rules['update'];
        }

        $fields = array_keys(array_filter($this->BPModel->getFieldsWithoutHidden(), static function($item, $key) {
            return !str_ends_with($key, 'method') && $item;
        },1));
        $fields[] = 'id';

        $item = $this->BPModel->findQuery($this->id, $fields);

        return FormFactory::make([], $item, $this->BPModel)->getRules($item);
    }
}
