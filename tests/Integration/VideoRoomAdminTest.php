<?php

namespace Terra\Tests\Integration;

use Terra\Plugin\VideoRoomAdmin;

/**
 * Comprehensive VideoRoom Plugin Admin Integration Test
 * 
 * Tests all VideoRoom admin features across all transport types
 */
class VideoRoomAdminTest extends BaseIntegrationTest
{
    /**
     * @var VideoRoomAdmin
     */
    private $videoRoom;

    /**
     * Test room ID for cleanup
     */
    private $testRoomId = null;

    /**
     * Current transport type
     */
    private static $transportType = 'http';

    /**
     * @inheritDoc
     */
    protected function getTransportConfig(): array
    {
        switch (self::$transportType) {
            case 'zmq':
                return [
                    'janus' => [
                        'transport' => 'zmq',
                        'admin_address' => getenv('JANUS_ADMIN_ADDRESS') ?: 'tcp://localhost:7889',
                        'admin_secret' => getenv('JANUS_ADMIN_SECRET') ?: 'janusoverlord',
                        'timeout' => 30,
                    ],
                    'zmq' => [
                        'persistent' => true,
                        'linger' => 0,
                    ],
                    'logging' => ['enabled' => true, 'level' => 'debug'],
                ];

            case 'unix':
                return [
                    'janus' => [
                        'transport' => 'unix',
                        'unix_socket_path' => getenv('JANUS_UNIX_SOCKET') ?: '/var/run/janus/janus-admin.sock',
                        'admin_secret' => getenv('JANUS_ADMIN_SECRET') ?: 'janusoverlord',
                        'timeout' => 30,
                    ],
                    'logging' => ['enabled' => true, 'level' => 'debug'],
                ];

            case 'http':
            default:
                return [
                    'janus' => [
                        'transport' => 'http',
                        'http_address' => getenv('JANUS_HTTP_ADDRESS') ?: 'http://localhost:7088/admin',
                        'admin_secret' => getenv('JANUS_ADMIN_SECRET') ?: 'janusoverlord',
                        'timeout' => 30,
                    ],
                    'http' => ['long_polling' => true],
                    'logging' => ['enabled' => true, 'level' => 'debug'],
                ];
        }
    }

    /**
     * @inheritDoc
     */
    protected function getTransportName(): string
    {
        return strtoupper(self::$transportType) . ' VideoRoom Admin';
    }

    /**
     * @inheritDoc
     */
    protected function printTransportSpecificTroubleshooting(): void
    {
        echo "VideoRoom Plugin specific issues:\n\n";
        echo "  1. Verify VideoRoom plugin is enabled:\n";
        echo "     $ ls -la /usr/lib/janus/plugins/libjanus_videoroom.so\n\n";
        echo "  2. Check plugin configuration:\n";
        echo "     $ cat /etc/janus/janus.plugin.videoroom.jcfg\n\n";
        echo "  3. Check Janus logs for plugin errors:\n";
        echo "     $ sudo journalctl -u janus | grep videoroom\n\n";
    }

    /**
     * Set transport type
     */
    public static function setTransportType(string $type): void
    {
        self::$transportType = $type;
    }

    /**
     * Setup VideoRoom admin instance
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->createClient();
        $this->videoRoom = new VideoRoomAdmin($this->client);
        $this->client->registerPlugin('videoroom', $this->videoRoom);
    }

    /**
     * Test listing rooms
     */
    public function testListRooms(): void
    {
        $success = false;
        $error = null;
        $rooms = null;

        $this->client->connect()->then(
            function () {
                return $this->videoRoom->listRooms();
            }
        )->then(
            function ($response) use (&$success, &$rooms) {
                $rooms = $response;
                $success = true;
            },
            function ($e) use (&$error) {
                $error = $e->getMessage();
            }
        );

        $this->client->getLoop()->run();

        $this->recordResult('List Rooms', $success, $error);
        $this->assertTrue($success, "Failed to list rooms: " . ($error ?? 'Unknown error'));
    }

    /**
     * Test creating a room
     */
    public function testCreateRoom(): void
    {
        $success = false;
        $error = null;
        $this->testRoomId = 9999 + rand(1, 1000);

        $this->client->connect()->then(
            function () {
                return $this->videoRoom->createRoom([
                    'room' => $this->testRoomId,
                    'description' => 'Test Room ' . $this->testRoomId,
                    'publishers' => 6,
                    'audiocodec' => 'opus',
                    'videocodec' => 'vp8',
                    'record' => false,
                ]);
            }
        )->then(
            function ($response) use (&$success) {
                $success = true;
            },
            function ($e) use (&$error) {
                $error = $e->getMessage();
            }
        );

        $this->client->getLoop()->run();

        $this->recordResult('Create Room', $success, $error);
        $this->assertTrue($success, "Failed to create room: " . ($error ?? 'Unknown error'));
    }

