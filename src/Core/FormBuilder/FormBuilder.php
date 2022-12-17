<?php

namespace BlackParadise\LaravelAdmin\Core\FormBuilder;


use BlackParadise\LaravelAdmin\Core\TypeFromTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class FormBuilder
{

    private Form $form;

    public function __construct(Form $form)
    {
        $this->form = $form;
    }

    /**
     * @return string
     */
    public function render(): string
    {
        return $this->form->render();
    }

    public function renderCreateForm()
    {
        $this->form->setCreateAttribute();
        return $this->render();
    }

    public function renderEditForm()
    {
        $this->form->setEditAttribute();
        return $this->render();
    }
}
