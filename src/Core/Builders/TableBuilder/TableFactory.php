<?php

namespace BlackParadise\LaravelAdmin\Core\Builders\TableBuilder;

use BlackParadise\LaravelAdmin\Core\Interfaces\Builders\TableBuilder\TableInterface;
use Exception;

class TableFactory
{
    public static function make(array $headers, array $items, string $name, bool $searchable, array $routes = []): TableInterface
    {
        if (config('bpadmin.ui_method') === 'blade' && class_exists(BlackParadise\LaravelAdminBladeUI\UI\Builders\TableBuilder\Table::class)) {
            return new BlackParadise\LaravelAdminBladeUI\UI\Builders\TableBuilder\Table($headers,$items,$name,$searchable,$routes);
        }

        if (config('bpadmin.ui_method') === 'inertia' && class_exists(BlackParadise\LaravelAdminInertiaUI\UI\Builders\TableBuilder\Table::class)) {
            return new BlackParadise\LaravelAdminInertiaUI\UI\Builders\TableBuilder\Table($headers,$items,$name,$searchable,$routes);
        }

        throw new Exception('Bad UI method or package not installed');
    }
}
