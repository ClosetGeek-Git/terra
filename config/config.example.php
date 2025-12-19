<?php

/**
 * Configuration example for Terra Janus Admin Framework
 * 
 * Copy this file to config/local.php and customize for your environment
 */

return [
    // Janus Gateway configuration
    'janus' => [
        // ZeroMQ admin endpoint address
        'admin_address' => getenv('JANUS_ADMIN_ADDRESS') ?: 'tcp://localhost:7889',
        
        // Admin API secret (must match janus.jcfg)
        'admin_secret' => getenv('JANUS_ADMIN_SECRET') ?: 'janusoverlord',
        
        // Request timeout in seconds
        'timeout' => 30,
    ],

    // ZeroMQ socket configuration
    'zmq' => [
        // Use persistent connections
        'persistent' => true,
        
        // Socket linger time (0 = no linger)
        'linger' => 0,
    ],

    // Logging configuration
    'logging' => [
        // Enable or disable logging
        'enabled' => true,
        
        // Log level: debug, info, notice, warning, error, critical, alert, emergency
        'level' => getenv('LOG_LEVEL') ?: 'info',
        
        // Log file path (null = stdout)
        'path' => null,
    ],
];
