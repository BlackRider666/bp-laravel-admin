<?php

namespace BlackParadise\LaravelAdmin\Core\Builders\FormBuilder\Inputs;

trait GetTypeTrait
{
    public function getType()
    {
        return array_key_exists('type',$this->attributes)?$this->attributes['type']:null;
    }
}
