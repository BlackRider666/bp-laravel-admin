<?php

namespace BlackParadise\LaravelAdmin\Core\PageBuilder\Components;

class LinkComponent
{
    private array $attributes = [];
    private string $label;
    private string $icon;


    public function __construct(string $label, string $icon = '', array $attributes = [])
    {
        $this->attributes = array_merge($this->attributes,$attributes);
        $this->label = $label;
        $this->icon = $icon;
    }

    /**
     * @return string
     */
    public function render(): string
    {
        $input = '<v-btn outlined dark ';

        foreach ($this->attributes as $key => $value) {
            $input .= $key.'="'.$value.'" ';
        }
        $input .='>';
        if($this->icon) {
            $input .= '<v-icon>'.$this->icon.'</v-icon>';
        }
        $input .= $this->label.'</v-btn>';
        return $input;
    }
}
