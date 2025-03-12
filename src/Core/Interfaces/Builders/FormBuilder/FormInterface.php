<?php

namespace BlackParadise\LaravelAdmin\Core\Interfaces\Builders\FormBuilder;

use BlackParadise\LaravelAdmin\Core\Interfaces\Builders\FormBuilder\Inputs\InputInterface;
use BlackParadise\LaravelAdmin\Core\Models\BPModel;
use Illuminate\Database\Eloquent\Model;

interface FormInterface
{
    public function __construct(array $attributes, Model $model = null, BPModel $BPModel = null);

    public function addField(InputInterface $field): void;

    public function addFieldToStart(InputInterface $field): void;

    public function getFields(): array;

    public function addAttributes(array $attributes): void;

    public function render(): string;

    public function renderCreateForm(): string;

    public function renderEditForm(): string;

    public function getRules(Model $model = null);

}
