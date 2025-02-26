<?php

namespace BlackParadise\LaravelAdmin\Core\Builders\FormBuilder;


use BlackParadise\LaravelAdmin\Core\Builders\FormBuilder\Inputs\BelongsToInput;
use BlackParadise\LaravelAdmin\Core\Builders\FormBuilder\Inputs\BooleanInput;
use BlackParadise\LaravelAdmin\Core\Builders\FormBuilder\Inputs\EditorInput;
use BlackParadise\LaravelAdmin\Core\Builders\FormBuilder\Inputs\EmailInput;
use BlackParadise\LaravelAdmin\Core\Builders\FormBuilder\Inputs\FileInput;
use BlackParadise\LaravelAdmin\Core\Builders\FormBuilder\Inputs\FloatInput;
use BlackParadise\LaravelAdmin\Core\Builders\FormBuilder\Inputs\HiddenInput;
use BlackParadise\LaravelAdmin\Core\Builders\FormBuilder\Inputs\InputInterface;
use BlackParadise\LaravelAdmin\Core\Builders\FormBuilder\Inputs\IntegerInput;
use BlackParadise\LaravelAdmin\Core\Builders\FormBuilder\Inputs\PasswordInput;
use BlackParadise\LaravelAdmin\Core\Builders\FormBuilder\Inputs\StringInput;
use BlackParadise\LaravelAdmin\Core\Builders\FormBuilder\Inputs\SubmitInput;
use BlackParadise\LaravelAdmin\Core\Builders\FormBuilder\Inputs\TextInput;
use BlackParadise\LaravelAdmin\Core\Builders\FormBuilder\Inputs\TranslatableEditorInput;
use BlackParadise\LaravelAdmin\Core\Builders\FormBuilder\Inputs\TranslatableInput;
use BlackParadise\LaravelAdmin\Core\Models\BPModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ViewErrorBag;

class Form
{
    private array $availableTypes = [
        'boolean'   =>  BooleanInput::class,
        'email'     =>  EmailInput::class,
        'float'     =>  FloatInput::class,
        'integer'   =>  IntegerInput::class,
        'string'    =>  StringInput::class,
        'text'      =>  TextInput::class,
        'hashed'  =>  PasswordInput::class,
        'submit'    =>  SubmitInput::class,
        'BelongsTo' =>  BelongsToInput::class,
        'BelongsToMany' =>  BelongsToInput::class,
        //date
        //phone
        'file'          =>  FileInput::class,
        'translatable'  =>  TranslatableInput::class,
        'translatableEditor'  =>  TranslatableEditorInput::class,
        'editor'        =>  EditorInput::class,
    ];
    private array $attributes = [
        'justify'   =>  'center',
        'align'     =>  'center',
        'enctype'   =>  'multipart/form-data'
    ];
    public array $fields = [];
    private string $entityName;
    private ?Model $model;

