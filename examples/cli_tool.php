<?php

require __DIR__ . '/../vendor/autoload.php';

use Terra\Admin\AdminClient;
use Terra\Plugin\VideoRoomAdmin;
use Terra\Plugin\VideoCallAdmin;
use Terra\Plugin\StreamingAdmin;
use Terra\Plugin\EchoTestAdmin;
use Terra\Plugin\RecordPlayAdmin;

/**
 * Interactive CLI Tool for Janus Admin API
 * 
 * Provides a command-line interface for testing Janus Admin API commands
 */

class JanusAdminCLI
{
    private $client;
    private $plugins = [];

    public function __construct(array $config)
    {
        $this->client = new AdminClient($config);
        
        // Register all plugins
        $this->plugins['videoroom'] = new VideoRoomAdmin($this->client);
        $this->plugins['videocall'] = new VideoCallAdmin($this->client);
        $this->plugins['streaming'] = new StreamingAdmin($this->client);
        $this->plugins['echotest'] = new EchoTestAdmin($this->client);
        $this->plugins['recordplay'] = new RecordPlayAdmin($this->client);

        foreach ($this->plugins as $name => $plugin) {
            $this->client->registerPlugin($name, $plugin);
        }
    }

    public function run()
    {
        $this->client->connect()->then(
            function () {
                echo "\n";
                echo "╔════════════════════════════════════════════════════════════╗\n";
                echo "║         Janus Admin Framework - Interactive CLI           ║\n";
                echo "╚════════════════════════════════════════════════════════════╝\n";
                echo "\nConnected to Janus Gateway!\n\n";
                $this->showMenu();
            }
        )->otherwise(
            function ($error) {
                echo "Failed to connect: " . $error->getMessage() . "\n";
                $this->client->getLoop()->stop();
            }
        );

        $this->client->run();
    }

    private function showMenu()
    {
        echo "Available Commands:\n";
        echo "  1. Get Server Info\n";
        echo "  2. List Sessions\n";
        echo "  3. Set Log Level\n";
        echo "  4. VideoRoom - List Rooms\n";
        echo "  5. VideoRoom - Create Room\n";
        echo "  6. VideoRoom - List Participants\n";
        echo "  7. Streaming - List Mountpoints\n";
        echo "  8. Streaming - Create Mountpoint\n";
        echo "  9. EchoTest - Get Stats\n";
        echo "  10. RecordPlay - List Recordings\n";
        echo "  0. Exit\n";
        echo "\nEnter command number: ";

        // In a real implementation, you would read from stdin
        // For this demo, we'll execute a sample command
        $this->executeCommand(1);
    }

    private function executeCommand(int $command)
    {
        switch ($command) {
            case 1:
                $this->getServerInfo();
                break;
            case 2:
                $this->listSessions();
                break;
            case 3:
                $this->setLogLevel('debug');
                break;
            case 4:
                $this->videoRoomListRooms();
                break;
            case 5:
                $this->videoRoomCreateRoom();
                break;
            case 6:
                $this->videoRoomListParticipants(1234);
                break;
            case 7:
                $this->streamingListMountpoints();
                break;
            case 8:
                $this->streamingCreateMountpoint();
                break;
            case 9:
                $this->echoTestGetStats();
                break;
            case 10:
                $this->recordPlayListRecordings();
                break;
            case 0:
                $this->exit();
                break;
            default:
                echo "Invalid command\n";
                $this->client->getLoop()->stop();
        }
    }

    private function getServerInfo()
    {
        echo "\n=== Getting Server Info ===\n";
        $this->client->getInfo()->then(
            function ($response) {
                echo json_encode($response, JSON_PRETTY_PRINT) . "\n";
                $this->client->getLoop()->stop();
            }
        )->otherwise(
            function ($error) {
                echo "Error: " . $error->getMessage() . "\n";
                $this->client->getLoop()->stop();
            }
        );
    }

    private function listSessions()
    {
        echo "\n=== Listing Sessions ===\n";
        $this->client->listSessions()->then(
            function ($response) {
                echo json_encode($response, JSON_PRETTY_PRINT) . "\n";
                $this->client->getLoop()->stop();
            }
        )->otherwise(
            function ($error) {
                echo "Error: " . $error->getMessage() . "\n";
                $this->client->getLoop()->stop();
            }
        );
    }

