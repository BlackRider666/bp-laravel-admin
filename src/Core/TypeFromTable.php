<?php


namespace BlackParadise\LaravelAdmin\Core;


use ErrorException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
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
        $schema = $model->getConnection()->getDoctrineSchemaManager();
        $columns = $schema->listTableColumns($table);
        $typeList = [];
        $casts = $model->getCasts();
        foreach ($columns as $column) {
            $name = $column->getName();
            $type = $column->getType()->getName();
            switch ($type) {
                case 'string':
                case 'text':
                case 'date':
                case 'time':
                case 'guid':
                case 'datetimetz':
                case 'datetime':
                case 'decimal':
                    $type = 'string';
                    break;
                case 'integer':
                case 'bigint':
                case 'smallint':
                    $type = 'integer';
                    break;
                case 'boolean':
                    $type = 'boolean';
                    break;
                case 'float':
                    $type = 'float';
                    break;
                default:
                    $type = 'mixed';
                    break;
            }
            $typeList[$name] = [
                'type'  =>  array_key_exists($name,$casts)?$casts[$name]:$type,
                'required'  =>  $column->getNotnull(),
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
