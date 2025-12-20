<?php

namespace Terra\Tests\Integration;

use Terra\Admin\AdminClient;

/**
 * Comprehensive Admin Client Integration Test
 * 
 * Tests all high-level admin features across all transport types
 * This test is designed to run against all three transports:
 * - HTTP/REST API
 * - UnixSocket
 * - ZeroMQ
 */
class AdminClientTest extends BaseIntegrationTest
{
    /**
     * Current transport type for test execution
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
                        'preferred_implementation' => 'auto',
                    ],
                    'logging' => [
                        'enabled' => true,
                        'level' => 'debug',
                    ],
                ];

            case 'unix':
                return [
                    'janus' => [
                        'transport' => 'unix',
                        'unix_socket_path' => getenv('JANUS_UNIX_SOCKET') ?: '/var/run/janus/janus-admin.sock',
                        'admin_secret' => getenv('JANUS_ADMIN_SECRET') ?: 'janusoverlord',
                        'timeout' => 30,
                    ],
                    'logging' => [
                        'enabled' => true,
                        'level' => 'debug',
                    ],
                ];

            case 'http':
            default:
                return [
                    'janus' => [
                        'transport' => 'http',
                        'http_address' => getenv('JANUS_HTTP_ADDRESS') ?: 'http://localhost:7088/admin',
                        'http_event_address' => getenv('JANUS_HTTP_EVENT_ADDRESS') ?: 'http://localhost:7088/admin',
                        'admin_secret' => getenv('JANUS_ADMIN_SECRET') ?: 'janusoverlord',
                        'timeout' => 30,
                    ],
                    'http' => [
                        'long_polling' => true,
                        'long_poll_timeout' => 30,
                    ],
                    'logging' => [
                        'enabled' => true,
                        'level' => 'debug',
                    ],
                ];
        }
    }

    /**
     * @inheritDoc
     */
    protected function getTransportName(): string
    {
        return strtoupper(self::$transportType) . ' Admin Client';
    }

    /**
     * @inheritDoc
     */
    protected function printTransportSpecificTroubleshooting(): void
    {
        echo "Admin Client specific issues:\n\n";
        echo "  1. Verify Janus admin_secret configuration:\n";
        echo "     $ cat /etc/janus/janus.jcfg | grep admin_secret\n";
        echo "     Should match: janusoverlord\n\n";
        
        echo "  2. Check if admin API is enabled:\n";
        echo "     Admin API should be enabled in transport configuration\n\n";
        
        echo "  3. Verify transport-specific configuration:\n";
        if (self::$transportType === 'http') {
            echo "     $ cat /etc/janus/janus.transport.http.jcfg\n";
            echo "     Verify: admin_http = true, admin_port = 7088\n\n";
        } elseif (self::$transportType === 'unix') {
            echo "     $ cat /etc/janus/janus.transport.pfunix.jcfg\n";
            echo "     Verify: admin_enabled = true\n\n";
        } elseif (self::$transportType === 'zmq') {
            echo "     $ cat /etc/janus/janus.transport.zmq.jcfg\n";
            echo "     Verify: admin_enabled = true\n\n";
        }
    }

    /**
     * Set transport type for testing
     */
    public static function setTransportType(string $type): void
    {
        self::$transportType = $type;
    }

    /**
     * Test getting server information
     */
    public function testGetServerInfo(): void
    {
        $this->createClient();

        $success = false;
        $error = null;
        $info = null;

        $this->client->connect()->then(
            function () use (&$success, &$error, &$info) {
                return $this->client->getInfo();
            }
        )->then(
            function ($response) use (&$success, &$info) {
                $info = $response;
                $success = true;
            },
            function ($e) use (&$error) {
                $error = $e->getMessage();
            }
        );

        $this->client->getLoop()->run();

        $this->recordResult('Get Server Info', $success, $error);
        $this->assertTrue($success, "Failed to get server info: " . ($error ?? 'Unknown error'));
        $this->assertIsArray($info);
        $this->assertArrayHasKey('name', $info);
        $this->assertEquals('Janus WebRTC Server', $info['name']);
    }

    /**
     * Test listing all sessions
     */
    public function testListAllSessions(): void
    {
        $this->createClient();

        $success = false;
        $error = null;
        $sessions = null;

        $this->client->connect()->then(
            function () {
                return $this->client->listSessions();
            }
        )->then(
            function ($response) use (&$success, &$sessions) {
                $sessions = $response;
                $success = true;
            },
            function ($e) use (&$error) {
                $error = $e->getMessage();
            }
        );

        $this->client->getLoop()->run();

        $this->recordResult('List Sessions', $success, $error);
        $this->assertTrue($success, "Failed to list sessions: " . ($error ?? 'Unknown error'));
        $this->assertIsArray($sessions);
        // Sessions array can be empty if no active sessions
    }

    /**
     * Test log level operations
     */
    public function testLogLevelOperations(): void
    {
        $this->createClient();

        $getLevelSuccess = false;
        $setLevelSuccess = false;
        $error = null;
        $initialLevel = null;

        $this->client->connect()->then(
            function () {
                // First get current log level
                return $this->client->getLogLevel();
            }
        )->then(
            function ($response) use (&$getLevelSuccess, &$initialLevel) {
                $getLevelSuccess = true;
                $initialLevel = $response['level'] ?? null;
                // Now set log level
                return $this->client->setLogLevel('info');
            }
        )->then(
            function ($response) use (&$setLevelSuccess) {
                $setLevelSuccess = true;
            },
            function ($e) use (&$error) {
                $error = $e->getMessage();
            }
        );

        $this->client->getLoop()->run();

        $this->recordResult('Get Log Level', $getLevelSuccess, $error);
        $this->recordResult('Set Log Level', $setLevelSuccess, $error);
        
        $this->assertTrue($getLevelSuccess, "Failed to get log level: " . ($error ?? 'Unknown error'));
        $this->assertTrue($setLevelSuccess, "Failed to set log level: " . ($error ?? 'Unknown error'));
        $this->assertNotNull($initialLevel, "Log level should not be null");
    }

