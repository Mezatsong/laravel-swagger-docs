<?php 

namespace Mezatsong\SwaggerDocs\Definitions;

use Doctrine\DBAL\Types\Type;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use ReflectionClass;

/**
 * Class DefinitionGenerator
 * @package Mezatsong\SwaggerDocs\Definitions
 */
class DefinitionGenerator {


    /**
     * array of models
     * @var array
     */
    protected array $models = [];


    /**
     * DefinitionGenerator constructor.
     */
    public function __construct(array $ignoredModels = []) {

        $this->models = collect(File::allFiles(app_path()))
            ->map(function ($item) {
                /**
                 * @var object
                 */
                $containerInstance = Container::getInstance();
                $path = $item->getRelativePathName();
                $class = sprintf('\%s%s',
                    $containerInstance->getNamespace(),
                    strtr(substr($path, 0, strrpos($path, '.')), '/', '\\'));

                return $class;
            })
            ->filter(function ($class) {
                $valid = false;

                if (class_exists($class)) {
                    $reflection = new ReflectionClass($class);
                    $valid = $reflection->isSubclassOf(Model::class) &&
                        !$reflection->isAbstract();
                }

                return $valid;
            })
            ->diff($ignoredModels)
            ->values()
            ->toArray();
    }


    /**
     * Generate definitions informations
     * @return array
     */
    function generateSchemas(): array {
        $schemas = [];

        foreach ($this->models as $model) {
            /** @var Model $model */
            $obj = new $model();

            if ($obj instanceof Model) { //check to make sure it is a model
                $reflection = new ReflectionClass($obj);
                
                // $with = $reflection->getProperty('with');
                // $with->setAccessible(true);

                $appends = $reflection->getProperty('appends');
                $appends->setAccessible(true);

                $relations = collect($reflection->getMethods())
                    ->filter(
                        fn($method) => !empty($method->getReturnType()) &&
                            str_contains(
                                $method->getReturnType(), 
                                \Illuminate\Database\Eloquent\Relations::class
                            )
                    )
                    ->pluck('name')
                    ->all();

                $table = $obj->getTable();
                $list = Schema::connection($obj->getConnectionName())->getColumnListing($table);
                $list = array_diff($list, $obj->getHidden());

                $properties = [];
                $required = [];

                /**
                * @var \Illuminate\Database\Connection
                */
                $conn = $obj->getConnection();
                $prefix = $conn->getTablePrefix();

                if ($prefix !== '') {
                    $table = $prefix . $table;
                }

                foreach ($list as $item) {

                    /**
                     * @var \Doctrine\DBAL\Schema\Column
                     */
                    $column = $conn->getDoctrineColumn($table, $item);

                    $data = $this->convertDBalTypeToSwaggerType(
                        Type::getTypeRegistry()->lookupName($column->getType())
                    );

                    if ($data['type'] == 'string' && ($len = $column->getLength())) {
                        $data['description'] .= "($len)";
                    }

                    $description = $column->getComment();
                    if (!is_null($description)) {
                        $data['description'] .= ": $description";
                    }

                    $default = $column->getDefault();
                    if (!is_null($default)) {
                        $data['default'] = $default;
                    }
                    
                    $data['nullable'] = ! $column->getNotnull();

                    $this->addExampleKey($data);

                    $properties[$item] = $data;

                    if ($column->getNotnull()) {
                        $required[] = $item;
                    }
                }

                foreach ($relations as $relationName) {
                    $relatedClass = get_class($obj->{$relationName}()->getRelated());
                    $refObject = [
                        'type' => 'object',
                        '$ref' => '#/components/schemas/' . last(explode('\\', $relatedClass)),
                    ];
                    
                    $resultsClass = get_class((object) ($obj->{$relationName}()->getResults()));

                    if (str_contains($resultsClass, \Illuminate\Database\Eloquent\Collection::class)) {
                        $properties[$relationName] = [
                            'type' => 'array',
                            'items'=> $refObject
                        ];
                    } else {
                        $properties[$relationName] = $refObject;
                    }
                }
                
                // $required = array_merge($required, $with->getValue($obj));

                foreach ($appends->getValue($obj) as $item) {
                    $methodeName = 'get' . ucfirst(Str::camel($item)) . 'Attribute';
                    if ( ! $reflection->hasMethod($methodeName)) {
                        Log::warning("[Mezatsong\SwaggerDocs] Method $model::$methodeName not found while parsing '$item' attribute");
                        continue;
                    }
                    $reflectionMethod = $reflection->getMethod($methodeName);
                    $returnType = $reflectionMethod->getReturnType();

                    $data = [];

                    // A schema without a type matches any data type – numbers, strings, objects, and so on.
                    if ($reflectionMethod->hasReturnType()) {
                        $type = $returnType->getName();

                        if (Str::contains($type, '\\')) {
                            $data = [
                                'type' => 'object',
                                '$ref' => '#/components/schemas/' . last(explode('\\', $type)),
                            ];
                        } else {
                            $data['type'] = $type;
                            $this->addExampleKey($data);
                        }
                    }

                    $properties[$item] = $data;

                    if ($returnType && false == $returnType->allowsNull()) {
                        $required[] = $item;
                    }
                }

                $definition = [
                    'type' => 'object',
                    'properties' => (object) $properties,
                ];
                
                if ( ! empty($required)) {
                    $definition['required'] = $required;
                }

                $schemas[ $this->getModelName($obj) ] = $definition;
            }
        }

        return $schemas;
    }


