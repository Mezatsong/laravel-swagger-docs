<?php namespace Mezatsong\SwaggerDocs\Definitions;

use ReflectionClass;
use Illuminate\Support\Collection;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Str;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use phpDocumentor\Reflection\DocBlockFactory;

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
                $with = $reflection->getProperty('with');
                $with->setAccessible(true);

                $table = $obj->getTable();
                $list = Schema::getColumnListing($table);
                $list = array_diff($list, $obj->getHidden());

                $properties = [];
                $required = [];

                foreach ($list as $item) {

                    /**
                    * @var object
                    */
                    $conn = DB::connection();

                    /**
                     * @var \Doctrine\DBAL\Schema\Column
                     */
                    $column = $conn->getDoctrineColumn($table, $item);

                    $data = [
                        'type' => $column->getType()->getName()
                    ];

                    $description = $column->getComment();
                    if (!is_null($description)) {
                        $data['description'] = $description;
                    }

                    $default = $column->getDefault();
                    if (!is_null($default)) {
                        $data['default'] = $default;
                    }

                    $this->addExampleKey($data);

                    $properties[$item] = $data;

                    if ($column->getNotnull()) {
                        $required[] = $item;
                    }
                }

                foreach ($with->getValue($obj) as $item) {
                    $class = get_class($obj->{$item}()->getModel());
                    $properties[$item] = [
                        'type' => 'object',
                        '$ref' => '#/components/schemas/' . last(explode('\\', $class)),
                    ];
                }

                $definition = [
                    'type' => 'object',
                    'required' => $required,
                    'properties' => $properties,
                ];

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
}
