<?php

namespace Terra\Tests\Integration;

use PHPUnit\Framework\TestCase;
use React\EventLoop\Factory as LoopFactory;
use React\EventLoop\LoopInterface;
use Terra\Admin\AdminClient;
use Terra\Transport\ZmqTransport;
use Terra\Transport\UnixSocketTransport;
use Terra\Transport\HttpTransport;

/**
 * Base class for integration tests
 * 
 * Provides common functionality for testing against a live Janus instance
 */
abstract class IntegrationTestBase extends TestCase
{
    /**
     * @var AdminClient Admin client instance
     */
    protected $client;

    /**
     * @var LoopInterface Event loop
     */
    protected $loop;

    /**
     * @var string Transport type being tested
     */
    protected $transportType;

    /**
     * @var array Test configuration
     */
    protected $config;

    /**
     * Set up test environment
     * 
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Determine which transport to test
        $this->transportType = getenv('JANUS_TRANSPORT') ?: 'zmq';
        
        // Load configuration based on transport type
        $this->config = $this->getConfigForTransport($this->transportType);
        
        // Create event loop
        $this->loop = LoopFactory::create();
        
        // Create client with appropriate transport
        $this->client = $this->createClient($this->config);
    }

    /**
     * Clean up test environment
     * 
     * @return void
     */
    protected function tearDown(): void
    {
        if ($this->client) {
            $this->client->disconnect();
        }

        parent::tearDown();
    }

    /**
     * Get configuration for the specified transport type
     * 
     * @param string $transportType Transport type (zmq, unix, http)
     * @return array Configuration array
     */
    protected function getConfigForTransport(string $transportType): array
    {
        switch ($transportType) {
            case 'zmq':
                return [
                    'janus' => [
                        'admin_address' => getenv('JANUS_ADMIN_ADDRESS') ?: 'tcp://localhost:7889',
                        'admin_secret' => getenv('JANUS_ADMIN_SECRET') ?: 'janusoverlord',
                        'timeout' => 30,
                    ],
                    'transport' => 'zmq',
                ];

            case 'unix':
                return [
                    'janus' => [
                        'admin_socket' => getenv('JANUS_ADMIN_SOCKET') ?: '/tmp/janus_admin.sock',
                        'admin_secret' => getenv('JANUS_ADMIN_SECRET') ?: 'janusoverlord',
                        'timeout' => 30,
                    ],
                    'transport' => 'unix',
                ];

            case 'http':
                return [
                    'janus' => [
                        'admin_host' => getenv('JANUS_ADMIN_HOST') ?: 'localhost',
                        'admin_port' => (int)(getenv('JANUS_ADMIN_PORT') ?: 7088),
                        'admin_base_path' => getenv('JANUS_ADMIN_BASE_PATH') ?: '/admin',
                        'admin_secret' => getenv('JANUS_ADMIN_SECRET') ?: 'janusoverlord',
                        'admin_secure' => (bool)(getenv('JANUS_ADMIN_SECURE') ?: false),
                        'timeout' => 30,
                    ],
                    'transport' => 'http',
                ];

            default:
                throw new \InvalidArgumentException("Unknown transport type: {$transportType}");
        }
    }

    /**
     * Create admin client with the specified configuration
     * 
     * @param array $config Configuration array
     * @return AdminClient
     */
    protected function createClient(array $config): AdminClient
    {
        return new AdminClient($config, $this->loop);
    }

    /**
     * Execute a promise-based test
     * 
     * Runs the event loop until the promise resolves or a timeout occurs
     * 
     * @param callable $testCallback Callback that returns a promise
     * @param int $timeout Timeout in seconds
     * @return mixed Result of the promise
     */
    protected function executePromiseTest(callable $testCallback, int $timeout = 30)
    {
        $result = null;
        $error = null;
        $completed = false;

        // Set up timeout
        $timer = $this->loop->addTimer($timeout, function () use (&$completed, &$error) {
            if (!$completed) {
                $error = new \Exception("Test timeout after {$timeout} seconds");
            }
        });

        // Execute the test
        $testCallback()->then(
            function ($value) use (&$result, &$completed, $timer) {
                $result = $value;
                $completed = true;
                $this->loop->cancelTimer($timer);
            },
            function ($reason) use (&$error, &$completed, $timer) {
                $error = $reason;
                $completed = true;
                $this->loop->cancelTimer($timer);
            }
        );

        // Run the event loop
        $this->loop->run();

        // Check for errors
        if ($error !== null) {
            throw $error;
        }

        return $result;
    }

    /**
     * Skip test if Janus is not available
     * 
     * @return void
     */
    protected function skipIfJanusNotAvailable(): void
    {
        if (getenv('SKIP_INTEGRATION_TESTS') === 'true') {
            $this->markTestSkipped('Integration tests are disabled');
        }

        try {
            $this->executePromiseTest(function () {
                return $this->client->connect();
            }, 5);
        } catch (\Exception $e) {
            $this->markTestSkipped("Janus Gateway is not available: " . $e->getMessage());
        }
    }

    /**
     * Assert that a response is successful
     * 
     * @param array $response Response data
     * @return void
     */
    protected function assertSuccessResponse(array $response): void
    {
        $this->assertIsArray($response);
        $this->assertArrayHasKey('janus', $response);
        $this->assertNotEquals('error', $response['janus']);
    }

    /**
     * Assert that a response contains an error
     * 
     * @param array $response Response data
     * @return void
     */
    protected function assertErrorResponse(array $response): void
    {
        $this->assertIsArray($response);
        $this->assertArrayHasKey('janus', $response);
        $this->assertEquals('error', $response['janus']);
        $this->assertArrayHasKey('error', $response);
    }

    /**
     * Generate a random room ID for testing
     * 
     * @return int
     */
    protected function generateTestRoomId(): int
    {
        return rand(10000, 99999);
    }

    /**
     * Generate a random participant ID for testing
     * 
     * @return int
     */
    protected function generateTestParticipantId(): int
    {
        return rand(100000, 999999);
    }
}
