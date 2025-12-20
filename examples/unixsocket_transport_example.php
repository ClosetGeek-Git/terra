<?php

/**
 * UnixSocket Transport Example
 * 
 * Demonstrates using Terra Admin Framework with UnixSocket transport
 */

require __DIR__ . '/../vendor/autoload.php';

use Terra\Admin\AdminClient;

// Configuration for UnixSocket transport
$config = [
    'janus' => [
        'transport' => 'unix',
        'unix_socket_path' => '/var/run/janus/janus-admin.sock',
        'admin_secret' => 'janusoverlord',
        'timeout' => 30,
    ],
    'logging' => [
        'enabled' => true,
        'level' => 'info',
    ],
];

echo "Terra Admin Framework - UnixSocket Transport Example\n";
echo "====================================================\n\n";

// Check OS compatibility
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    echo "✗ Error: UnixSocket transport is not supported on Windows\n";
    echo "  Please use HTTP or ZMQ transport instead.\n";
    exit(1);
}

try {
    // Create admin client with UnixSocket transport
    $client = new AdminClient($config);
    
    // Register event handler
    $client->onEvent(function ($event) {
        echo "Event received: " . json_encode($event, JSON_PRETTY_PRINT) . "\n";
    });

    echo "Connecting to Janus Gateway via UnixSocket...\n";
    echo "Socket path: " . $config['janus']['unix_socket_path'] . "\n\n";

    // Connect to Janus
    $client->connect()->then(
        function () use ($client) {
            echo "✓ Connected successfully!\n\n";
            
            // Get server info
            echo "Getting server information...\n";
            return $client->getInfo();
        }
    )->then(
        function ($info) use ($client) {
            echo "✓ Server Info:\n";
            echo "  Name: " . ($info['name'] ?? 'Unknown') . "\n";
            echo "  Version: " . ($info['version_string'] ?? 'Unknown') . "\n";
            echo "  Author: " . ($info['author'] ?? 'Unknown') . "\n\n";
            
            // List sessions
            echo "Listing active sessions...\n";
            return $client->listSessions();
        }
    )->then(
        function ($response) use ($client) {
            $sessions = $response['sessions'] ?? [];
            echo "✓ Active sessions: " . count($sessions) . "\n";
            
            if (!empty($sessions)) {
                echo "  Sessions:\n";
                foreach ($sessions as $sessionId) {
                    echo "    - Session ID: $sessionId\n";
                }
            }
            echo "\n";
            
            // Get current log level
            echo "Getting log level...\n";
            return $client->getLogLevel();
        }
    )->then(
        function ($response) use ($client) {
            $level = $response['level'] ?? 'Unknown';
            echo "✓ Current log level: $level\n\n";
            
            echo "UnixSocket transport test completed successfully!\n";
            echo "Event loop is running... Press Ctrl+C to exit.\n";
        }
    )->otherwise(
        function ($error) {
            echo "✗ Error: " . $error->getMessage() . "\n";
            exit(1);
        }
    );

    // Run the event loop
    $client->run();

} catch (\Exception $e) {
    echo "✗ Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}
