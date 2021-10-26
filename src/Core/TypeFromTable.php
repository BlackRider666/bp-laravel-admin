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
        foreach ($reflector->getMethods() as $reflectionMethod) {
            $returnType = $reflectionMethod->getReturnType();
            if ($returnType) {
                if (in_array(class_basename($returnType->getName()), ['BelongsTo', 'BelongsToMany'])) {
                    $relName = $reflectionMethod->getName();
                    $modelRel = (new $reflectionMethod->class())->$relName()->getRelated();
                    $typeList[$reflectionMethod->getName().'_id']['relation'] = method_exists($modelRel,'forSelect') ?
                        $modelRel->forSelect()
                    :
                        $modelRel->pluck('name', 'id');
                    if(in_array(class_basename($returnType->getName()), ['BelongsToMany'])) {
                        $typeList[$reflectionMethod->getName().'_id']['multiple'] = true;
                    }
                }
            }
        }
        $fields = [];
        $custom_fields = $model->createWithRel;
        $fillables = !empty($custom_fields)?
            array_merge($model->getFillable(),array_keys($custom_fields))
            :
            $model->getFillable();
        foreach ($fillables as $modelType) {
            if (!empty($custom_fields) &&array_key_exists($modelType,$custom_fields)) {
                $fields[$modelType] = array_merge($typeList[$modelType],$custom_fields[$modelType]);
            } else {
                $fields[$modelType] = $typeList[$modelType];
            }
        }
        return $fields;
    }
}
