<?php

namespace BlackParadise\LaravelAdmin\Core\Builders\PageBuilder\Components;

use BlackParadise\LaravelAdmin\Core\Interfaces\Builders\PageBuilder\Component\LinkInterface;
use Exception;

class LinkFactory
{
    public static function make(string $label, string $icon = '', array $attributes = []): LinkInterface
    {
        if (config('bpadmin.ui_method') === 'blade' && class_exists(BlackParadise\LaravelAdminBladeUI\UI\Builders\PageBuilder\Components\LinkComponent::class)) {
            return new BlackParadise\LaravelAdminBladeUI\UI\Builders\PageBuilder\Components\LinkComponent($label,$icon = '',$attributes = []);
        }

        if (config('bpadmin.ui_method') === 'inertia' && class_exists(BlackParadise\LaravelAdminInertiaUI\UI\Builders\PageBuilder\Components\LinkComponent::class)) {
            return new BlackParadise\LaravelAdminInertiaUI\UI\Builders\PageBuilder\Components\LinkComponent($label,$icon = '',$attributes = []);
        }

        throw new Exception('Bad UI method or package not installed');
    }
}
