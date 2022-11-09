<?php

namespace BlackParadise\LaravelAdmin\Core\FormBuilder\Inputs;

class SubmitInput implements InputInterface
{
    private array $attributes = [
        'type'  =>  'submit',
        'class' =>  'btn btn-primary ml-auto waves-effect waves-themed',
    ];

    private string $transField;

    public function __construct(array $attributes, string $transField = 'bpadmin::common.forms.submit')
    {
        $this->attributes = array_merge($this->attributes,$attributes);
        $this->transField = $transField;
    }
    public function render()
    {
        $input = '';
        foreach ($this->attributes as $key => $value) {
            $input .= $key.'="'.$value.'"';
        }
        $input .='>';
        $input .= trans($this->transField);
        $input .= '</button>';

        return $input;
    }
}
