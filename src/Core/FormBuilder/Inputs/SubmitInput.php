<?php

namespace BlackParadise\LaravelAdmin\Core\FormBuilder\Inputs;

class SubmitInput implements InputInterface
{
    use GetTypeTrait;

    private array $attributes = [];

    public function __construct(array $attributes = [])
    {
        $this->attributes['label'] = trans('bpadmin::common.forms.submit');
        unset($attributes['name']);
        $this->attributes = array_merge($this->attributes,$attributes);
    }

    /**
     * @return string
     */
    public function render(): string
    {
        $view =  view('bpadmin::components.inputs.submit', [
            'attributes' => $this->attributes,
        ]);
        return $view->render();
    }
}
