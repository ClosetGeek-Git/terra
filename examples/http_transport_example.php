<?php

/**
 * HTTP/REST API Transport Example
 * 
 * Demonstrates using Terra Admin Framework with HTTP Restful API transport
 */

require __DIR__ . '/../vendor/autoload.php';

use Terra\Admin\AdminClient;

// Configuration for HTTP transport
$config = [
    'janus' => [
        'transport' => 'http',
        'http_address' => 'http://localhost:7088/admin',
        'http_event_address' => 'http://localhost:7088/admin', // For long polling
        'admin_secret' => 'janusoverlord',
        'timeout' => 30,
    ],
    'http' => [
        'long_polling' => true,
        'long_poll_timeout' => 30,
    ],
    'logging' => [
        'enabled' => true,
        'level' => 'info',
    ],
];

echo "Terra Admin Framework - HTTP Transport Example\n";
echo "=============================================\n\n";

try {
    // Create admin client with HTTP transport
    $client = new AdminClient($config);
    
    // Register event handler
    $client->onEvent(function ($event) {
        echo "Event received: " . json_encode($event, JSON_PRETTY_PRINT) . "\n";
    });

    echo "Connecting to Janus Gateway via HTTP...\n";

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
            
            echo "HTTP transport test completed successfully!\n";
            echo "Event loop is running... Press Ctrl+C to exit.\n";
            echo "(Events will be displayed via long polling)\n";
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
