<?php

namespace BlackParadise\LaravelAdmin\Core\Interfaces\Builders\TableBuilder;

interface TableInterface
{
    public function __construct(array $headers, array $items, string $name, bool $searchable, array $routes = []);

    public function render(array $options = []): string;
}
