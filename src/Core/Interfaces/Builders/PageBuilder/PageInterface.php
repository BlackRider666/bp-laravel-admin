<?php

namespace BlackParadise\LaravelAdmin\Core\Interfaces\Builders\PageBuilder;
use Inertia\Response;
use Illuminate\View\View;

interface PageInterface
{
    public function __construct(string $layout, string $title, array $components, array $headers = []);

    public function render(): Response|View;

    public function addComponent(string $component): void;
}
