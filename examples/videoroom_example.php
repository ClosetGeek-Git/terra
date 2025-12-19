<?php

require __DIR__ . '/../vendor/autoload.php';

use Terra\Admin\AdminClient;
use Terra\Plugin\VideoRoomAdmin;

/**
 * Example: VideoRoom Plugin Usage
 * 
 * Demonstrates how to manage video rooms using the VideoRoom plugin
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

// Create VideoRoom admin controller
$videoRoomAdmin = new VideoRoomAdmin($client);

// Register the plugin
$client->registerPlugin('videoroom', $videoRoomAdmin);

// Connect to Janus
$client->connect()->then(
    function () use ($client, $videoRoomAdmin) {
        echo "Connected to Janus Gateway!\n\n";

        // List all video rooms
        echo "Listing all video rooms...\n";
        return $videoRoomAdmin->listRooms();
    }
)->then(
    function ($response) use ($videoRoomAdmin) {
        echo "Video Rooms:\n";
        echo json_encode($response, JSON_PRETTY_PRINT) . "\n\n";

        // Create a new room
        echo "Creating a new video room...\n";
        return $videoRoomAdmin->createRoom([
            'room' => 1234,
            'description' => 'Test Room',
            'publishers' => 6,
            'audiocodec' => 'opus',
            'videocodec' => 'vp8',
            'record' => false,
        ]);
    }
)->then(
    function ($response) use ($videoRoomAdmin) {
        echo "Room Created:\n";
        echo json_encode($response, JSON_PRETTY_PRINT) . "\n\n";

        // Get room information
        echo "Getting room information...\n";
        return $videoRoomAdmin->getRoomInfo(1234);
    }
)->then(
    function ($response) use ($videoRoomAdmin) {
        echo "Room Info:\n";
        echo json_encode($response, JSON_PRETTY_PRINT) . "\n\n";

        // List participants in the room
        echo "Listing participants...\n";
        return $videoRoomAdmin->listParticipants(1234);
    }
)->then(
    function ($response) use ($client) {
        echo "Participants:\n";
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