    /**
     * Test getting room information
     */
    public function testGetRoomInfo(): void
    {
        $success = false;
        $error = null;
        $roomId = 1234;

        // First try to create a room to test
        $this->client->connect()->then(
            function () use ($roomId) {
                return $this->videoRoom->createRoom([
                    'room' => $roomId,
                    'description' => 'Info Test Room',
                    'publishers' => 3,
                ]);
            }
        )->then(
            function () use ($roomId) {
                // Now get room info
                return $this->videoRoom->getRoomInfo($roomId);
            }
        )->then(
            function ($response) use (&$success) {
                $success = true;
            },
            function ($e) use (&$error) {
                $error = $e->getMessage();
            }
        )->always(
            function () use ($roomId) {
                // Cleanup
                return $this->videoRoom->destroyRoom($roomId);
            }
        );

        $this->client->getLoop()->run();

        $this->recordResult('Get Room Info', $success, $error);
        $this->assertTrue($success, "Failed to get room info: " . ($error ?? 'Unknown error'));
    }

    /**
     * Test listing participants
     */
    public function testListParticipants(): void
    {
        $success = false;
        $error = null;
        $roomId = 1235;

        $this->client->connect()->then(
            function () use ($roomId) {
                return $this->videoRoom->createRoom([
                    'room' => $roomId,
                    'description' => 'Participants Test Room',
                ]);
            }
        )->then(
            function () use ($roomId) {
                return $this->videoRoom->listParticipants($roomId);
            }
        )->then(
            function ($response) use (&$success) {
                $success = true;
            },
            function ($e) use (&$error) {
                $error = $e->getMessage();
            }
        )->always(
            function () use ($roomId) {
                return $this->videoRoom->destroyRoom($roomId);
            }
        );

        $this->client->getLoop()->run();

        $this->recordResult('List Participants', $success, $error);
        $this->assertTrue($success, "Failed to list participants: " . ($error ?? 'Unknown error'));
    }

    /**
     * Test editing room configuration
     */
    public function testEditRoom(): void
    {
        $success = false;
        $error = null;
        $roomId = 1236;

        $this->client->connect()->then(
            function () use ($roomId) {
                return $this->videoRoom->createRoom([
                    'room' => $roomId,
                    'description' => 'Edit Test Room',
                ]);
            }
        )->then(
            function () use ($roomId) {
                return $this->videoRoom->editRoom($roomId, [
                    'new_description' => 'Edited Test Room',
                ]);
            }
        )->then(
            function ($response) use (&$success) {
                $success = true;
            },
            function ($e) use (&$error) {
                $error = $e->getMessage();
            }
        )->always(
            function () use ($roomId) {
                return $this->videoRoom->destroyRoom($roomId);
            }
        );

        $this->client->getLoop()->run();

        $this->recordResult('Edit Room', $success, $error);
        $this->assertTrue($success, "Failed to edit room: " . ($error ?? 'Unknown error'));
    }

    /**
     * Test destroying a room
     */
    public function testDestroyRoom(): void
    {
        $success = false;
        $error = null;
        $roomId = 1237;

        $this->client->connect()->then(
            function () use ($roomId) {
                return $this->videoRoom->createRoom([
                    'room' => $roomId,
                    'description' => 'Destroy Test Room',
                ]);
            }
        )->then(
            function () use ($roomId) {
                return $this->videoRoom->destroyRoom($roomId);
            }
        )->then(
            function ($response) use (&$success) {
                $success = true;
            },
            function ($e) use (&$error) {
                $error = $e->getMessage();
            }
        );

        $this->client->getLoop()->run();

        $this->recordResult('Destroy Room', $success, $error);
        $this->assertTrue($success, "Failed to destroy room: " . ($error ?? 'Unknown error'));
    }

    /**
     * Test listing forwarders
     */
    public function testListForwarders(): void
    {
        $success = false;
        $error = null;
        $roomId = 1238;

        $this->client->connect()->then(
            function () use ($roomId) {
                return $this->videoRoom->createRoom([
                    'room' => $roomId,
                    'description' => 'Forwarders Test Room',
                ]);
            }
        )->then(
            function () use ($roomId) {
                return $this->videoRoom->listForwarders($roomId);
            }
        )->then(
            function ($response) use (&$success) {
                $success = true;
            },
            function ($e) use (&$error) {
                $error = $e->getMessage();
            }
        )->always(
            function () use ($roomId) {
                return $this->videoRoom->destroyRoom($roomId);
            }
        );

        $this->client->getLoop()->run();

        $this->recordResult('List Forwarders', $success, $error);
        $this->assertTrue($success, "Failed to list forwarders: " . ($error ?? 'Unknown error'));
    }

    /**
     * Cleanup after tests
     */
    protected function tearDown(): void
    {
        // Clean up test room if it exists
        if ($this->testRoomId !== null && $this->client && $this->videoRoom) {
            try {
                $this->client->connect()->then(
                    function () {
                        return $this->videoRoom->destroyRoom($this->testRoomId);
                    }
                );
                $this->client->getLoop()->addTimer(1, function () {
                    $this->client->disconnect();
                });
                $this->client->getLoop()->run();
            } catch (\Exception $e) {
                // Ignore cleanup errors
            }
        }

        parent::tearDown();
    }
}
