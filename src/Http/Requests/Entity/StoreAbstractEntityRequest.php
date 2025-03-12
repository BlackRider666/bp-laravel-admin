<?php

namespace BlackParadise\LaravelAdmin\Http\Requests\Entity;

use BlackParadise\LaravelAdmin\Core\Builders\FormBuilder\FormFactory;

class StoreAbstractEntityRequest extends BaseAbstractEntityRequest
{
    public function rules(): array
    {
        return $this->BPModel->rules['store'] ?? FormFactory::make([], new $this->BPModel->model, $this->BPModel)->getRules();
    }
}
