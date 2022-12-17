<?php

namespace BlackParadise\LaravelAdmin\Core\FormBuilder\Inputs;

class FloatInput extends IntegerInput
{

    public function __construct(array $attributes, string $entity, array $errors, array $rules = [])
    {
        $rules = !empty($rules)? $rules : [
            'front' => [],
            'back'  => ['numeric'],
        ];
        $attributes['step'] = '0.01';
        parent::__construct($attributes,$entity,$errors,$rules);
    }
}
