<?php

error_reporting(E_ALL);
ini_set('display_startup_errors',true);
ini_set('display_errors', true);

ini_set(option: 'memory_limit', value: '4G');

if (!function_exists('config_get')) {

    function config_get(
        string|array $key_path,
        mixed $default=null,
        array|null $data=null,
        bool $allow_override=false,
    ): mixed
    {
        if (is_string($key_path))
        {
            $key_path = explode(
                separator: '.',
                string: $key_path
            );
        }

        if ($allow_override)
        {
            $search = end($key_path);
            global $argv;
            foreach ($argv as $index => $option)
            {
                if ($index < 2 || !str_contains(haystack: $option, needle: '=')) continue;

                [$argument, $value] = explode(
                    separator: '=',
                    string: $option,
                    limit: 2
                );

                if ($search == $argument) return $value;
            }
        }

        if ($data === null)
        {
            static $config = null;
            if ($config === null)
            {
                $config = require __DIR__ . '/config.php';
            }
            $data = $config;
        }

        if (count($key_path) === 1) return $data[$key_path[0]] ?? $default;

        $key = $key_path[0];
        unset($key_path[0]);

        if (!isset($data[$key])) return $default;

        return config_get(
            key_path: array_values($key_path),
            default: $default,
            data: $data[$key]
        );
    }
}

if (!function_exists('db'))
{
    function db(): PDO {
        static $database_connection = null;

        if ($database_connection === null)
        {
            $database_connection = new PDO(...config_get('db'));
        }

        return $database_connection;
    }
}

require_once __DIR__ . '/classes/classes.php';