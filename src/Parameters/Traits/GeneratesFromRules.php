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
     * Get parameter format
     * @param array $parameterRules
     * @return string
     */
    protected function getParameterExtra(string $type, array $parameterRules): array {

        $extra = [];

        if (in_array('nullable', $parameterRules)) {
            $extra['nullable'] = true;
        }

        if (in_array($type, ['numeric', 'integer'])) {
            foreach ($parameterRules as $rule) {
                if(is_object($rule)) continue;
                if (Str::startsWith($rule, 'min')) {
                    [$_, $value] = explode(':', $rule);
                    $extra['minimum'] = intval(trim($value));
                }

                if (Str::startsWith($rule, 'max')) {
                    [$_, $value] = explode(':', $rule);
                    $extra['maximum'] = intval(trim($value));
                }

                if (Str::startsWith($rule, 'multiple_of')) {
                    [$_, $value] = explode(':', $rule);
                    $extra['multipleOf'] = intval(trim($value));
                }
            }
        }

        if ($type == 'string') {
            foreach ($parameterRules as $rule) {
                if (!is_string($rule)) continue;
                if (Str::startsWith($rule, 'min')) {
                    [$_, $value] = explode(':', $rule);
                    $extra['minLength'] = intval(trim($value));
                }

                if (Str::startsWith($rule, 'max')) {
                    [$_, $value] = explode(':', $rule);
                    $extra['maxLength'] = intval(trim($value));
                }

                $formatMap = [
                    'byte' => ['date'],
                    'binary' => ['file', 'image', 'mimetypes', 'mimes'],
                    'date' => ['date'],
                    'password' => ['password'],

                    // custom, these are not OpenAPI built-in
                    'email' => ['email'],
                    'uuid' => ['uuid'],
                    'uri' => ['url'],
                    'ip' => ['ip'],
                    'ipv4' => ['ipv4'],
                    'ipv6' => ['ipv6'],
                    'json' => ['json']
                ];

                foreach($formatMap as $format => $formatRules) {
                    if (at_least_one_in_array($formatRules, $parameterRules)) {
                        $extra['format'] = $format;
                    }
                }

                if (!isset($extra['format'])) {
                    if (
                        Str::startsWith($rule, 'after') ||
                        Str::startsWith($rule, 'after_or_equal') ||
                        Str::startsWith($rule, 'before') ||
                        Str::startsWith($rule, 'before_or_equal')
                    ) {
                        $extra['format'] = 'date';
                    }
                }

                if (Str::startsWith($rule, 'regex')) {
                    [$_, $value] = explode(':', $rule);
                    $extra['pattern'] = trim($value);
                }
            }

        }

        return $extra;
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