    /**
     * Test event handler registration
     */
    public function testEventHandlerRegistration(): void
    {
        $this->createClient();

        $eventHandlerRegistered = false;
        $error = null;

        // Register event handler
        $this->client->onEvent(function ($event) use (&$eventHandlerRegistered) {
            // Event received
            $eventHandlerRegistered = true;
        });

        $this->client->connect()->then(
            function () {
                // Connection successful
            },
            function ($e) use (&$error) {
                $error = $e->getMessage();
            }
        );

        // Run briefly to allow event handler setup
        $this->client->getLoop()->addTimer(2, function () {
            $this->client->disconnect();
        });

        $this->client->getLoop()->run();

        $this->recordResult('Event Handler Registration', $error === null, $error);
        $this->assertNull($error, "Failed to register event handler: " . ($error ?? ''));
    }

    /**
     * Test plugin registration functionality
     */
    public function testPluginRegistration(): void
    {
        $this->createClient();

        try {
            // Create mock plugin
            $mockPlugin = new class($this->client) {
                private $client;
                public function __construct($client) {
                    $this->client = $client;
                }
            };

            // Register plugin
            $this->client->registerPlugin('test_plugin', $mockPlugin);
            
            // Verify plugin is registered
            $registered = $this->client->plugin('test_plugin');
            
            $this->recordResult('Plugin Registration', $registered !== null);
            $this->assertNotNull($registered, "Plugin was not registered");
            $this->assertSame($mockPlugin, $registered, "Retrieved plugin does not match registered plugin");
        } catch (\Exception $e) {
            $this->recordResult('Plugin Registration', false, $e->getMessage());
            $this->fail("Plugin registration failed: " . $e->getMessage());
        }
    }

    /**
     * Test configuration manager access
     */
    public function testConfigurationAccess(): void
    {
        $this->createClient();

        try {
            $config = $this->client->getConfig();
            $adminSecret = $config->get('janus.admin_secret');
            
            $this->recordResult('Configuration Access', $adminSecret !== null);
            $this->assertNotNull($adminSecret, "Admin secret should not be null");
            $this->assertEquals('janusoverlord', $adminSecret, "Admin secret mismatch");
        } catch (\Exception $e) {
            $this->recordResult('Configuration Access', false, $e->getMessage());
            $this->fail("Configuration access failed: " . $e->getMessage());
        }
    }

    /**
     * Test logger access
     */
    public function testLoggerAccess(): void
    {
        $this->createClient();

        try {
            $logger = $this->client->getLogger();
            
            $this->recordResult('Logger Access', $logger !== null);
            $this->assertNotNull($logger, "Logger should not be null");
            
            // Test logging (should not throw exception)
            $logger->info("Test log message");
            $this->assertTrue(true, "Logging successful");
        } catch (\Exception $e) {
            $this->recordResult('Logger Access', false, $e->getMessage());
            $this->fail("Logger access failed: " . $e->getMessage());
        }
    }

    /**
     * Test event loop access
     */
    public function testEventLoopAccess(): void
    {
        $this->createClient();

        try {
            $loop = $this->client->getLoop();
            
            $this->recordResult('Event Loop Access', $loop !== null);
            $this->assertNotNull($loop, "Event loop should not be null");
            $this->assertInstanceOf(\React\EventLoop\LoopInterface::class, $loop);
        } catch (\Exception $e) {
            $this->recordResult('Event Loop Access', false, $e->getMessage());
            $this->fail("Event loop access failed: " . $e->getMessage());
        }
    }

    /**
     * Test timeout configuration
     */
    public function testTimeoutConfiguration(): void
    {
        $config = $this->getTransportConfig();
        $config['janus']['timeout'] = 5;

        try {
            $client = new AdminClient($config);
            
            $this->recordResult('Timeout Configuration', true);
            $this->assertTrue(true, "Timeout configuration successful");
        } catch (\Exception $e) {
            $this->recordResult('Timeout Configuration', false, $e->getMessage());
            $this->fail("Failed to configure timeout: " . $e->getMessage());
        }
    }

    /**
     * Test error handling for invalid admin secret
     */
    public function testInvalidAdminSecret(): void
    {
        $config = $this->getTransportConfig();
        $config['janus']['admin_secret'] = 'invalid_secret_12345';

        $client = new AdminClient($config);

        $failed = false;
        $error = null;

        $client->connect()->then(
            function () use ($client) {
                return $client->getInfo();
            }
        )->then(
            function ($response) {
                // Should not succeed with invalid secret
            },
            function ($e) use (&$failed, &$error) {
                $failed = true;
                $error = $e->getMessage();
            }
        );

        $client->getLoop()->run();

        // We expect this to fail with invalid secret
        $this->recordResult('Invalid Admin Secret Handling', $failed, 
            $failed ? null : 'Should have failed with invalid admin secret');
        
        $this->assertTrue($failed, "Should have failed with invalid admin secret");
    }
}
