<?php

require __DIR__ . '/../vendor/autoload.php';

use Terra\Admin\AdminClient;
use Terra\Plugin\VideoRoomAdmin;

/**
 * Example: Basic Admin Client Usage
 * 
 * Demonstrates how to connect to Janus Gateway and retrieve server information
 */

// Configuration
$config = [
    'janus' => [
        'admin_address' => 'tcp://localhost:7889',
        'admin_secret' => 'janusoverlord', // Change this to your admin secret
        'timeout' => 30,
    ],
    'logging' => [
        'enabled' => true,
        'level' => 'debug',
    ],
];

// Create admin client
$client = new AdminClient($config);

// Connect to Janus
$client->connect()->then(
    function () use ($client) {
        echo "Connected to Janus Gateway!\n";

        // Get server information
        return $client->getInfo();
    }
)->then(
    function ($response) use ($client) {
        echo "Server Info:\n";
        echo json_encode($response, JSON_PRETTY_PRINT) . "\n\n";

        // List all sessions
        return $client->listSessions();
    }
)->then(
    function ($response) use ($client) {
        echo "Active Sessions:\n";
        echo json_encode($response, JSON_PRETTY_PRINT) . "\n\n";

        // Stop the event loop after getting results
        $client->getLoop()->stop();
    }
)->otherwise(
    function ($error) use ($client) {
        echo "Error: " . $error->getMessage() . "\n";
        $client->getLoop()->stop();
    }
);

// Run the event loop
$client->run();

echo "Done!\n";
