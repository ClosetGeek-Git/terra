<?php

namespace Terra\Tests\Integration;

use Terra\Plugin\StreamingAdmin;

/**
 * Comprehensive Streaming Plugin Admin Integration Test
 * 
 * Tests all Streaming admin features across all transport types
 */
class StreamingAdminTest extends BaseIntegrationTest
{
    /**
     * @var StreamingAdmin
     */
    private $streaming;

    /**
     * Test mountpoint ID for cleanup
     */
    private $testMountpointId = null;

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
                    'zmq' => ['persistent' => true, 'linger' => 0],
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
        return strtoupper(self::$transportType) . ' Streaming Admin';
    }

    /**
     * @inheritDoc
     */
    protected function printTransportSpecificTroubleshooting(): void
    {
        echo "Streaming Plugin specific issues:\n\n";
        echo "  1. Verify Streaming plugin is enabled:\n";
        echo "     $ ls -la /usr/lib/janus/plugins/libjanus_streaming.so\n\n";
        echo "  2. Check plugin configuration:\n";
        echo "     $ cat /etc/janus/janus.plugin.streaming.jcfg\n\n";
        echo "  3. Check Janus logs for plugin errors:\n";
        echo "     $ sudo journalctl -u janus | grep streaming\n\n";
    }

    /**
     * Set transport type
     */
    public static function setTransportType(string $type): void
    {
        self::$transportType = $type;
    }

    /**
     * Setup Streaming admin instance
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->createClient();
        $this->streaming = new StreamingAdmin($this->client);
        $this->client->registerPlugin('streaming', $this->streaming);
    }

    /**
     * Test listing mountpoints
     */
    public function testListMountpoints(): void
    {
        $success = false;
        $error = null;

        $this->client->connect()->then(
            function () {
                return $this->streaming->listMountpoints();
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

        $this->recordResult('List Mountpoints', $success, $error);
        $this->assertTrue($success, "Failed to list mountpoints: " . ($error ?? 'Unknown error'));
    }

    /**
     * Test creating a mountpoint
     */
    public function testCreateMountpoint(): void
    {
        $success = false;
        $error = null;
        $this->testMountpointId = 8000 + rand(1, 1000);

        $this->client->connect()->then(
            function () {
                return $this->streaming->createMountpoint([
                    'id' => $this->testMountpointId,
                    'name' => 'Test Mountpoint ' . $this->testMountpointId,
                    'description' => 'Test streaming mountpoint',
                    'type' => 'rtp',
                    'audio' => true,
                    'video' => true,
                    'audioport' => 5002,
                    'videoport' => 5004,
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

        $this->recordResult('Create Mountpoint', $success, $error);
        $this->assertTrue($success, "Failed to create mountpoint: " . ($error ?? 'Unknown error'));
    }

    /**
     * Test getting mountpoint information
     */
    public function testGetMountpointInfo(): void
    {
        $success = false;
        $error = null;
        $mountpointId = 8001;

        $this->client->connect()->then(
            function () use ($mountpointId) {
                return $this->streaming->createMountpoint([
                    'id' => $mountpointId,
                    'name' => 'Info Test Mountpoint',
                    'type' => 'rtp',
                    'audio' => true,
                ]);
            }
        )->then(
            function () use ($mountpointId) {
                return $this->streaming->getMountpointInfo($mountpointId);
            }
        )->then(
            function ($response) use (&$success) {
                $success = true;
            },
            function ($e) use (&$error) {
                $error = $e->getMessage();
            }
        )->always(
            function () use ($mountpointId) {
                return $this->streaming->destroyMountpoint($mountpointId);
            }
        );

        $this->client->getLoop()->run();

        $this->recordResult('Get Mountpoint Info', $success, $error);
        $this->assertTrue($success, "Failed to get mountpoint info: " . ($error ?? 'Unknown error'));
    }

    /**
     * Test enabling/disabling a mountpoint
     */
    public function testToggleMountpoint(): void
    {
        $success = false;
        $error = null;
        $mountpointId = 8002;

        $this->client->connect()->then(
            function () use ($mountpointId) {
                return $this->streaming->createMountpoint([
                    'id' => $mountpointId,
                    'name' => 'Toggle Test Mountpoint',
                    'type' => 'rtp',
                    'audio' => true,
                ]);
            }
        )->then(
            function () use ($mountpointId) {
                // Disable mountpoint
                return $this->streaming->toggleMountpoint($mountpointId, false);
            }
        )->then(
            function () use ($mountpointId) {
                // Enable mountpoint
                return $this->streaming->toggleMountpoint($mountpointId, true);
            }
        )->then(
            function ($response) use (&$success) {
                $success = true;
            },
            function ($e) use (&$error) {
                $error = $e->getMessage();
            }
        )->always(
            function () use ($mountpointId) {
                return $this->streaming->destroyMountpoint($mountpointId);
            }
        );

        $this->client->getLoop()->run();

        $this->recordResult('Toggle Mountpoint', $success, $error);
        $this->assertTrue($success, "Failed to toggle mountpoint: " . ($error ?? 'Unknown error'));
    }

    /**
     * Test destroying a mountpoint
     */
    public function testDestroyMountpoint(): void
    {
        $success = false;
        $error = null;
        $mountpointId = 8003;

        $this->client->connect()->then(
            function () use ($mountpointId) {
                return $this->streaming->createMountpoint([
                    'id' => $mountpointId,
                    'name' => 'Destroy Test Mountpoint',
                    'type' => 'rtp',
                    'audio' => true,
                ]);
            }
        )->then(
            function () use ($mountpointId) {
                return $this->streaming->destroyMountpoint($mountpointId);
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

        $this->recordResult('Destroy Mountpoint', $success, $error);
        $this->assertTrue($success, "Failed to destroy mountpoint: " . ($error ?? 'Unknown error'));
    }

    /**
     * Cleanup after tests
     */
    protected function tearDown(): void
    {
        // Clean up test mountpoint if it exists
        if ($this->testMountpointId !== null && $this->client && $this->streaming) {
            try {
                $this->client->connect()->then(
                    function () {
                        return $this->streaming->destroyMountpoint($this->testMountpointId);
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
