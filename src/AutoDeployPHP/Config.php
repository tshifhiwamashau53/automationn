<?php
namespace AutoDeployPHP;

class Config
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public static function load(string $path): self
    {
        if (!file_exists($path)) {
            throw new \RuntimeException("Config file not found: $path");
        }
        $data = require $path;
        if (!is_array($data)) {
            throw new \RuntimeException("Config file must return an array.");
        }
        return new self($data);
    }

    public function get(string $key, $default = null)
    {
        $parts = explode('.', $key);
        $value = $this->config;
        foreach ($parts as $p) {
            if (!is_array($value) || !array_key_exists($p, $value)) {
                return $default;
            }
            $value = $value[$p];
        }
        return $value;
    }

    public function all(): array
    {
        return $this->config;
    }
}
