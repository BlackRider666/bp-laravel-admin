<?php

namespace BlackParadise\LaravelAdmin\Core\Interfaces\Builders\PageBuilder\Component;

interface LinkInterface
{
    public function __construct(string $label, string $icon = '', array $attributes = []);

    public function render(): string;
}
