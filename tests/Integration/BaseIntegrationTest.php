<?php

namespace Terra\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Terra\Admin\AdminClient;
use Terra\Transport\TransportFactory;

/**
 * Base Integration Test
 * 
 * Provides common functionality for transport integration tests
 */
abstract class BaseIntegrationTest extends TestCase
{
    /**
     * @var AdminClient
     */
    protected $client;

    /**
     * @var array Test results for reporting
     */
    protected $testResults = [];

    /**
     * Check if Janus is available before running tests
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->testResults = [];
    }

    /**
     * Get configuration for the transport being tested
     * 
     * @return array
     */
    abstract protected function getTransportConfig(): array;

    /**
     * Get transport name for reporting
     * 
     * @return string
     */
    abstract protected function getTransportName(): string;

    /**
     * Record test result
     * 
     * @param string $testName
     * @param bool $passed
     * @param string|null $error
     */
    protected function recordResult(string $testName, bool $passed, ?string $error = null): void
    {
        $this->testResults[$testName] = [
            'passed' => $passed,
            'error' => $error,
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Print test summary
     */
    protected function printTestSummary(): void
    {
        echo "\n" . str_repeat("=", 80) . "\n";
        echo "TEST SUMMARY - {$this->getTransportName()} Transport\n";
        echo str_repeat("=", 80) . "\n\n";

        $passed = 0;
        $failed = 0;

        foreach ($this->testResults as $testName => $result) {
            $status = $result['passed'] ? '✓ PASS' : '✗ FAIL';
            $color = $result['passed'] ? "\033[0;32m" : "\033[0;31m";
            $reset = "\033[0m";

            echo sprintf("%s%-10s%s %s\n", $color, $status, $reset, $testName);
            
            if (!$result['passed'] && $result['error']) {
                echo "           Error: {$result['error']}\n";
            }

            if ($result['passed']) {
                $passed++;
            } else {
                $failed++;
            }
        }

        echo "\n" . str_repeat("-", 80) . "\n";
        echo sprintf("Total: %d tests | Passed: %d | Failed: %d\n", 
            count($this->testResults), $passed, $failed);
        echo str_repeat("=", 80) . "\n\n";

        if ($failed > 0) {
            $this->printTroubleshootingGuide();
        }
    }

    /**
     * Print troubleshooting guide for failed tests
     */
    protected function printTroubleshootingGuide(): void
    {
        echo "\n" . str_repeat("=", 80) . "\n";
        echo "TROUBLESHOOTING GUIDE - {$this->getTransportName()} Transport\n";
        echo str_repeat("=", 80) . "\n\n";

        echo "Common issues and solutions:\n\n";

        $this->printTransportSpecificTroubleshooting();

        echo "\nGeneral Janus troubleshooting:\n";
        echo "  1. Check if Janus is running:\n";
        echo "     $ systemctl status janus\n";
        echo "     $ journalctl -u janus -n 50\n\n";
        
        echo "  2. Verify Janus configuration:\n";
        echo "     $ cat /etc/janus/janus.jcfg\n";
        echo "     $ cat /etc/janus/janus.transport.*.jcfg\n\n";
        
        echo "  3. Check Janus logs:\n";
        echo "     $ tail -f /var/log/janus/janus.log\n\n";
        
        echo "  4. Test Janus manually:\n";
        echo "     $ curl http://localhost:7088/janus/info\n\n";
        
        echo "  5. Restart Janus:\n";
        echo "     $ sudo systemctl restart janus\n\n";

        echo str_repeat("=", 80) . "\n\n";
    }

    /**
     * Print transport-specific troubleshooting
     */
    abstract protected function printTransportSpecificTroubleshooting(): void;

    /**
     * Create test client
     * 
     * @return void
     */
    protected function createClient(): void
    {
        try {
            $this->client = new AdminClient($this->getTransportConfig());
            $this->recordResult('Client Creation', true);
        } catch (\Exception $e) {
            $this->recordResult('Client Creation', false, $e->getMessage());
            throw $e;
        }
    }

    /**
     * Test connection
     */
    public function testConnection(): void
    {
        $this->createClient();

        $connected = false;
        $error = null;

        $this->client->connect()->then(
            function () use (&$connected) {
                $connected = true;
            },
            function ($e) use (&$error) {
                $error = $e->getMessage();
            }
        );

        $this->client->getLoop()->run();

        $this->recordResult('Connection', $connected, $error);
        $this->assertTrue($connected, "Failed to connect: " . ($error ?? 'Unknown error'));
    }

    /**
     * Test getting server info
     */
    public function testGetInfo(): void
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
    }

    /**
     * Test listing sessions
     */
    public function testListSessions(): void
    {
        $this->createClient();

        $success = false;
        $error = null;
        $response = null;

        $this->client->connect()->then(
            function () {
                return $this->client->listSessions();
            }
        )->then(
            function ($resp) use (&$success, &$response) {
                $response = $resp;
                $success = true;
            },
            function ($e) use (&$error) {
                $error = $e->getMessage();
            }
        );

        $this->client->getLoop()->run();

        $this->recordResult('List Sessions', $success, $error);
        $this->assertTrue($success, "Failed to list sessions: " . ($error ?? 'Unknown error'));
        $this->assertIsArray($response);
    }

    /**
     * Test event handling
     */
    public function testEventHandling(): void
    {
        $this->createClient();

        $eventReceived = false;
        $error = null;

        $this->client->onEvent(function ($event) use (&$eventReceived) {
            $eventReceived = true;
        });

        $this->client->connect()->then(
            function () {
                // Events will be received asynchronously
            },
            function ($e) use (&$error) {
                $error = $e->getMessage();
            }
        );

        // Run loop briefly to check for events
        $this->client->getLoop()->addTimer(2, function () {
            $this->client->disconnect();
        });

        $this->client->getLoop()->run();

        // Event reception is optional (depends on Janus activity)
        $this->recordResult('Event Handling Setup', $error === null, $error);
        $this->assertNull($error, "Event handling failed: " . ($error ?? ''));
    }

    /**
     * Tear down
     */
    protected function tearDown(): void
    {
        if ($this->client) {
            $this->client->disconnect();
        }

        // Print summary after all tests in class
        if (method_exists($this, 'getStatus') && $this->getStatus() === 0) {
            $this->printTestSummary();
        }

        parent::tearDown();
    }
}
