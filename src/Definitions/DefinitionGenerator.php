<?php namespace Mezatsong\SwaggerDocs\Definitions;

use ReflectionClass;
use Illuminate\Support\Collection;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model;
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

                    $properties[$item] = $data;

                    if ($column->getNotnull()) {
                        $required[] = $item;
                    }
                }

                foreach ($with->getValue($obj) as $item) {
                    $class        = get_class($obj->{$item}()->getModel());
                    $properties[$item] = [
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
}