    public function __construct(array $attributes, Model $model = null, BPModel $BPModel = null)
    {
        $this->attributes = array_merge($this->attributes,$attributes);
        $this->entityName = $BPModel? $BPModel->name:'search';
        if ($BPModel) {
            if (!$model->exists) {
                $fields = $BPModel->getFields();
            } else {
                $fields =  $BPModel->getFieldsWithoutHidden();
            }
            $errors = session()->get('errors', app(ViewErrorBag::class))->messages();
            foreach ($fields as $key => $value)
            {
                if (substr($key, -6) === 'method' && $value) {
                    $modelMethod = substr($key, 0,-7);
                    $valueModel = $model->$modelMethod()->allRelatedIds()->toArray();
                } else {
                    if (!in_array($value['type'],['translatable','translatableEditor'])) {
                        $valueModel = $model->$key;
                    } else {
                        $valueModel = $model->getTranslations($key);
                    }
                }
                if (in_array($value['type'],['BelongsTo', 'BelongsToMany'])) {
                    $modelMethod = $value['method'];
                    $modelRel = $model->$modelMethod()->getRelated();
                    $items = method_exists($modelRel,'forSelect') ?
                        $modelRel->forSelect()
                        :
                        $modelRel->pluck('name', 'id');
                } else {
                    $items = null;
                }

                $attrUpdated = [
                    'name' => $key,
                    'value' => $valueModel,
                    'model_id' => $model->getKey(),
                    'items' => $items,
                    'key_model' => $BPModel::$key,
                ];

                if (!$valueModel) {
                    unset($attrUpdated['value']);
                }

                $attrField = array_merge($value,$attrUpdated);
                $attrErrors = array_key_exists($key,$errors)?$errors[$key]:[];
                if (array_key_exists($value['type'], $this->availableTypes)) {
                    if($value['type'] === 'file') {
                        $attrField['path'] = $BPModel->filePath;
                    }

                    $this->addField(new ($this->availableTypes[$value['type']])(
                        $attrField,
                        $this->entityName,
                        $attrErrors,
                    ));
                } else {
                    $this->addField(new $this->availableTypes['string']($attrField,$this->entityName, $attrErrors));
                }
            }
        }
        $this->model = $model;
    }

    /**
     * @return string
     */
    public function render(): string
    {
        $form = '<v-form ';
        $attributes = $this->attributes;
        if (!in_array($attributes['method'],['POST','GET'])) {
            $form .= 'method="POST"';
            $this->addFieldToStart((new HiddenInput(['name' => '_method', 'value' => $attributes['method']])));
            unset($attributes['method']);
        }
        foreach ($attributes as $key => $value) {
            if ($key !== 'value' && $key !== 'submit_label') {
                $form .= $key.'="'.$value.'" ';
            }
        }
        $form .='>';
        $form .= PHP_EOL;
        $form .= csrf_field().PHP_EOL;
        foreach ($this->fields as $field) {
            $form .= $field->render();
            $form .= PHP_EOL;
        }
        $form .= $this->submitBtnRender();
        $form .= '</form>';
        return $form;
    }

    /**
     * @param InputInterface $field
     */
    public function addField(InputInterface $field): void
    {
        $this->fields[] = $field;
    }

    public function addFieldToStart(InputInterface $field): void
    {
        array_unshift($this->fields,$field);
    }
    /**
     * @return array
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * @param array $attributes
     */
    public function addAttributes(array $attributes): void
    {
        $this->attributes = array_merge($this->attributes,$attributes);
    }

    public function setCreateAttribute()
    {
        $createAttribute = [
            'method' => 'POST',
            'action' => route('bpadmin.'.$this->entityName.'.store'),
            'submit_label' => trans('bpadmin::common.forms.create'),
        ];
        $this->attributes = array_merge($this->attributes,$createAttribute);
    }

    public function setEditAttribute()
    {
        $createAttribute = [
            'method' => 'PUT',
            'action' => route('bpadmin.'.$this->entityName.'.update', $this->model->getKey()),
            'submit_label' => trans('bpadmin::common.forms.update'),
        ];
        $this->attributes = array_merge($this->attributes,$createAttribute);
    }

    private function submitBtnRender()
    {
        return (new SubmitInput(['label' => $this->attributes['submit_label']]))->render();
    }

    public function getRules($model = null)
    {
        $fields = collect($this->fields);
        return $fields->map(static function($item) use ($model){
            if (!in_array($item->getType(),['translatable','translatableEditor'])) {
                $rules = $item->getRules();
                if (in_array('file',$rules) && in_array('required', $rules) && $model) {
                    $rules = array_map(static function ($rule) {
                        if ($rule !== 'required') {
                            return $rule;
                        }
                    },$rules);
                    $rules = array_filter($rules);
                }
                return [$item->getName() => $rules];
            }
            return [
                $item->getName() => 'array',
                $item->getName().'.*' => $item->getRules(),
            ];
        })->collapse()->toArray();
    }
}
