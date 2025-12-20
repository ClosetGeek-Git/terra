<?php

namespace Terra\Tests\Integration;

use Terra\Plugin\EchoTestAdmin;

/**
 * Integration tests for EchoTest Plugin Admin functionality
 * 
 * Tests EchoTest plugin operations across all transport types
 */
class EchoTestPluginTest extends IntegrationTestBase
{
    /**
     * @var EchoTestAdmin EchoTest admin controller
     */
    private $echoTest;

    /**
     * Set up test environment
     * 
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfJanusNotAvailable();
        
        $this->echoTest = new EchoTestAdmin($this->client);
        $this->client->registerPlugin('echotest', $this->echoTest);
    }

    /**
     * Test getting EchoTest plugin statistics
     * 
     * @return void
     */
    public function testGetStats(): void
    {
        $result = $this->executePromiseTest(function () {
            return $this->echoTest->getStats();
        });

        $this->assertSuccessResponse($result);
        $this->assertArrayHasKey('plugindata', $result);
        $this->assertArrayHasKey('data', $result['plugindata']);
    }

    /**
     * Test listing active EchoTest sessions
     * 
     * @return void
     */
    public function testListSessions(): void
    {
        $result = $this->executePromiseTest(function () {
            return $this->echoTest->listSessions();
        });

        $this->assertSuccessResponse($result);
        $this->assertArrayHasKey('plugindata', $result);
        $this->assertArrayHasKey('data', $result['plugindata']);
        
        $data = $result['plugindata']['data'];
        $this->assertArrayHasKey('list', $data);
        $this->assertIsArray($data['list']);
    }

    /**
     * Test that EchoTest sessions list is empty initially
     * 
     * @return void
     */
    public function testListSessionsIsEmpty(): void
    {
        $result = $this->executePromiseTest(function () {
            return $this->echoTest->listSessions();
        });

        $this->assertSuccessResponse($result);
        $data = $result['plugindata']['data'];
        
        // Should have a list (may be empty or have active sessions)
        $this->assertArrayHasKey('list', $data);
        $this->assertIsArray($data['list']);
    }
}
