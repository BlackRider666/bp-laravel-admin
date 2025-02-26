<?php

namespace BlackParadise\LaravelAdmin\Core\Builders\FormBuilder\Inputs;

class IntegerInput extends StringInput
{
    public function __construct(array $attributes, string $entity, array $errors, array $rules = [])
    {
        unset($attributes['type']);
        $rules = !empty($rules)? $rules : [
            'front' => [],
            'back'  => ['integer'],
        ];
        $attributes['type'] = 'number';
        parent::__construct($attributes, $entity, $errors, $rules);
    }
}
