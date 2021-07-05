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
                    switch (config('database.default')) {
                        case 'sqlite':
                        case 'mysql':
                            $type = 'integer';
                            break;
                        default:
                            $type = 'boolean';
                            break;
                    }
                    break;
                case 'float':
                    $type = 'float';
                    break;
                default:
                    $type = 'mixed';
                    break;
            }
            $typeList[$name] = [
                'type'  =>  $type,
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
                    $typeList[$reflectionMethod->getName().'_id']['relation'] = $modelRel->forSelect();
                }
            }
        }
        $fields = [];
        foreach ($model->getFillable() as $modelType) {
            $fields[$modelType] = $typeList[$modelType];
        }
        return $fields;
    }
}
