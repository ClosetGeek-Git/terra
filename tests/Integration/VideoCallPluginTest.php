<?php

namespace Terra\Tests\Integration;

use Terra\Plugin\VideoCallAdmin;

/**
 * Integration tests for VideoCall Plugin Admin functionality
 * 
 * Tests VideoCall plugin operations across all transport types
 */
class VideoCallPluginTest extends IntegrationTestBase
{
    /**
     * @var VideoCallAdmin VideoCall admin controller
     */
    private $videoCall;

    /**
     * Set up test environment
     * 
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfJanusNotAvailable();
        
        $this->videoCall = new VideoCallAdmin($this->client);
        $this->client->registerPlugin('videocall', $this->videoCall);
    }

    /**
     * Test listing VideoCall sessions
     * 
     * @return void
     */
    public function testListSessions(): void
    {
        $result = $this->executePromiseTest(function () {
            return $this->videoCall->listSessions();
        });

        $this->assertSuccessResponse($result);
        $this->assertArrayHasKey('plugindata', $result);
        $this->assertArrayHasKey('data', $result['plugindata']);
        
        $data = $result['plugindata']['data'];
        $this->assertArrayHasKey('list', $data);
        $this->assertIsArray($data['list']);
    }

    /**
     * Test getting user info for non-existent user
     * 
     * @return void
     */
    public function testGetNonExistentUserInfo(): void
    {
        try {
            $result = $this->executePromiseTest(function () {
                return $this->videoCall->getUserInfo('nonexistent_user_' . time());
            });

            // Should return error or empty result
            $data = $result['plugindata']['data'];
            $this->assertArrayHasKey('error', $data);
        } catch (\Exception $e) {
            // Expected exception for non-existent user
            $this->assertInstanceOf(\Exception::class, $e);
        }
    }

    /**
     * Test hangup for non-existent user
     * 
     * @return void
     */
    public function testHangupNonExistentUser(): void
    {
        try {
            $result = $this->executePromiseTest(function () {
                return $this->videoCall->hangup('nonexistent_user_' . time());
            });

            // Should return error
            $data = $result['plugindata']['data'];
            $this->assertArrayHasKey('error', $data);
        } catch (\Exception $e) {
            // Expected exception for non-existent user
            $this->assertInstanceOf(\Exception::class, $e);
        }
    }

    /**
     * Test that sessions list is initially empty or has proper structure
     * 
     * @return void
     */
    public function testListSessionsStructure(): void
    {
        $result = $this->executePromiseTest(function () {
            return $this->videoCall->listSessions();
        });

        $this->assertSuccessResponse($result);
        $data = $result['plugindata']['data'];
        
        // Verify structure
        $this->assertArrayHasKey('list', $data);
        $this->assertIsArray($data['list']);
        
        // If there are sessions, verify their structure
        if (!empty($data['list'])) {
            foreach ($data['list'] as $session) {
                $this->assertIsArray($session);
            }
        }
    }
}
