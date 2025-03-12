<?php

namespace BlackParadise\LaravelAdmin\Http\Requests\Entity;

use BlackParadise\LaravelAdmin\Core\Builders\FormBuilder\Form;

class StoreAbstractEntityRequest extends BaseAbstractEntityRequest
{
    public function rules(): array
    {
        return $this->BPModel->rules['store'] ?? (new Form([], new $this->BPModel->model, $this->BPModel))->getRules();
    }
}
