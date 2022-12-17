<?php

namespace BlackParadise\LaravelAdmin\Core\FormBuilder\Inputs;

class BelongsToInput implements InputInterface
{
    private array $attributes = [];
    private array $items;
    private array $errors;
    private array $rules = [
        'front' => [],
        'back'  => [],
    ];
    public function __construct(array $attributes, string $entity, array $errors)
    {
        $this->items = $attributes['items']->toArray();
        unset($attributes['items'], $attributes['type']);
        $this->attributes['label'] = trans('bpadmin::'.$entity.'.'.$attributes['name']);
        $this->attributes['value'] = old($attributes['name']);
        $this->attributes = array_merge($this->attributes,$attributes);
        $this->errors = $errors;
    }

    /**
     * @return string
     */
    public function render(): string
    {
        $view = view('bpadmin::components.inputs.select', [
            'attributes'    => $this->attributes,
            'items'         => $this->items,
            'errors'        => $this->errors,
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
