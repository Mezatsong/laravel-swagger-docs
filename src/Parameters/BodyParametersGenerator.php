<?php namespace Mezatsong\SwaggerDocs\Parameters;

use Illuminate\Support\Arr;
use Mezatsong\SwaggerDocs\Parameters\Traits\GeneratesFromRules;
use Mezatsong\SwaggerDocs\Parameters\Interfaces\ParametersGenerator;

/**
 * Class BodyParametersGenerator
 * @package Mezatsong\SwaggerDocs\Parameters
 */
class BodyParametersGenerator implements ParametersGenerator {
    use GeneratesFromRules;

    /**
     * Rules array
     * @var array
     */
    protected array $rules;

    /**
     * Parameters location
     * @var string
     */
    protected string $location = 'body';

    /**
     * BodyParametersGenerator constructor.
     * @param array $rules
     */
    public function __construct(array $rules) {
        $this->rules = $rules;
    }

    /**
     * Get parameters
     * @return array[]
     */
    public function getParameters(): array {
        $required = [];
        $properties = [];

        $schema = [];

        foreach ($this->rules as $parameter => $rule) {
            $parameterRules = $this->splitRules($rule);
            $nameTokens = explode('.', $parameter);
            $this->addToProperties($properties,  $nameTokens, $parameterRules);

            if ($this->isParameterRequired($parameterRules)) {
                $required[] = $parameter;
            }
        }

        if (\count($required) > 0) {
            Arr::set($schema, 'required', $required);
        }

        Arr::set($schema, 'properties', $properties);
        return [
            'content'               =>  [
                'application/json'  =>  [
                    'schema'        =>  $schema
                ]
            ]
        ];
    }

    /**
     * @inheritDoc
     * @return string
     */
    public function getParameterLocation(): string {
        return $this->location;
    }

    /**
     * Add data to properties array
     * @param array $properties
     * @param array $nameTokens
     * @param array $rules
     */
    protected function addToProperties(array & $properties, array $nameTokens, array $rules): void {
        if (\count($nameTokens) === 0) {
            return;
        }

        $name = array_shift($nameTokens);

        if (!empty($nameTokens)) {
            $type = $this->getNestedParameterType($nameTokens);
        } else {
            $type = $this->getParameterType($rules);
        }

        if ($name === '*') {
            $name = 0;
        }

        if (!Arr::has($properties, $name)) {
            $propertyObject = $this->createNewPropertyObject($type, $rules);
            Arr::set($properties, $name, $propertyObject);
        } else {
            Arr::set($properties, $name . '.type', $type);
        }

        if ($type === 'array') {
            $this->addToProperties($properties[$name]['items'], $nameTokens, $rules);
        } else if ($type === 'object') {
            $this->addToProperties($properties[$name]['properties'], $nameTokens, $rules);
        }
    }

    /**
     * Get nested parameter type
     * @param array $nameTokens
     * @return string
     */
    protected function getNestedParameterType(array $nameTokens): string {
        if (current($nameTokens) === '*') {
            return 'array';
        }
        return 'object';
    }

    /**
     * Create new property object
     * @param string $type
     * @param array $rules
     * @return string[]
     */
    protected function createNewPropertyObject(string $type, array $rules): array {
        $propertyObject = [
            'type'      =>  $type,
        ];

        if ($enums = $this->getEnumValues($rules)) {
            Arr::set($propertyObject, 'enum', $enums);
        }

        if ($type === 'array') {
            Arr::set($propertyObject, 'items', []);
        } else if ($type === 'object') {
            Arr::set($propertyObject, 'properties', []);
        }

        return $propertyObject;
    }
}
