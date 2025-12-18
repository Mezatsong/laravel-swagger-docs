<?php

namespace Mezatsong\SwaggerDocs\Definitions;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use ReflectionClass;

/**
 * Class DefinitionGenerator
 *
 * @package Mezatsong\SwaggerDocs\Definitions
 */
class DefinitionGenerator
{
    /**
     * array of models
     *
     * @var array
     */
    protected array $models = [];

    /**
     * DefinitionGenerator constructor.
     */
    public function __construct(array $ignoredModels = [])
    {
        $this->models = collect(File::allFiles(app_path()))
            ->map(function ($item) {
                /**
                 * @var object
                 */
                $containerInstance = Container::getInstance();
                $path = $item->getRelativePathName();
                $class = sprintf(
                    '\%s%s',
                    $containerInstance->getNamespace(),
                    strtr(substr($path, 0, strrpos($path, '.')), '/', '\\')
                );

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
     *
     * @return array
     */
    public function generateSchemas(): array
    {
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
                        fn ($method) => !empty($method->getReturnType()) &&
                            str_contains(
                                $method->getReturnType(),
                                "Illuminate\Database\Eloquent\Relations",
                                // \Illuminate\Database\Eloquent\Relations::class
                            )
                    )
                    ->pluck('name')
                    ->all();

                $table = $obj->getTable();
                $columns = Schema::getColumns($table);

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

                foreach ($columns as $column) {
                    $swaggerProps = $this->convertDBTypeToSwaggerType($column['type']);

                    $description = $column['comment'];
                    if (!is_null($description)) {
                        $swaggerProps['description'] = "$description";
                    }

                    $this->addExampleKey($column);

                    if (!$column['nullable']) {
                        $required[] = $column['name'];
                    }

                    $properties[$column['name']] = $swaggerProps;
                }

                foreach ($relations as $relationName) {
                    $relatedClass = get_class($obj->{$relationName}()->getRelated());

                    if (str_starts_with($relatedClass, 'App\\')) {
                        $refObject = [
                            'type' => 'object',
                            '$ref' => '#/components/schemas/' . last(explode('\\', $relatedClass)),
                        ];
                    } else {
                        $refObject = [
                            'type'        => 'object',
                            'description' => '#/components/schemas/' . last(explode('\\', $relatedClass)),
                        ];
                    }

                    $resultsClass = get_class((object) ($obj->{$relationName}()->getResults()));

                    if (str_contains($resultsClass, \Illuminate\Database\Eloquent\Collection::class)) {
                        $properties[$relationName] = [
                            'type' => 'array',
                            'items'=> $refObject,
                        ];
                    } else {
                        $properties[$relationName] = $refObject;
                    }
                }

                // $required = array_merge($required, $with->getValue($obj));

                foreach ($appends->getValue($obj) as $item) {
                    $methodeName = 'get' . ucfirst(Str::camel($item)) . 'Attribute';
                    if (!$reflection->hasMethod($methodeName)) {
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
                            $data = ['type' => 'object'];
                            if (is_subclass_of($type, Model::class) && str_starts_with($relatedClass, 'App\\')) {
                                $data['$ref'] = '#/components/schemas/' . last(explode('\\', $type));
                            }
                        } else {
                            $data = $this->convertPhpTypeToSwaggerType($type);
                            $this->addExampleKey($data);
                        }
                    } else {
                        $data = ['type' => 'object', 'nullable' => true];
                    }

                    $properties[$item] = $data;

                    if ($returnType && false == $returnType->allowsNull() && !in_array($item, $required ?? [])) {
                        $required[] = $item;
                    }
                }

                $definition = [
                    'type'       => 'object',
                    'properties' => (object) $properties,
                ];

                if (!empty($required)) {
                    $definition['required'] = $required;
                }

                $schemas[$this->getModelName($obj)] = $definition;
            }
        }

