<?php

namespace BlackParadise\LaravelAdmin\Core\Builders\FormBuilder;

use BlackParadise\LaravelAdmin\Core\Interfaces\Builders\FormBuilder\FormInterface;
use BlackParadise\LaravelAdmin\Core\Models\BPModel;
use Exception;
use Illuminate\Database\Eloquent\Model;

class FormFactory
{
    public static function make(array $attributes, Model $model = null, BPModel $BPModel = null): FormInterface
    {
        if (config('bpadmin.ui_method') === 'blade' && class_exists(\BlackParadise\AdminBladeUI\UI\Builders\FormBuilder\Form::class)) {
            return new \BlackParadise\AdminBladeUI\UI\Builders\FormBuilder\Form($attributes, $model, $BPModel);
        }

        if (config('bpadmin.ui_method') === 'inertia' && class_exists(\BlackParadise\AdminInertiaUI\UI\Builders\FormBuilder\Form::class)) {
            return new \BlackParadise\AdminInertiaUI\UI\Builders\FormBuilder\Form($attributes, $model, $BPModel);
        }

        throw new Exception('Bad UI method or package not installed');
    }
}
