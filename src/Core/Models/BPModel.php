<?php

namespace BlackParadise\LaravelAdmin\Core\Models;

use BlackParadise\LaravelAdmin\Core\AbstractRepo;
use BlackParadise\LaravelAdmin\Core\DashboardPresenter;
use BlackParadise\LaravelAdmin\Core\StorageManager;
use BlackParadise\LaravelAdmin\Core\FormBuilder\Form;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

class BPModel
{
    public string $model;

    public string $name;

    protected static string $key = 'id';

    public string $filePath = '';

    protected array $fieldTypes = [];

    protected static int $perPage = 10;

    public array $searchFields = [];

    public array $tableHeaderFields = [];

    public array $showPageFields = [];

    public static string $validationType = 'default';

    public array $rules = [
        'store' => null,
        'update' => null,
    ];

    public function getFields()
    {
        return $this->fieldTypes;
    }

    public function getFieldsWithoutHidden()
    {
        $fields = $this->fieldTypes;
        $model = new $this->model;
        foreach ($model->getHidden() as $hidden) {
            unset($fields[$hidden]);
        }
        return $fields;
    }

    public function getFileFields()
    {
        return array_keys(array_filter($this->fieldTypes, function ($field) {
            return $field['type'] == 'file';
        }));
    }

    public function getTablePage(Request $request): View
    {
        $data = $request->all(['perPage','page','sortBy','sortDesc', 'q']);
        $headers = $this->tableHeaderFields;
        $searchable = !empty($this->searchFields);

        $items = $this->indexQuery($data, $headers);
        $items = $items->toArray();
        $entity = new $this->model;
        $items['data'] = array_map(function ($item) use ($headers, $entity) {
            if ($entity->translatable) {
                foreach(array_intersect($entity->translatable, $headers) as $transField)
                {
                    $item[$transField] = $item[$transField]?$item[$transField][config('bpadmin.languages')[0]]:null;
                }
            }
            return $item;
        },$items['data']);
        return (new DashboardPresenter())->getTablePage($headers, $items,$this->name,$searchable);
    }

    public function getCreatePage(): View
    {
        return (new DashboardPresenter())->getCreatePage($this);
    }

    public function getShowPage(int $id): View
    {
        $item = $this->findQuery($id, $this->showPageFields);

        return (new DashboardPresenter())->getShowPage($item, $this->showPageFields, $this->name);
    }

    public function getEditPage(int $id): View
    {
        $fields = array_keys($this->getFieldsWithoutHidden());
        $fields = array_filter($fields,function ($field) {
            return substr($field, -6) !== 'method';
        });
        $fields[] = 'id';
        $item = $this->findQuery($id, $fields);

        return (new DashboardPresenter())->getEditPage($this, $item);
    }

    public function storeEntity(array $data)
    {
        $createdModel = $this->model::create($data);
        $methods = array_filter($data, function($item,$key) {
            return substr($key, -6) === 'method' && $item;
        },1);
        $methodsWithCorrectItem = array_map(function($item) {
            $items = explode(',',$item);
            return array_map(static function($item){
                return (int)$item;
            },$items);
        },$methods);
        foreach ($methodsWithCorrectItem as $method => $value) {
            $modelMethod = substr($method, 0,-7);
            $createdModel->$modelMethod()->sync($value);
        }
    }

    public function updateEntity(int $id, array $data)
    {
        $fields = array_keys($this->getFieldsWithoutHidden());
        $fields[] = 'id';
        $model = $this->findQuery($id, $fields);
        $model->update($data);
        $methods = array_filter($data, function($item,$key) {
            return substr($key, -6) === 'method' && $item;
        },1);
        $methodsWithCorrectItem = array_map(function($item) {
            $items = explode(',',$item);
            return array_map(static function($item){
                return (int)$item;
            },$items);
        },$methods);
        foreach ($methodsWithCorrectItem as $method => $value) {
            $modelMethod = substr($method, 0,-7);
            $model->$modelMethod()->sync($value);
        }
    }

    public function getStoreRules()
    {
        if ($this->rules['store'] !== null) {
            return $this->rules['store'];
        }
        $form = new Form([],new $this->model,$this);
        return $form->getRules();
    }

    public function getUpdateRules(int $id)
    {
        if ($this->rules['update'] !== null) {
            return $this->rules['update'];
        }
        $model = $fields = array_keys($this->getFieldsWithoutHidden());
        $fields[] = 'id';
        $item = $this->findQuery($id, $fields);
        $form = new Form([],$item,$this);
        return $form->getRules($item);
    }

    public function delete(int $id)
    {
        $model = (new AbstractRepo($this->model,$this->searchFields))->find($id);
        if ($model) {
            $files = $this->getFileFields();
            foreach($files as $key => $value) {
                if ($model->$value !== null) {
                    (new StorageManager())->deleteFile($model->$value,$this->filePath.'/'.$value);
                }
            }
            $model->delete();
        }
    }

    public function indexQuery(array $data, array $headers): LengthAwarePaginator
    {
        return (new AbstractRepo($this->model,$this->searchFields))->search($data,$headers);
    }

    public function findQuery(int $id, array $fields)
    {
        return (new AbstractRepo($this->model,$this->searchFields))->find($id, $fields);
    }
}
