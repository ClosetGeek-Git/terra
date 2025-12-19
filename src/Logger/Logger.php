<?php

namespace Terra\Logger;

use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\NullHandler;
use Psr\Log\LoggerInterface;
use Terra\Config\ConfigManager;

/**
 * Logger wrapper for Terra framework
 * 
 * Provides logging capabilities with Monolog
 */
class Logger
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Constructor
     * 
     * @param ConfigManager $config Configuration manager
     * @param string $name Logger name
     */
    public function __construct(ConfigManager $config, string $name = 'terra')
    {
        $this->logger = new MonologLogger($name);

        if ($config->get('logging.enabled', true)) {
            $level = $this->getLevelFromString($config->get('logging.level', 'info'));
            $path = $config->get('logging.path', 'php://stdout');
            
            $handler = new StreamHandler($path, $level);
            $this->logger->pushHandler($handler);
        } else {
            $this->logger->pushHandler(new NullHandler());
        }
    }

    /**
     * Get logger instance
     * 
     * @return LoggerInterface
     */
    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * Log debug message
     * 
     * @param string $message
     * @param array $context
     * @return void
     */
    public function debug(string $message, array $context = []): void
    {
        $this->logger->debug($message, $context);
    }

    /**
     * Log info message
     * 
     * @param string $message
     * @param array $context
     * @return void
     */
    public function info(string $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }

    /**
     * Log warning message
     * 
     * @param string $message
     * @param array $context
     * @return void
     */
    public function warning(string $message, array $context = []): void
    {
        $this->logger->warning($message, $context);
    }

    /**
     * Log error message
     * 
     * @param string $message
     * @param array $context
     * @return void
     */
    public function error(string $message, array $context = []): void
    {
        $this->logger->error($message, $context);
    }

    /**
     * Convert string level to Monolog level constant
     * 
     * @param string $level
     * @return int
     */
    private function getLevelFromString(string $level): int
    {
        $levels = [
            'debug' => MonologLogger::DEBUG,
            'info' => MonologLogger::INFO,
            'notice' => MonologLogger::NOTICE,
            'warning' => MonologLogger::WARNING,
            'error' => MonologLogger::ERROR,
            'critical' => MonologLogger::CRITICAL,
            'alert' => MonologLogger::ALERT,
            'emergency' => MonologLogger::EMERGENCY,
        ];

        return $levels[strtolower($level)] ?? MonologLogger::INFO;
    }
}
