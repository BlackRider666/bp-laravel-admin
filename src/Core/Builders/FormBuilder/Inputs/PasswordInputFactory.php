<?php

namespace BlackParadise\LaravelAdmin\Core\Builders\FormBuilder\Inputs;

use BlackParadise\LaravelAdmin\Core\Interfaces\Builders\FormBuilder\Inputs\InputInterface;
use Exception;

class PasswordInputFactory
{
    public static function make(array $attributes, string $entity, array $errors, array $rules = []): InputInterface
    {
        if (config('bpadmin.ui_method') === 'blade' && class_exists(\BlackParadise\AdminBladeUI\UI\Builders\FormBuilder\Inputs\PasswordInput::class)) {
            return new \BlackParadise\AdminBladeUI\UI\Builders\FormBuilder\Inputs\PasswordInput($attributes,$entity,$errors,$rules);
        }

        if (config('bpadmin.ui_method') === 'inertia' && class_exists(BlackParadise\AdminInertiaUI\UI\Builders\FormBuilder\Inputs\PasswordInput::class)) {
            return new \BlackParadise\AdminInertiaUI\UI\Builders\FormBuilder\Inputs\PasswordInput($attributes,$entity,$errors,$rules);
        }

        throw new Exception('Bad UI method or package not installed');
    }
}
