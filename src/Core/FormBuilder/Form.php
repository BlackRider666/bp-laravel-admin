<?php

namespace BlackParadise\LaravelAdmin\Core\FormBuilder;


use BlackParadise\LaravelAdmin\Core\FormBuilder\Inputs\BelongsToInput;
use BlackParadise\LaravelAdmin\Core\FormBuilder\Inputs\BooleanInput;
use BlackParadise\LaravelAdmin\Core\FormBuilder\Inputs\EmailInput;
use BlackParadise\LaravelAdmin\Core\FormBuilder\Inputs\FileInput;
use BlackParadise\LaravelAdmin\Core\FormBuilder\Inputs\FloatInput;
use BlackParadise\LaravelAdmin\Core\FormBuilder\Inputs\HiddenInput;
use BlackParadise\LaravelAdmin\Core\FormBuilder\Inputs\InputInterface;
use BlackParadise\LaravelAdmin\Core\FormBuilder\Inputs\IntegerInput;
use BlackParadise\LaravelAdmin\Core\FormBuilder\Inputs\PasswordInput;
use BlackParadise\LaravelAdmin\Core\FormBuilder\Inputs\StringInput;
use BlackParadise\LaravelAdmin\Core\FormBuilder\Inputs\SubmitInput;
use BlackParadise\LaravelAdmin\Core\FormBuilder\Inputs\TextInput;
use BlackParadise\LaravelAdmin\Core\TypeFromTable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Http\Request;

class Form
{
    private array $availableTypes = [
        'boolean'   =>  BooleanInput::class,
        'email'     =>  EmailInput::class,
        'float'     =>  FloatInput::class,
        'integer'   =>  IntegerInput::class,
        'string'    =>  StringInput::class,
        'text'      =>  TextInput::class,
        'password'  =>  PasswordInput::class,
        'submit'    =>  SubmitInput::class,
        'BelongsTo' =>  BelongsToInput::class,
        'BelongsToMany' =>  BelongsToInput::class,
        //date
        //phone
        'file'          =>  FileInput::class,
    ];
    private array $attributes = [
        'justify'   =>  'center',
        'align'     =>  'center',
        'enctype'   =>  'multipart/form-data'
    ];
    private array $fields = [];
    private string $entityName;
    private ?Model $model;

    public function __construct(array $attributes, Model $model = null, string $entityName = 'search', array $availableTypes = [])
    {
        $this->attributes = array_merge($this->attributes,$attributes);
        $this->availableTypes = array_merge($this->availableTypes,$availableTypes);
        $this->entityName = $entityName;
        if ($model) {
            if (!$model->exists) {
                $fields =  Cache::rememberForever($this->entityName.'.store', static function () use ($model) {
                    return (new TypeFromTable())->getTypeList($model);
                });
            } else {
                $fields =  Cache::rememberForever($this->entityName.'.update', static function () use ($model) {
                    return (new TypeFromTable())->getTypeListWithoutHidden($model);
                });
            }
            $errors = session()->get('errors', app(ViewErrorBag::class))->messages();
            foreach ($fields as $key => $value)
            {
                if (substr($key, -6) === 'method' && $value) {
                    $modelMethod = substr($key, 0,-7);
                    $valueModel = $model->$modelMethod()->pluck('id')->toArray();
                } else {
                    $valueModel = $model->$key;
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
                $attrField = array_merge($value,['name' => $key, 'value' => $valueModel, 'model_id' => $model->getKey(), 'items' => $items]);
                $attrErrors = array_key_exists($key,$errors)?$errors[$key]:[];
                if (array_key_exists($value['type'], $this->availableTypes)) {
                    if($value['type'] === 'file') {
                    }
                    $this->addField(new $this->availableTypes[$value['type']](
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

    public function validate(array $data, array $rules = [])
    {
        if (empty($rules)) {
            $fields = collect($this->fields);
            $rules = $fields->map(function($item) {
                return [$item->getName() => $item->getRules()];
            })->collapse()->toArray();
        }
        return Validator::make($data,$rules);
    }
}
