<?php

namespace Terra\Transport;

use React\EventLoop\LoopInterface;
use React\ZMQ\Context as ReactZMQContext;
use Terra\Config\ConfigManager;
use Terra\Logger\Logger;

/**
 * ZMQ Factory
 * 
 * Creates ZMQ context with fallback support for different implementations
 */
class ZmqFactory
{
    /**
     * Create a ZMQ context with implementation fallback
     * 
     * Tries implementations in this order:
     * 1. @ClosetGeek-Git/ReactPHPZMQ (if available)
     * 2. @friends-of-reactphp/zmq (if available)
     * 3. react/zmq (fallback)
     * 
     * @param LoopInterface $loop Event loop
     * @param ConfigManager $config Configuration
     * @param Logger $logger Logger instance
     * @return object ZMQ Context
     */
    public static function createContext(LoopInterface $loop, ConfigManager $config, Logger $logger)
    {
        $preferredImplementation = $config->get('zmq.preferred_implementation', 'auto');
        
        // Try preferred implementation first if specified
        if ($preferredImplementation !== 'auto') {
            $context = self::tryImplementation($preferredImplementation, $loop, $logger);
            if ($context !== null) {
                return $context;
            }
        }

        // Auto-detection: try implementations in priority order
        $implementations = [
            'ClosetGeek\\ReactPHPZMQ\\Context',
            'FriendsOfReactPHP\\ZMQ\\Context',
            'React\\ZMQ\\Context',
        ];

        foreach ($implementations as $class) {
            $context = self::tryImplementation($class, $loop, $logger);
            if ($context !== null) {
                $logger->info("Using ZMQ implementation", ['class' => $class]);
                return $context;
            }
        }

        // Final fallback to react/zmq
        $logger->info("Using default ZMQ implementation", ['class' => 'React\\ZMQ\\Context']);
        return new ReactZMQContext($loop);
    }

    /**
     * Try to instantiate a specific ZMQ implementation
     * 
     * @param string $className Class name to try
     * @param LoopInterface $loop Event loop
     * @param Logger $logger Logger instance
     * @return object|null ZMQ context or null if unavailable
     */
    private static function tryImplementation(string $className, LoopInterface $loop, Logger $logger): ?object
    {
        try {
            // Normalize class name
            $className = ltrim($className, '\\');
            
            if (class_exists($className)) {
                $logger->debug("Attempting to use ZMQ implementation", ['class' => $className]);
                return new $className($loop);
            }
        } catch (\Exception $e) {
            $logger->debug("Failed to load ZMQ implementation", [
                'class' => $className,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Get available ZMQ implementations
     * 
     * @return array List of available implementation class names
     */
    public static function getAvailableImplementations(): array
    {
        $implementations = [
            'ClosetGeek\\ReactPHPZMQ\\Context',
            'FriendsOfReactPHP\\ZMQ\\Context',
            'React\\ZMQ\\Context',
        ];

        return array_filter($implementations, function($class) {
            return class_exists($class);
        });
    }
}
