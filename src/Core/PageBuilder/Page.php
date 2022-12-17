<?php

namespace BlackParadise\LaravelAdmin\Core\PageBuilder;

class Page
{
    private string $title;

    private array $components;

    private array $headers;

    public function __construct(string $title, array $components, array $headers = [])
    {
        $this->title = $title;
        $this->components = $components;
        $this->headers = $headers;
    }

    /**
     * @return array
     */
    public function render(): array
    {
        $html = implode('', $this->components);
        $headers = implode('',$this->headers);
        return [
            'title' =>  $this->title,
            'headers' => $headers,
            'html'  =>  $html,
        ];
    }

    /**
     * @param string $component
     */
    public function addComponent(string $component): void
    {
        $this->components[] = $component;
    }
}
