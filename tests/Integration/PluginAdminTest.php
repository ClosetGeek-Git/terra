<?php

namespace Terra\Tests\Integration;

use Terra\Plugin\VideoCallAdmin;
use Terra\Plugin\EchoTestAdmin;
use Terra\Plugin\RecordPlayAdmin;

/**
 * Comprehensive Plugin Admin Integration Test
 * 
 * Tests VideoCall, EchoTest, and RecordPlay admin features across all transport types
 */
class PluginAdminTest extends BaseIntegrationTest
{
    /**
     * @var VideoCallAdmin
     */
    private $videoCall;

    /**
     * @var EchoTestAdmin
     */
    private $echoTest;

    /**
     * @var RecordPlayAdmin
     */
    private $recordPlay;

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
        return strtoupper(self::$transportType) . ' Plugin Admin';
    }

    /**
     * @inheritDoc
     */
    protected function printTransportSpecificTroubleshooting(): void
    {
        echo "Plugin Admin specific issues:\n\n";
        echo "  1. Verify plugins are enabled:\n";
        echo "     $ ls -la /usr/lib/janus/plugins/\n\n";
        echo "  2. Check plugin configurations:\n";
        echo "     $ ls -la /etc/janus/janus.plugin.*.jcfg\n\n";
        echo "  3. Check Janus logs for plugin errors:\n";
        echo "     $ sudo journalctl -u janus | grep plugin\n\n";
    }

    /**
     * Set transport type
     */
    public static function setTransportType(string $type): void
    {
        self::$transportType = $type;
    }

    /**
     * Setup plugin admin instances
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->createClient();
        
        $this->videoCall = new VideoCallAdmin($this->client);
        $this->echoTest = new EchoTestAdmin($this->client);
        $this->recordPlay = new RecordPlayAdmin($this->client);
        
        $this->client->registerPlugin('videocall', $this->videoCall);
        $this->client->registerPlugin('echotest', $this->echoTest);
        $this->client->registerPlugin('recordplay', $this->recordPlay);
    }

    /**
     * Test VideoCall: List sessions
     */
    public function testVideoCallListSessions(): void
    {
        $success = false;
        $error = null;

        $this->client->connect()->then(
            function () {
                return $this->videoCall->listSessions();
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

        $this->recordResult('VideoCall List Sessions', $success, $error);
        $this->assertTrue($success, "Failed to list videocall sessions: " . ($error ?? 'Unknown error'));
    }

    /**
     * Test VideoCall: Get user info (with non-existent user - should handle gracefully)
     */
    public function testVideoCallGetUserInfo(): void
    {
        $completed = false;
        $error = null;

        $this->client->connect()->then(
            function () {
                // Query non-existent user to test error handling
                return $this->videoCall->getUserInfo('nonexistent_test_user_12345');
            }
        )->then(
            function ($response) use (&$completed) {
                $completed = true;
            },
            function ($e) use (&$completed, &$error) {
                // Expected to fail with non-existent user
                $completed = true;
                $error = $e->getMessage();
            }
        );

        $this->client->getLoop()->run();

        $this->recordResult('VideoCall Get User Info', $completed, 
            $completed ? null : 'Request did not complete');
        $this->assertTrue($completed, "VideoCall getUserInfo request did not complete");
    }

    /**
     * Test EchoTest: Get statistics
     */
    public function testEchoTestGetStats(): void
    {
        $success = false;
        $error = null;

        $this->client->connect()->then(
            function () {
                return $this->echoTest->getStats();
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

        $this->recordResult('EchoTest Get Stats', $success, $error);
        $this->assertTrue($success, "Failed to get echotest stats: " . ($error ?? 'Unknown error'));
    }

    /**
     * Test EchoTest: List sessions
     */
    public function testEchoTestListSessions(): void
    {
        $success = false;
        $error = null;

        $this->client->connect()->then(
            function () {
                return $this->echoTest->listSessions();
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

        $this->recordResult('EchoTest List Sessions', $success, $error);
        $this->assertTrue($success, "Failed to list echotest sessions: " . ($error ?? 'Unknown error'));
    }

    /**
     * Test RecordPlay: List recordings
     */
    public function testRecordPlayListRecordings(): void
    {
        $success = false;
        $error = null;

        $this->client->connect()->then(
            function () {
                return $this->recordPlay->listRecordings();
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

        $this->recordResult('RecordPlay List Recordings', $success, $error);
        $this->assertTrue($success, "Failed to list recordings: " . ($error ?? 'Unknown error'));
    }

    /**
     * Test RecordPlay: Get recording info (with non-existent recording)
     */
    public function testRecordPlayGetRecordingInfo(): void
    {
        $completed = false;
        $error = null;

        $this->client->connect()->then(
            function () {
                // Query non-existent recording to test error handling
                return $this->recordPlay->getRecordingInfo('nonexistent_recording_12345');
            }
        )->then(
            function ($response) use (&$completed) {
                $completed = true;
            },
            function ($e) use (&$completed, &$error) {
                // Expected to fail with non-existent recording
                $completed = true;
                $error = $e->getMessage();
            }
        );

        $this->client->getLoop()->run();

        $this->recordResult('RecordPlay Get Recording Info', $completed,
            $completed ? null : 'Request did not complete');
        $this->assertTrue($completed, "RecordPlay getRecordingInfo request did not complete");
    }

    /**
     * Test all plugin registrations
     */
    public function testPluginRegistrations(): void
    {
        try {
            $videoCall = $this->client->plugin('videocall');
            $echoTest = $this->client->plugin('echotest');
            $recordPlay = $this->client->plugin('recordplay');

            $this->recordResult('Plugin Registrations', 
                $videoCall !== null && $echoTest !== null && $recordPlay !== null);
            
            $this->assertNotNull($videoCall, "VideoCall plugin not registered");
            $this->assertNotNull($echoTest, "EchoTest plugin not registered");
            $this->assertNotNull($recordPlay, "RecordPlay plugin not registered");
        } catch (\Exception $e) {
            $this->recordResult('Plugin Registrations', false, $e->getMessage());
            $this->fail("Plugin registrations failed: " . $e->getMessage());
        }
    }
}
