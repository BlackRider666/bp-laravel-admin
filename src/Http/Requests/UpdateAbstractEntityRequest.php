<?php

namespace BlackParadise\LaravelAdmin\Http\Requests;

use BlackParadise\LaravelAdmin\Core\Builders\FormBuilder\Form;

class UpdateAbstractEntityRequest extends BaseAbstractEntityRequest
{
    public function rules():array
    {
        if ($this->BPModel->rules['update'] !== null) {
            return $this->BPModel->rules['update'];
        }

        $fields = array_keys(array_filter($this->BPModel->getFieldsWithoutHidden(), static function($item, $key) {
            return !str_ends_with($key, 'method') && $item;
        },1));
        $fields[] = 'id';

        $item = $this->BPModel->findQuery($this->id, $fields);

        return (new Form([], $item, $this->BPModel))->getRules($item);
    }
}
