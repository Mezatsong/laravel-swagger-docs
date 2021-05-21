<?php namespace Mezatsong\SwaggerDocs;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use Mezatsong\SwaggerDocs\Commands\GenerateSwaggerDocumentation;
use Mezatsong\SwaggerDocs\Commands\MakeSwaggerSchemaBuilder;

/**
 * Class SwaggerServiceProvider
 * @package Mezatsong\SwaggerDocs
 */
class SwaggerServiceProvider extends ServiceProvider {

    /**
     * @inheritDoc
     * @return void
     */
    public function boot(): void {
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateSwaggerDocumentation::class,
                MakeSwaggerSchemaBuilder::class
            ]);
        }

        $source = __DIR__ . '/../config/swagger.php';

        $this->publishes([
            $source => config_path('swagger.php')
        ]);

        $viewsPath = __DIR__ . '/../resources/views';
        $this->loadViewsFrom($viewsPath, 'swagger');
        $translationsPath = __DIR__ . '/../resources/lang';

        $this->publishes([
            $viewsPath => config('swagger.views', base_path('resources/views/vendor/swagger')),
            $translationsPath => config('swagger.translations', base_path('resources/lang/vendor/swagger'))
        ]);

        $this->loadRoutesFrom(__DIR__ . DIRECTORY_SEPARATOR . 'routes.php');

        $this->mergeConfigFrom(
            $source, 'swagger'
        );

        $this->loadValidationRules();

        DB::getDoctrineSchemaManager()->getDatabasePlatform()->registerDoctrineTypeMapping('enum', 'string');

        if (file_exists($file = __DIR__ . '/helpers.php')) {
            require $file;
        }
    }

    /**
     * Load custom validation rules
     * @return void
     */
    private function loadValidationRules(): void {
        Validator::extend('swagger_default', function(string $attribute, $value, array $parameters, Validator $validator) {
            return true;
        });
        Validator::extend('swagger_min', function(string $attribute, $value, array $parameters, Validator $validator) {
            [$min, $fail] = $this->parseParameters($parameters);
            $valueType = $this->getTypeFromString((string) $value);
            settype($min, $valueType);
            if ($fail) {
                return $value >= $min;
            }
            return true;
        });
        Validator::extend('swagger_max', function(string $attribute, $value, array $parameters, Validator $validator) {
            [$max, $fail] = $this->parseParameters($parameters);
            $valueType = $this->getTypeFromString((string) $value);
            settype($max, $valueType);
            if ($fail) {
                return $value <= $max;
            }
            return true;
        });
    }

    /**
     * Get variable type from string
     * @param string $string
     * @return string
     */
    private function getTypeFromString(string $string): string {
        return gettype($this->guessVariableType($string));
    }

    /**
     * Guess variable type
     * @param string $string
     * @return bool|float|int|string
     */
    private function guessVariableType(string $string) {
        $string = trim($string);
        if (empty($string)) {
            return '';
        }
        if (!preg_match('/[^0-9.]+/', $string)) {
            if (preg_match('/[.]+/', $string)) {
                return (double) $string;
            }
            return (integer) $string;
        }
        if ($string === 'true') {
            return (boolean) true;
        }
        if ($string === 'false') {
            return (boolean) false;
        }
        return (string) $string;
    }

    /**
     * Parse parameter
     * @param array $parameters
     * @return array
     */
    private function parseParameters(array $parameters): array {
        $parameter = Arr::first($parameters);
        $exploded = explode(':', $parameter);
        $value = $exploded[0];
        if (\count($exploded) === 2) {
            return [
                $value,
                isset($exploded[1]) ? $exploded[1] === 'fail' : false
            ];
        }
        return [$value, false];
    }

}
