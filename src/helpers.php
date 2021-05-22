<?php

use Illuminate\Support\Facades\File;

if (!function_exists('at_least_one_in_array')) {
    function at_least_one_in_array(array $items, array $haystack, bool $strict = false): bool {
        foreach($items as $item) {
            if (in_array($item, $haystack, $strict)) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('all_in_array')) {
    function all_in_array(array $items, array $haystack, bool $strict = false): bool {
        foreach($items as $item) {
            if (!in_array($item, $haystack, $strict)) {
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('strip_optional_char')) {
    function strip_optional_char(string $uri): string {
        return str_replace('?', '', $uri);
    }
}

if (!function_exists('swagger_resolve_documentation_file_path')) {
    function swagger_resolve_documentation_file_path(): string {
        $documentationFilePrefix = config('swagger.storage', storage_path('swagger')) . DIRECTORY_SEPARATOR . 'swagger.';
        $documentationFile = '';
        if (File::exists($documentationFilePrefix . 'json')) {
            $documentationFile = $documentationFilePrefix . 'json';
        } else {
            if (File::exists($documentationFilePrefix . 'yaml')) {
                $documentationFile = $documentationFilePrefix . 'yaml';
            }
        }
        return $documentationFile;
    }
}

if (!function_exists('swagger_is_connection_secure')) {
    function swagger_is_connection_secure(): bool {
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
            return true;
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' || !empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on') {
            return true;
        }
        return false;
    }
}

