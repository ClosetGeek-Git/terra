<?php

namespace Terra\Config;

/**
 * Configuration Manager for Terra framework
 * 
 * Manages configuration options for Janus Gateway connection and plugins
 */
class ConfigManager
{
    /**
     * @var array Configuration storage
     */
    private $config = [];

    /**
     * @var array Default configuration values
     */
    private $defaults = [
        'janus' => [
            'admin_address' => 'tcp://localhost:7889',
            'timeout' => 30,
        ],
        'zmq' => [
            'persistent' => true,
            'linger' => 0,
        ],
        'logging' => [
            'enabled' => true,
            'level' => 'info',
            'path' => null,
        ],
    ];

    /**
     * Constructor
     * 
     * @param array $config Initial configuration
     */
    public function __construct(array $config = [])
    {
        $this->config = array_replace_recursive($this->defaults, $config);
    }

    /**
     * Get a configuration value
     * 
     * @param string $key Configuration key (dot notation supported)
     * @param mixed $default Default value if key not found
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Set a configuration value
     * 
     * @param string $key Configuration key (dot notation supported)
     * @param mixed $value Value to set
     * @return void
     */
    public function set(string $key, $value): void
    {
        $keys = explode('.', $key);
        $config = &$this->config;

        foreach ($keys as $i => $k) {
            if ($i === count($keys) - 1) {
                $config[$k] = $value;
            } else {
                if (!isset($config[$k]) || !is_array($config[$k])) {
                    $config[$k] = [];
                }
                $config = &$config[$k];
            }
        }
    }

    /**
     * Check if a configuration key exists
     * 
     * @param string $key Configuration key
     * @return bool
     */
    public function has(string $key): bool
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return false;
            }
            $value = $value[$k];
        }

        return true;
    }

    /**
     * Get all configuration
     * 
     * @return array
     */
    public function all(): array
    {
        return $this->config;
    }

    /**
     * Merge configuration
     * 
     * @param array $config Configuration to merge
     * @return void
     */
    public function merge(array $config): void
    {
        $this->config = array_replace_recursive($this->config, $config);
    }
}