        return $schemas;
    }

    /**
     * Get array of models
     *
     * @return array array of models
     */
    public function getModels(): array
    {
        return $this->models;
    }

    private function getModelName($model): string
    {
        return last(explode('\\', get_class($model)));
    }

    private function addExampleKey(array &$property): void
    {
        if (Arr::has($property, 'type')) {
            switch ($property['type']) {
                case 'bigserial':
                case 'bigint':
                    Arr::set($property, 'example', rand(1000000000000000000, 9200000000000000000));
                    break;
                case 'serial':
                case 'integer':
                case 'int':
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
                case 'bool':
                case 'boolean':
                    Arr::set($property, 'example', rand(0, 1) == 0);
                    break;
                default:
                    # code...
                    break;
            }
        }
    }

    private function cleanDatabaseType(string $dbType)
    {
        $cleanedType = preg_replace('/\([^)]+\)/', '', strtolower($dbType));
        $cleanedType = explode(' ', $cleanedType)[0];
        $cleanedType = preg_replace('/\s+(unsigned|null|unique|default|current_timestamp|primary key|serial|auto_increment)/i', '', $cleanedType);
        return trim($cleanedType);
    }

    /**
     * @return array array of with 'type' and 'format' as keys
     */
    private function convertDBTypeToSwaggerType(string $dbType): array
    {   
        $lowerType = $dbType == 'tinyint(1)' 
            ? 'boolean' // tinyint(1) is a boolean
            : $this->cleanDatabaseType($dbType); // We use preg_replace to remove parenthesis, eg: "int(20)" become just "int"
            
        switch ($lowerType) {
            case 'bigserial':
            case 'bigint':
                $property = [
                    'type'   => 'integer',
                    'format' => 'int64',
                ];
                break;
            case 'serial':
            case 'int':
            case 'integer':
            case 'mediumint':
            case 'smallint':
            case 'tinyint':
            case 'year':
                $property = ['type' => 'integer'];
                break;
            case 'float':
                $property = [
                    'type'   => 'number',
                    'format' => 'float',
                ];
                break;
            case 'decimal':
            case 'double':
            case 'real':
                $property = [
                    'type'   => 'number',
                    'format' => 'double',
                ];
                break;
            case 'boolean':
                $property = ['type' => 'boolean'];
                break;
            case 'date':
                $property = [
                    'type'   => 'string',
                    'format' => 'date',
                ];
                break;
            case 'datetime':
            case 'timestamp':
                $property = [
                    'type'   => 'string',
                    'format' => 'date-time',
                ];
                break;
            case 'binary':
            case 'varbinary':
            case 'blob':
                $property = [
                    'type'   => 'string',
                    'format' => 'binary',
                ];
                break;
            case 'string':
            case 'text':
            case 'mediumtext':
            case 'longtext':
            case 'varchar':
                $property = ['type' => 'string'];
                break;
            case 'time':
            case 'char':
                $property = ['type' => 'string', 'description' => $dbType];
                break;
            case 'enum':
                $property = ['type' => 'string'];
                break;
            case 'set':
            default:
                $property = [
                    'type'                 => 'object',
                    'nullable'             => true,
                    'additionalProperties' => true,
                    'description'          => $dbType,
                ];
                break;
        }

        if (!isset($property['format']) && in_array($dbType, ['boolean', 'enum', 'object'])) {
            $property['format'] = $dbType;
        }

        return $property;
    }

    /**
     * @return array array of with 'type' and 'format' as keys
     */
    private function convertPhpTypeToSwaggerType(string $phpType): array
    {
        $mapping = [
            'int' => [
                'type'   => 'integer',
                'format' => 'int32',
            ],
            'float' => [
                'type'   => 'number',
                'format' => 'float',
            ],
            'string' => [
                'type' => 'string',
            ],
            'bool' => [
                'type' => 'boolean',
            ],
            'array' => [
                'type'  => 'array',
                'items' => [
                    'type'                 => 'object',
                    'nullable'             => true,
                    'additionalProperties' => true,
                ],
            ],
            'object' => [
                'type'                 => 'object',
                'additionalProperties' => true,
            ],
            '?int' => [
                'type'     => 'integer',
                'nullable' => true,
            ],
            '?float' => [
                'type'     => 'number',
                'format'   => 'float',
                'nullable' => true,
            ],
            '?string' => [
                'type'     => 'string',
                'nullable' => true,
            ],
            '?bool' => [
                'type'     => 'boolean',
                'nullable' => true,
            ],
            '?array' => [
                'type'  => 'array',
                'items' => [
                    'type'     => 'object',
                    'nullable' => true,
                ],
                'nullable' => true,
            ],
            '?object' => [
                'type'     => 'object',
                'nullable' => true,
            ],
        ];

        return $mapping[strtolower($phpType)] ?? [
            'type'     => 'object',
            'nullable' => true,
        ];
    }
}
