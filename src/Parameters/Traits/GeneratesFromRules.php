<?php namespace Mezatsong\SwaggerDocs\Parameters\Traits;

use Illuminate\Support\Str;

/**
 * Trait GeneratesFromRules
 * @package Mezatsong\SwaggerDocs\Parameters\Traits
 */
trait GeneratesFromRules {

    /**
     * Split rules
     * @param string|array $rules
     * @return array
     */
    protected function splitRules($rules): array {
        if (is_string($rules)) {
            return explode('|', $rules);
        }
        return $rules;
    }

    /**
     * Get parameter type
     * @param array $parameterRules
     * @return string
     */
    protected function getParameterType(array $parameterRules): string {
        if (in_array('integer', $parameterRules)) {
            return 'integer';
        } elseif (in_array('numeric', $parameterRules)) {
            return 'number';
        } elseif (in_array('boolean', $parameterRules)) {
            return 'boolean';
        } elseif (in_array('array', $parameterRules)) {
            return 'array';
        } else {
            return 'string';
        }
    }

    /**
     * Check whether parameter is required
     * @param array $parameterRules
     * @return bool
     */
    protected function isParameterRequired(array $parameterRules): bool {
        return in_array('required', $parameterRules);
    }

    /**
     * Check whether parameter is of Array type
     * @param string $parameter
     * @return bool
     */
    protected function isArrayParameter(string $parameter): bool {
        return Str::contains($parameter, '*');
    }

    /**
     * Get array key
     * @param string $parameter
     * @return mixed|string
     */
    protected function getArrayKey(string $parameter) {
        return current(explode('.', $parameter));
    }

    /**
     * Get values for Enum
     * @param array $parameterRules
     * @return array
     */
    protected function getEnumValues(array $parameterRules): array {
        $in = $this->getInParameter($parameterRules);
        if (!$in) {
            return [];
        }
        [$_parameter, $values] = explode(':', $in);
        return explode(',', str_replace('"', '', $values));
    }

    /**
     * Get default value for rule
     * @param array $parameterRules
     * @return string|null
     */
    protected function getDefaultValue(array $parameterRules): ?string {
        foreach ($parameterRules as $rule) {
            if (Str::startsWith($rule, 'swagger_default')) {
                [$key, $value] = explode(':', $rule);
                return trim($value);
            }
        }
        return null;
    }

    /**
     * Get min value
     * @param array $parameterRules
     * @return string|null
     */
    protected function getMinValue(array $parameterRules) {
        foreach ($parameterRules as $rule) {
            if (Str::startsWith($rule, 'swagger_min')) {
                [$key, $value] = explode(':', $rule);
                return trim($value);
            }
        }
        return null;
    }

    /**
     * Get min value
     * @param array $parameterRules
     * @return string|null
     */
    protected function getMaxValue(array $parameterRules) {
        foreach ($parameterRules as $rule) {
            if (Str::startsWith($rule, 'swagger_max')) {
                [$key, $value] = explode(':', $rule);
                return trim($value);
            }
        }
        return null;
    }

    /**
     * Get the 'in:' parameter
     * @param array $parameterRules
     * @return false|mixed|string
     */
    private function getInParameter(array $parameterRules) {
        foreach ($parameterRules as $rule) {
            if ((is_string($rule) || method_exists($rule, '__toString')) && Str::startsWith($rule, 'in:')) {
                return $rule;
            }
        }
        return false;
    }

}
