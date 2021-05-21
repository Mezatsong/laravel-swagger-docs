<?php namespace Mezatsong\SwaggerDocs\Commands;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputArgument;

/**
 * Class MakeSwaggerSchemaBuilder
 * @package Mezatsong\SwaggerDocs\Commands
 */
class MakeSwaggerSchemaBuilder extends GeneratorCommand {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'swagger:make-schema-builder';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new Swagger schema builder';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'SchemaBuilder';

    /**
     * @inheritDoc
     */
    protected function getStub()
    {
        return __DIR__ . '/../stubs/SchemaBuilder.stub';
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the class to be generated'],
        ];
    }

    /**
     * Get the default namespace for the class.
     *
     * @param string $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace.'\Swagger\SchemaBuilders';
    }
}
