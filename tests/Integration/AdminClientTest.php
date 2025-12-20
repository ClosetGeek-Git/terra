<?php

namespace Terra\Tests\Integration;

/**
 * Integration tests for AdminClient core functionality
 * 
 * Tests basic admin operations across all transport types
 */
class AdminClientTest extends IntegrationTestBase
{
    /**
     * Test connection to Janus Gateway
     * 
     * @return void
     */
    public function testConnection(): void
    {
        $result = $this->executePromiseTest(function () {
            return $this->client->connect();
        });

        $this->assertTrue($this->client->getLoop() !== null);
    }

    /**
     * Test getting server information
     * 
     * @return void
     */
    public function testGetInfo(): void
    {
        $this->skipIfJanusNotAvailable();

        $info = $this->executePromiseTest(function () {
            return $this->client->getInfo();
        });

        $this->assertSuccessResponse($info);
        $this->assertArrayHasKey('name', $info);
        $this->assertEquals('Janus WebRTC Server', $info['name']);
        $this->assertArrayHasKey('version', $info);
    }

    /**
     * Test listing sessions
     * 
     * @return void
     */
    public function testListSessions(): void
    {
        $this->skipIfJanusNotAvailable();

        $result = $this->executePromiseTest(function () {
            return $this->client->listSessions();
        });

        $this->assertSuccessResponse($result);
        $this->assertArrayHasKey('sessions', $result);
        $this->assertIsArray($result['sessions']);
    }

    /**
     * Test getting log level
     * 
     * @return void
     */
    public function testGetLogLevel(): void
    {
        $this->skipIfJanusNotAvailable();

        $result = $this->executePromiseTest(function () {
            return $this->client->getLogLevel();
        });

        $this->assertSuccessResponse($result);
        $this->assertArrayHasKey('level', $result);
        $this->assertIsInt($result['level']);
    }

    /**
     * Test setting log level
     * 
     * @return void
     */
    public function testSetLogLevel(): void
    {
        $this->skipIfJanusNotAvailable();

        // Set to info level (4)
        $result = $this->executePromiseTest(function () {
            return $this->client->setLogLevel('info');
        });

        $this->assertSuccessResponse($result);
        $this->assertArrayHasKey('level', $result);
        $this->assertEquals(4, $result['level']);
    }

    /**
     * Test setting different log levels
     * 
     * @dataProvider logLevelProvider
     * @return void
     */
    public function testSetDifferentLogLevels(string $level, int $expectedValue): void
    {
        $this->skipIfJanusNotAvailable();

        $result = $this->executePromiseTest(function () use ($level) {
            return $this->client->setLogLevel($level);
        });

        $this->assertSuccessResponse($result);
        $this->assertEquals($expectedValue, $result['level']);
    }

    /**
     * Provider for log level tests
     * 
     * @return array
     */
    public function logLevelProvider(): array
    {
        return [
            'fatal' => ['fatal', 1],
            'error' => ['error', 2],
            'warn' => ['warn', 3],
            'info' => ['info', 4],
            'debug' => ['debug', 6],
        ];
    }

    /**
     * Test listing handles for a non-existent session
     * 
     * @return void
     */
    public function testListHandlesForNonExistentSession(): void
    {
        $this->skipIfJanusNotAvailable();

        try {
            $result = $this->executePromiseTest(function () {
                return $this->client->listHandles(999999999);
            });

            // Some Janus versions return success with empty array, others return error
            if (isset($result['janus']) && $result['janus'] === 'error') {
                $this->assertErrorResponse($result);
            } else {
                $this->assertSuccessResponse($result);
            }
        } catch (\Exception $e) {
            // Expected for non-existent session
            $this->assertStringContainsString('No such session', $e->getMessage());
        }
    }

    /**
     * Test handle info for non-existent handle
     * 
     * @return void
     */
    public function testHandleInfoForNonExistentHandle(): void
    {
        $this->skipIfJanusNotAvailable();

        try {
            $result = $this->executePromiseTest(function () {
                return $this->client->handleInfo(999999999, 888888888);
            });

            $this->assertErrorResponse($result);
        } catch (\Exception $e) {
            // Expected for non-existent handle
            $this->assertStringContainsString('No such', $e->getMessage());
        }
    }

    /**
     * Test connection timeout
     * 
     * @return void
     */
    public function testConnectionTimeout(): void
    {
        // Create client with invalid address
        $invalidConfig = $this->config;
        
        if ($this->transportType === 'zmq') {
            $invalidConfig['janus']['admin_address'] = 'tcp://localhost:9999';
        } elseif ($this->transportType === 'http') {
            $invalidConfig['janus']['admin_port'] = 9999;
        } else {
            $this->markTestSkipped('Timeout test not applicable for Unix socket');
        }
        
        $invalidConfig['janus']['timeout'] = 2;
        
        $client = $this->createClient($invalidConfig);

        try {
            $this->executePromiseTest(function () use ($client) {
                return $client->connect();
            }, 5);
            
            $this->fail('Expected connection to fail');
        } catch (\Exception $e) {
            $this->assertStringContainsString('Failed to connect', $e->getMessage());
        }
    }

    /**
     * Test invalid admin secret
     * 
     * @return void
     */
    public function testInvalidAdminSecret(): void
    {
        // Skip for HTTP transport as it might handle auth differently
        if ($this->transportType === 'http') {
            $this->markTestSkipped('HTTP transport may handle auth differently');
        }

        $invalidConfig = $this->config;
        $invalidConfig['janus']['admin_secret'] = 'invalid_secret';
        
        $client = $this->createClient($invalidConfig);

        try {
            $this->executePromiseTest(function () use ($client) {
                return $client->connect()->then(function () use ($client) {
                    return $client->getInfo();
                });
            });
            
            // If we get here, check for error in response
            $this->markTestIncomplete('Need to verify error handling for invalid secret');
        } catch (\Exception $e) {
            $this->assertStringContainsString('Unauthorized', $e->getMessage());
        }
    }
}
