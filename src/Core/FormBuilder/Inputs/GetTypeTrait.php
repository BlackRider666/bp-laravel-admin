<?php

namespace BlackParadise\LaravelAdmin\Core\FormBuilder\Inputs;

trait GetTypeTrait
{
    public function getType()
    {
        return array_key_exists('type',$this->attributes)?$this->attributes['type']:null;
    }
}
