<?php

namespace BlackParadise\LaravelAdmin\Core\Builders\PageBuilder\Components;

use BlackParadise\LaravelAdmin\Core\Interfaces\Builders\PageBuilder\Component\LinkInterface;
use Exception;

class LinkFactory
{
    public static function make(string $label, string $icon = '', array $attributes = []): LinkInterface
    {
        if (config('bpadmin.ui_method') === 'blade' && class_exists(\BlackParadise\AdminBladeUI\UI\Builders\PageBuilder\Components\LinkComponent::class)) {
            return new \BlackParadise\AdminBladeUI\UI\Builders\PageBuilder\Components\LinkComponent($label,$icon,$attributes);
        }

        if (config('bpadmin.ui_method') === 'inertia' && class_exists(\BlackParadise\AdminInertiaUI\UI\Builders\PageBuilder\Components\LinkComponent::class)) {
            return new \BlackParadise\AdminInertiaUI\UI\Builders\PageBuilder\Components\LinkComponent($label,$icon,$attributes);
        }

        throw new Exception('Bad UI method or package not installed');
    }
}
