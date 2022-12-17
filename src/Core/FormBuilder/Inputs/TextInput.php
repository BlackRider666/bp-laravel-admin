<?php

namespace BlackParadise\LaravelAdmin\Core\FormBuilder\Inputs;

class TextInput implements InputInterface
{
    private array $attributes = [];
    private array $errors;
    private array $rules;

    public function __construct(array $attributes, string $entity, array $errors, array $rules = [])
    {
        $this->attributes['label'] = trans('bpadmin::'.$entity.'.'.$attributes['name']);
        $this->attributes['value'] = old($attributes['name']);
        $this->rules = !empty($rules)? $rules : [
            'front' => [],
            'back'  => ['string'],
        ];
        if ($attributes['required']) {
            $this->rules['front'][] = 'required';
            $this->rules['back'][] = 'required';
            unset($attributes['required']);
        }
        $this->attributes = array_merge($this->attributes,$attributes);
        $this->errors = $errors;
    }

    /**
     * @return string
     * @throws Throwable
     */
    public function render(): string
    {
        $view =  view('bpadmin::components.inputs.text', [
            'attributes' => $this->attributes,
            'errors' => $this->errors,
        ]);
        return $view->render();
    }

    /**
     * @return array
     */
    public function getRules(): array
    {
        return $this->rules['back'];
    }

    public function getName()
    {
        return $this->attributes['name'];
    }
}
