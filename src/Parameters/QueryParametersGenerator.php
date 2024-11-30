<?php namespace Mezatsong\SwaggerDocs\Parameters;

use Illuminate\Support\Arr;
use Mezatsong\SwaggerDocs\Parameters\Traits\GeneratesFromRules;
use Mezatsong\SwaggerDocs\Parameters\Interfaces\ParametersGenerator;

/**
 * Class QueryParametersGenerator
 * @package Mezatsong\SwaggerDocs\Parameters
 */
class QueryParametersGenerator implements ParametersGenerator {
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
    protected string $location = 'query';

    /**
     * QueryParametersGenerator constructor.
     * @param array $rules
     */
    public function __construct(array $rules) {
        $this->rules = $rules;
    }

    /**
     * @inheritDoc
     * @return array
     */
    public function getParameters(): array {
        $parameters = [];
        $arrayTypes = [];

        foreach ($this->rules as $parameter => $rule) {
            $parameterRules = $this->splitRules($rule);
            $enums = $this->getEnumValues($parameterRules);
            $type = $this->getParameterType($parameterRules);
            $default = $this->getDefaultValue($parameterRules);
            $min = $this->getMinValue($parameterRules);
            $max = $this->getMaxValue($parameterRules);

            if ($this->isArrayParameter($parameter)) {
                $key = $this->getArrayKey($parameter);
                $arrayTypes[$key] = $type;
                continue;
            }
            $parameter = $this->formatParameterName($parameter);

            $parameterObject = [
                'in'            =>  $this->getParameterLocation(),
                'name'          =>  $parameter,
                'description'   =>  '',
                'required'      =>  $this->isParameterRequired($parameterRules)
            ];

            if (\count($enums) > 0) {
                Arr::set($parameterObject, 'schema.type', 'string');
                Arr::set($parameterObject, 'schema.enum', $enums);
            } else {
                Arr::set($parameterObject, 'schema.type', $type);
            }

            if ($default) {
                settype($default, $type);
                Arr::set($parameterObject, 'schema.default', $default);
            }
            if ($min) {
                settype($min, $type);
                Arr::set($parameterObject, 'schema.minimum', $min);
            }
            if ($max) {
                settype($max, $type);
                Arr::set($parameterObject, 'schema.maximum', $max);
            }

            if ($type === 'array') {
                Arr::set($parameterObject, 'items', [
                    'type'  =>  'string'
                ]);
            }
            Arr::set($parameters, $parameter, $parameterObject);
        }

        $parameters = $this->addArrayTypes($parameters, $arrayTypes);
        return array_values($parameters);
    }

    /**
     * @inheritDoc
     * @return string
     */
    public function getParameterLocation(): string {
        return $this->location;
    }

    /**
     * Add array types
     * @param array $parameters
     * @param array $arrayTypes
     * @return array
     */
    protected function addArrayTypes(array $parameters, array $arrayTypes): array {
        foreach ($arrayTypes as $key => $type) {
            if (!isset($parameters[$key])) {
                $parameters[$key] = [
                    'name'          =>  $key,
                    'in'            =>  $this->getParameterLocation(),
                    'type'          =>  'array',
                    'required'      =>  false,
                    'description'   =>  '',
                    'items'         =>  [
                        'type'      =>  $type
                    ]
                ];
            } else {
                $parameters[$key]['type'] = 'array';
                $parameters[$key]['items']['type'] = $type;
            }
        }
        return $parameters;
    }

    /**
     * @param string $parameter
     * @return string
     */
    protected function formatParameterName(string $parameter): string {
        if (!strpos($parameter, '.')) {
            return $parameter;
        }
        $parts = explode('.', $parameter);
        for ($i = 1; $i < count($parts); $i++) {
            $parts[$i] = "[$parts[$i]]";
        }

        return implode('', $parts);
    }

}
