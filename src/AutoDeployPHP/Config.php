<?php
namespace AutoDeployPHP;

class Config
{
    /** @var array<string,mixed> */
    private array $config;

    /**
     * @param array<string,mixed> $config
     */
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

    /**
     * Get a config value using dot notation.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
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

    /**
     * @return array<string,mixed>
     */
    public function all(): array
    {
        return $this->config;
    }
}
