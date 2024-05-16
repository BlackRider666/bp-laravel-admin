<?php


namespace BlackParadise\LaravelAdmin\Core;


use Doctrine\DBAL\Types\Type;
use ErrorException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use ReflectionClass;
use ReflectionMethod;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Types;

class TypeFromTable
{
    /**
     * @param Model $model
     * @return array
     */
    public function getTypeList(Model $model): array
    {
        $table = $model->getConnection()->getTablePrefix() . $model->getTable();
        $config = config('database.connections.mysql');
        $connectionParams = [
            'dbname' => $config['database'],
            'user' => $config['username'],
            'password' => $config['password'],
            'host' => $config['host'],
            'driver' => 'pdo_mysql',
            'charset' => $config['charset'],
        ];
        $conn = DriverManager::getConnection($connectionParams);
        $schema = $conn->createSchemaManager();
        $columns = $schema->listTableColumns($table);
        $typeList = [];
        $casts = $model->getCasts();
        foreach ($columns as $column) {
            $name = $column->getName();
            $type = Type::getTypeRegistry()->lookupName($column->getType());
            switch ($type) {
                case Types::STRING:
                case Types::TEXT:
                case Types::DATE_MUTABLE:
                case Types::TIME_MUTABLE:
                case Types::GUID:
                case Types::JSON:
                case Types::DATETIMETZ_MUTABLE:
                case Types::DATETIME_MUTABLE:
                    $type = 'string';
                    break;
                case Types::INTEGER:
                case Types::BIGINT:
                case Types::SMALLINT:
                    $type = 'integer';
                    break;
                case Types::BOOLEAN:
                    $type = 'boolean';
                    break;
                case Types::FLOAT:
                case Types::DECIMAL:
                    $type = 'float';
                    break;
                default:
                    $type = 'mixed';
                    break;
            }

            $typeList[$name] = [
                'type' => array_key_exists($name, $casts) ? $casts[$name] : $type,
                'required' => $column->getNotnull(),
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
