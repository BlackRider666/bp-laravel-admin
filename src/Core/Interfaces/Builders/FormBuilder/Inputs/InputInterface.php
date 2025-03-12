<?php

namespace BlackParadise\LaravelAdmin\Core\Interfaces\Builders\FormBuilder\Inputs;

interface InputInterface
{
    public function render();

    public function getRules();
}
