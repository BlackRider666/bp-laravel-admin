<?php

namespace BlackParadise\LaravelAdmin\Core\Builders\TableBuilder;

use BlackParadise\LaravelAdmin\Core\Interfaces\Components\ComponentInterface;

class SimpleTableFactory
{
    public static function make(array $items): ComponentInterface
    {
        if (config('bpadmin.ui_method') === 'blade' && class_exists(\BlackParadise\AdminBladeUI\UI\Components\TableComponent::class)) {
            return new \BlackParadise\AdminBladeUI\UI\Components\TableComponent($items);
        }

        if (config('bpadmin.ui_method') === 'inertia' && class_exists(\BlackParadise\AdminInertiaUI\UI\Components\TableComponent::class)) {
            return new \BlackParadise\AdminInertiaUI\UI\Components\TableComponent($items);
        }

        throw new Exception('Bad UI method or package not installed');
    }
}
