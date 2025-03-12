<?php

namespace BlackParadise\LaravelAdmin\Core\Builders\PageBuilder;

use BlackParadise\LaravelAdmin\Core\Interfaces\Builders\PageBuilder\PageInterface;
use Exception;

class PageFactory
{
    public static function make(string $layout, string $title, array $components, array $headers = []): PageInterface
    {
        if (config('bpadmin.ui_method') === 'blade' && class_exists(BlackParadise\LaravelAdminBladeUI\UI\Builders\PageBuilder\Page::class)) {
            return new BlackParadise\LaravelAdminBladeUI\UI\Builders\PageBuilder\Page($layout, $title, $components, $headers);
        }

        if (config('bpadmin.ui_method') === 'inertia' && class_exists(BlackParadise\LaravelAdminInertiaUI\UI\Builders\PageBuilder\Page::class)) {
            return new BlackParadise\LaravelAdminInertiaUI\UI\Builders\PageBuilder\Page($layout, $title, $components, $headers);
        }

        throw new Exception('Bad UI method or package not installed');
    }
}
