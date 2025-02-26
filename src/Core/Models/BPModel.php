<?php

namespace BlackParadise\LaravelAdmin\Core\Models;

use BlackParadise\LaravelAdmin\Core\Builders\FormBuilder\Form;
use BlackParadise\LaravelAdmin\Core\Presenters\DashboardPresenter;
use BlackParadise\LaravelAdmin\Core\Repo\AbstractRepo;
use BlackParadise\LaravelAdmin\Core\Services\StorageManager;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class BPModel
{
    public string $model;

    public string $name;

    public static string $key = 'id';

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
            return $field['type'] === 'file';
        }));
    }

    public function getEditFields()
    {
        $fields = array_keys($this->getFieldsWithoutHidden());
        $fields = array_filter($fields,function ($field) {
            return substr($field, -6) !== 'method' && $field;
        });
        $fields[] = 'id';

        return $fields;
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
        $fieldsToFind = array_filter($fields, function($item) {
            return substr($item, -6) !== 'method' && $item;
        },1);
        $model = $this->findQuery($id, $fieldsToFind);
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

    public function delete(int $id)
    {
        $model = $this->findQuery($id);
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

    public function findQuery(int $id, array $fields = ['*'])
    {
        return (new AbstractRepo($this->model,$this->searchFields))->find($id, $fields);
    }
}