    /**
     * Get array of models
     * @return array array of models
     */
    function getModels(): array {
        return $this->models;
    }


    private function getModelName($model): string {
        return last(explode('\\', get_class($model)));
    }


    private function addExampleKey(array & $property): void {
        if (Arr::has($property, 'type')) {
            switch ($property['type']) {
                case 'bigserial':
                case 'bigint':
                    Arr::set($property, 'example', rand(1000000000000000000, 9200000000000000000));
                    break;
                case 'serial':
                case 'integer':
                    Arr::set($property, 'example', rand(1000000000, 2000000000));
                    break;
                case 'mediumint':
                    Arr::set($property, 'example', rand(1000000, 8000000));
                    break;
                case 'smallint':
                    Arr::set($property, 'example', rand(10000, 32767));
                    break;
                case 'tinyint':
                    Arr::set($property, 'example', rand(100, 127));
                    break;
                case 'decimal':
                case 'float':
                case 'double':
                case 'real':
                    Arr::set($property, 'example', 0.5);
                    break;
                case 'date':
                    Arr::set($property, 'example', date('Y-m-d'));
                    break;
                case 'time':
                    Arr::set($property, 'example', date('H:i:s'));
                    break;
                case 'datetime':
                    Arr::set($property, 'example', date('Y-m-d H:i:s'));
                    break;
                case 'timestamp':
                    Arr::set($property, 'example', date('Y-m-d H:i:s'));
                    break;
                case 'string':
                    Arr::set($property, 'example', 'string');
                    break;
                case 'text':
                    Arr::set($property, 'example', 'a long text');
                    break;
                case 'boolean':
                    Arr::set($property, 'example', rand(0,1) == 0);
                    break;

                default:
                    # code...
                    break;
            }
        }
    }

    /**
     * @return array array of with 'type' and 'format' as keys
     */
    private function convertDBalTypeToSwaggerType(string $type): array {
        $lowerType = strtolower($type);
        switch ($lowerType) {
            case 'bigserial':
            case 'bigint':
                $property = [
                    'type' => 'integer', 
                    'format' => 'int64'
                ];
                break;
            case 'serial':
            case 'integer':
            case 'mediumint':
            case 'smallint':
            case 'tinyint':
            case 'tinyint':
            case 'year':
                $property = ['type' => 'integer'];
                break;
            case 'float':
                $property = [
                    'type' => 'number',
                    'format' => 'float'
                ];
                break;
            case 'decimal':
            case 'double':
            case 'real':
                $property = [
                    'type' => 'number',
                    'format' => 'double'
                ];
                break;
            case 'boolean':
                $property = ['type' => 'boolean'];
                break;
            case 'date':
                $property = [
                    'type' => 'string',
                    'format' => 'date',
                ];
                break;
            case 'datetime':
            case 'timestamp':
                $property = [
                    'type' => 'string',
                    'format' => 'date-time',
                ];
                break;
            case 'binary':
            case 'varbinary':
            case 'blob':
                $property = [
                    'type' => 'string',
                    'format' => 'binary',
                ];
                break;
            case 'time':
            case 'string':
            case 'text':
            case 'char':
            case 'varchar':
            case 'enum':
            case 'set':
            default:
                $property = ['type' => 'string'];
                break;
        }

        $property['description'] = $type;

        return $property;
    }
}