    private function setLogLevel(string $level)
    {
        echo "\n=== Setting Log Level to $level ===\n";
        $this->client->setLogLevel($level)->then(
            function ($response) {
                echo json_encode($response, JSON_PRETTY_PRINT) . "\n";
                $this->client->getLoop()->stop();
            }
        )->otherwise(
            function ($error) {
                echo "Error: " . $error->getMessage() . "\n";
                $this->client->getLoop()->stop();
            }
        );
    }

    private function videoRoomListRooms()
    {
        echo "\n=== Listing VideoRooms ===\n";
        $this->plugins['videoroom']->listRooms()->then(
            function ($response) {
                echo json_encode($response, JSON_PRETTY_PRINT) . "\n";
                $this->client->getLoop()->stop();
            }
        )->otherwise(
            function ($error) {
                echo "Error: " . $error->getMessage() . "\n";
                $this->client->getLoop()->stop();
            }
        );
    }

    private function videoRoomCreateRoom()
    {
        echo "\n=== Creating VideoRoom ===\n";
        $this->plugins['videoroom']->createRoom([
            'room' => 1234,
            'description' => 'CLI Test Room',
            'publishers' => 6,
        ])->then(
            function ($response) {
                echo json_encode($response, JSON_PRETTY_PRINT) . "\n";
                $this->client->getLoop()->stop();
            }
        )->otherwise(
            function ($error) {
                echo "Error: " . $error->getMessage() . "\n";
                $this->client->getLoop()->stop();
            }
        );
    }

    private function videoRoomListParticipants(int $roomId)
    {
        echo "\n=== Listing Participants in Room $roomId ===\n";
        $this->plugins['videoroom']->listParticipants($roomId)->then(
            function ($response) {
                echo json_encode($response, JSON_PRETTY_PRINT) . "\n";
                $this->client->getLoop()->stop();
            }
        )->otherwise(
            function ($error) {
                echo "Error: " . $error->getMessage() . "\n";
                $this->client->getLoop()->stop();
            }
        );
    }

    private function streamingListMountpoints()
    {
        echo "\n=== Listing Streaming Mountpoints ===\n";
        $this->plugins['streaming']->listMountpoints()->then(
            function ($response) {
                echo json_encode($response, JSON_PRETTY_PRINT) . "\n";
                $this->client->getLoop()->stop();
            }
        )->otherwise(
            function ($error) {
                echo "Error: " . $error->getMessage() . "\n";
                $this->client->getLoop()->stop();
            }
        );
    }

    private function streamingCreateMountpoint()
    {
        echo "\n=== Creating Streaming Mountpoint ===\n";
        $this->plugins['streaming']->createMountpoint([
            'id' => 999,
            'name' => 'CLI Test Stream',
            'type' => 'rtp',
        ])->then(
            function ($response) {
                echo json_encode($response, JSON_PRETTY_PRINT) . "\n";
                $this->client->getLoop()->stop();
            }
        )->otherwise(
            function ($error) {
                echo "Error: " . $error->getMessage() . "\n";
                $this->client->getLoop()->stop();
            }
        );
    }

    private function echoTestGetStats()
    {
        echo "\n=== Getting EchoTest Stats ===\n";
        $this->plugins['echotest']->getStats()->then(
            function ($response) {
                echo json_encode($response, JSON_PRETTY_PRINT) . "\n";
                $this->client->getLoop()->stop();
            }
        )->otherwise(
            function ($error) {
                echo "Error: " . $error->getMessage() . "\n";
                $this->client->getLoop()->stop();
            }
        );
    }

    private function recordPlayListRecordings()
    {
        echo "\n=== Listing Recordings ===\n";
        $this->plugins['recordplay']->listRecordings()->then(
            function ($response) {
                echo json_encode($response, JSON_PRETTY_PRINT) . "\n";
                $this->client->getLoop()->stop();
            }
        )->otherwise(
            function ($error) {
                echo "Error: " . $error->getMessage() . "\n";
                $this->client->getLoop()->stop();
            }
        );
    }

    private function exit()
    {
        echo "\nGoodbye!\n";
        $this->client->disconnect();
        $this->client->getLoop()->stop();
    }
}

// Configuration
$config = [
    'janus' => [
        'admin_address' => getenv('JANUS_ADMIN_ADDRESS') ?: 'tcp://localhost:7889',
        'admin_secret' => getenv('JANUS_ADMIN_SECRET') ?: 'janusoverlord',
        'timeout' => 30,
    ],
    'logging' => [
        'enabled' => true,
        'level' => getenv('LOG_LEVEL') ?: 'info',
    ],
];

// Run the CLI
$cli = new JanusAdminCLI($config);
$cli->run();
