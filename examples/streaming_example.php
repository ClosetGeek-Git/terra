<?php

require __DIR__ . '/../vendor/autoload.php';

use Terra\Admin\AdminClient;
use Terra\Plugin\StreamingAdmin;

/**
 * Example: Streaming Plugin Usage
 * 
 * Demonstrates how to manage streaming mountpoints
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
        'level' => 'info',
    ],
];

// Create admin client
$client = new AdminClient($config);

// Create Streaming admin controller
$streamingAdmin = new StreamingAdmin($client);

// Register the plugin
$client->registerPlugin('streaming', $streamingAdmin);

// Connect to Janus
$client->connect()->then(
    function () use ($client, $streamingAdmin) {
        echo "Connected to Janus Gateway!\n\n";

        // List all mountpoints
        echo "Listing all streaming mountpoints...\n";
        return $streamingAdmin->listMountpoints();
    }
)->then(
    function ($response) use ($streamingAdmin) {
        echo "Mountpoints:\n";
        echo json_encode($response, JSON_PRETTY_PRINT) . "\n\n";

        // Create a new mountpoint
        echo "Creating a new mountpoint...\n";
        return $streamingAdmin->createMountpoint([
            'id' => 999,
            'name' => 'Test Stream',
            'description' => 'Test streaming mountpoint',
            'type' => 'rtp',
            'audio' => true,
            'video' => true,
            'audioport' => 5002,
            'videoport' => 5004,
        ]);
    }
)->then(
    function ($response) use ($streamingAdmin) {
        echo "Mountpoint Created:\n";
        echo json_encode($response, JSON_PRETTY_PRINT) . "\n\n";

        // Get mountpoint information
        echo "Getting mountpoint information...\n";
        return $streamingAdmin->getMountpointInfo(999);
    }
)->then(
    function ($response) use ($client) {
        echo "Mountpoint Info:\n";
        echo json_encode($response, JSON_PRETTY_PRINT) . "\n\n";

        // Stop the event loop
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
