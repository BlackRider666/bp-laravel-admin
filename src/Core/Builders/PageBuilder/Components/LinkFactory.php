<?php

namespace BlackParadise\LaravelAdmin\Core\Builders\PageBuilder\Components;

use BlackParadise\LaravelAdmin\Core\Interfaces\Components\ComponentInterface;
use Exception;

class LinkFactory
{
    public static function make(array $attributes = []): ComponentInterface
    {
        if (config('bpadmin.ui_method') === 'blade' && class_exists(\BlackParadise\AdminBladeUI\UI\Components\LinkComponent::class)) {
            return new \BlackParadise\AdminBladeUI\UI\Components\LinkComponent($attributes);
        }

        if (config('bpadmin.ui_method') === 'inertia' && class_exists(\BlackParadise\AdminInertiaUI\UI\Components\LinkComponent::class)) {
            return new \BlackParadise\AdminInertiaUI\UI\Components\LinkComponent($attributes);
        }

        throw new Exception('Bad UI method or package not installed');
    }
}
