<?php


namespace BlackParadise\LaravelAdmin\Core;


use ErrorException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use ReflectionMethod;

class TypeFromTable
{
    /**
     * @param Model $model
     * @return array
     */
    public function getTypeList(Model $model): array
    {
        $table = $model->getConnection()->getTablePrefix() . $model->getTable();
        $columns = Schema::getColumns($table);
        $typeList = [];
        foreach ($columns as $column) {
            $name = $column['name'];
            $type = $column['type_name'];
            switch ($type) {
                case 'string':
                case 'varchar':
                case 'guid':
                case 'json':
                    $type = 'string';
                    break;
                case 'text':
                    $type = 'text';
                    break;
                case 'date':
                    $type = 'date';
                    break;
                case 'time':
                    $type = 'time';
                    break;
                case 'datetimetz':
                case 'datetime':
                    $type = 'datetime';
                    break;
                case 'float':
                case 'decimal':
                    $type = 'float';
                    break;
                case 'integer':
                case 'bigint':
                case 'smallint':
                    $type = 'integer';
                    break;
                case 'boolean':
                    $type = 'boolean';
                    break;
                default:
                    $type = 'mixed';
                    break;
            }
            $typeList[$name] = [
                'type'  =>  $type,
                'required'  =>  !$column['nullable'],
            ];
        }
        $reflector = new ReflectionClass($model);
        $relationFields = [];
        foreach ($reflector->getMethods() as $reflectionMethod) {
            $returnType = $reflectionMethod->getReturnType();
            if ($returnType) {
                if (in_array(class_basename($returnType->getName()), ['BelongsTo', 'BelongsToMany'])) {
                    $relName = $reflectionMethod->getName();
                    $typeList[$relName]['type'] = class_basename($returnType->getName());
                    $typeList[$relName]['method'] = $relName;
                    $relationFields[] = $relName;
                    if(class_basename($returnType->getName()) == 'BelongsToMany') {
                        $typeList[$relName]['multiple'] = true;
                    } else {
                        $typeList[$relName]['required'] = true;
                    }
                }
            }
        }
        $fields = [];
        foreach ($model->getFillable() as $modelType) {
            $fields[$modelType] = $typeList[$modelType];
        }
        foreach ($relationFields as $rel) {
            if ($typeList[$rel]['type'] === 'BelongsTo') {
                $fields[$rel.'_id'] = $typeList[$rel];
            } else {
                $fields[$rel.'_method'] = $typeList[$rel];
            }
        }
        if ($model->translatable) {
            foreach ($model->translatable as $transField) {
                $fields[$transField]['type'] = 'translatable';
            }
        }
        if ($model->editable) {
            foreach ($model->editable as $editableField) {
                $fields[$editableField]['type'] = $fields[$editableField]['type'] === 'translatable'?'translatableEditor':'editor';
            }
        }
        return $fields;
    }

    public function getTypeListWithoutHidden(Model $model)
    {
        $fields = $this->getTypeList($model);
        foreach ($model->getHidden() as $hidden) {
            unset($fields[$hidden]);
        }
        return $fields;
    }
}
