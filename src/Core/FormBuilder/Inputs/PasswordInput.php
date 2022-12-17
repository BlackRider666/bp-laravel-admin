<?php

namespace BlackParadise\LaravelAdmin\Core\FormBuilder\Inputs;

class PasswordInput extends StringInput
{
    public function __construct(array $attributes, string $entity, array $errors, array $rules = [])
    {
        $attributes['type'] = 'password';
        $rules = !empty($rules)? $rules : [
            'front' => ['min:8','max:255'],
            'back'  => ['string', 'min:8', 'max:255'],
        ];
        parent::__construct($attributes,$entity,$errors, $rules);
    }
}
