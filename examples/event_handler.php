<?php

require __DIR__ . '/../vendor/autoload.php';

use Terra\Admin\AdminClient;

/**
 * Example: Event Handler
 * 
 * Demonstrates how to handle events from Janus Gateway
 */

// Configuration
$config = [
    'janus' => [
        'admin_address' => 'tcp://localhost:7889',
        'admin_secret' => 'janusoverlord',
        'timeout' => 30,
    ],
    'logging' => [
        'enabled' => true,
        'level' => 'debug',
    ],
];

// Create admin client
$client = new AdminClient($config);

// Register event handler
$client->onEvent(function ($event) {
    echo "Received Event:\n";
    echo json_encode($event, JSON_PRETTY_PRINT) . "\n\n";
});

// Connect to Janus
$client->connect()->then(
    function () use ($client) {
        echo "Connected to Janus Gateway!\n";
        echo "Listening for events... (Press Ctrl+C to stop)\n\n";

        // Get server info to test connection
        return $client->getInfo();
    }
)->then(
    function ($response) {
        echo "Server is responding:\n";
        echo json_encode($response, JSON_PRETTY_PRINT) . "\n\n";
    }
)->otherwise(
    function ($error) use ($client) {
        echo "Error: " . $error->getMessage() . "\n";
        $client->getLoop()->stop();
    }
);

// Keep the event loop running
$client->run();
