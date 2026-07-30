<?php

namespace AutoDeployPHP;

class Config
{
    private array $data;

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    // Convenience loader: accepts a path to a PHP config file or an array
    public static function load($source): Config
    {
        if (is_string($source)) {
            if (!file_exists($source)) {
                throw new \InvalidArgumentException("Config file not found: $source");
            }
            $data = include $source;
            if (!is_array($data)) {
                throw new \InvalidArgumentException("Config file must return an array: $source");
            }
            return new Config($data);
        }

        if (is_array($source)) {
            return new Config($source);
        }

        throw new \InvalidArgumentException('Unsupported config source; provide filename or array');
    }

    // Support dot notation keys like 'deployment.deploy_to'
    public function get(string $key, $default = null)
    {
        if ($key === '') {
            return $default;
        }
        $parts = explode('.', $key);
        $value = $this->data;
        foreach ($parts as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }

    public function set(string $key, $value): void
    {
        $parts = explode('.', $key);
        $ref = &$this->data;
        foreach ($parts as $part) {
            if (!isset($ref[$part]) || !is_array($ref[$part])) {
                $ref[$part] = [];
            }
            $ref = &$ref[$part];
        }
        $ref = $value;
    }

    public function all(): array
    {
        return $this->data;
    }
}
