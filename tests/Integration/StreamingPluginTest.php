<?php

namespace Terra\Tests\Integration;

use Terra\Plugin\StreamingAdmin;

/**
 * Integration tests for Streaming Plugin Admin functionality
 * 
 * Tests Streaming plugin operations across all transport types
 */
class StreamingPluginTest extends IntegrationTestBase
{
    /**
     * @var StreamingAdmin Streaming admin controller
     */
    private $streaming;

    /**
     * @var array Test mountpoints to clean up
     */
    private $testMountpoints = [];

    /**
     * Set up test environment
     * 
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfJanusNotAvailable();
        
        $this->streaming = new StreamingAdmin($this->client);
        $this->client->registerPlugin('streaming', $this->streaming);
    }

    /**
     * Clean up test mountpoints
     * 
     * @return void
     */
    protected function tearDown(): void
    {
        foreach ($this->testMountpoints as $mountpointId) {
            try {
                $this->executePromiseTest(function () use ($mountpointId) {
                    return $this->streaming->destroyMountpoint($mountpointId);
                });
            } catch (\Exception $e) {
                // Ignore cleanup errors
            }
        }

        parent::tearDown();
    }

    /**
     * Test listing streaming mountpoints
     * 
     * @return void
     */
    public function testListMountpoints(): void
    {
        $result = $this->executePromiseTest(function () {
            return $this->streaming->listMountpoints();
        });

        $this->assertSuccessResponse($result);
        $this->assertArrayHasKey('plugindata', $result);
        $this->assertArrayHasKey('data', $result['plugindata']);
        $this->assertArrayHasKey('list', $result['plugindata']['data']);
        $this->assertIsArray($result['plugindata']['data']['list']);
    }

    /**
     * Test creating a streaming mountpoint
     * 
     * @return void
     */
    public function testCreateMountpoint(): void
    {
        $mountpointId = $this->generateTestRoomId();
        $this->testMountpoints[] = $mountpointId;

        $result = $this->executePromiseTest(function () use ($mountpointId) {
            return $this->streaming->createMountpoint([
                'id' => $mountpointId,
                'name' => 'Test Mountpoint',
                'description' => 'Test mountpoint for integration tests',
                'type' => 'rtp',
                'audio' => true,
                'video' => true,
                'audioport' => 5002,
                'audiopt' => 111,
                'audiocodec' => 'opus',
                'videoport' => 5004,
                'videopt' => 96,
                'videocodec' => 'vp8',
            ]);
        });

        $this->assertSuccessResponse($result);
        $this->assertArrayHasKey('plugindata', $result);
        
        $data = $result['plugindata']['data'];
        $this->assertArrayHasKey('streaming', $data);
        $this->assertEquals('created', $data['streaming']);
    }

    /**
     * Test creating and destroying a mountpoint
     * 
     * @return void
     */
    public function testCreateAndDestroyMountpoint(): void
    {
        $mountpointId = $this->generateTestRoomId();

        // Create mountpoint
        $createResult = $this->executePromiseTest(function () use ($mountpointId) {
            return $this->streaming->createMountpoint([
                'id' => $mountpointId,
                'name' => 'Temporary Mountpoint',
                'type' => 'rtp',
                'audio' => true,
                'audioport' => 5002,
                'audiopt' => 111,
                'audiocodec' => 'opus',
            ]);
        });

        $this->assertSuccessResponse($createResult);

        // Destroy mountpoint
        $destroyResult = $this->executePromiseTest(function () use ($mountpointId) {
            return $this->streaming->destroyMountpoint($mountpointId);
        });

        $this->assertSuccessResponse($destroyResult);
        $data = $destroyResult['plugindata']['data'];
        $this->assertEquals('destroyed', $data['streaming']);
    }

    /**
     * Test getting mountpoint information
     * 
     * @return void
     */
    public function testGetMountpointInfo(): void
    {
        $mountpointId = $this->generateTestRoomId();
        $this->testMountpoints[] = $mountpointId;

        // Create mountpoint first
        $this->executePromiseTest(function () use ($mountpointId) {
            return $this->streaming->createMountpoint([
                'id' => $mountpointId,
                'name' => 'Mountpoint for Info Test',
                'type' => 'rtp',
                'audio' => true,
                'audioport' => 5002,
                'audiopt' => 111,
                'audiocodec' => 'opus',
            ]);
        });

        // Get mountpoint info
        $result = $this->executePromiseTest(function () use ($mountpointId) {
            return $this->streaming->getMountpointInfo($mountpointId);
        });

        $this->assertSuccessResponse($result);
        $data = $result['plugindata']['data'];
        $this->assertArrayHasKey('info', $data);
        $this->assertEquals($mountpointId, $data['info']['id']);
    }

    /**
     * Test getting info for non-existent mountpoint
     * 
     * @return void
     */
    public function testGetNonExistentMountpointInfo(): void
    {
        $mountpointId = 99999;

        try {
            $result = $this->executePromiseTest(function () use ($mountpointId) {
                return $this->streaming->getMountpointInfo($mountpointId);
            });

            // Should return error
            $data = $result['plugindata']['data'];
            $this->assertArrayHasKey('error', $data);
        } catch (\Exception $e) {
            // Expected exception
            $this->assertInstanceOf(\Exception::class, $e);
        }
    }

    /**
     * Test toggling mountpoint
     * 
     * @return void
     */
    public function testToggleMountpoint(): void
    {
        $mountpointId = $this->generateTestRoomId();
        $this->testMountpoints[] = $mountpointId;

        // Create mountpoint
        $this->executePromiseTest(function () use ($mountpointId) {
            return $this->streaming->createMountpoint([
                'id' => $mountpointId,
                'name' => 'Mountpoint for Toggle Test',
                'type' => 'rtp',
                'audio' => true,
                'audioport' => 5002,
                'audiopt' => 111,
                'audiocodec' => 'opus',
            ]);
        });

        // Toggle mountpoint (disable)
        $result = $this->executePromiseTest(function () use ($mountpointId) {
            return $this->streaming->toggleMountpoint($mountpointId, false);
        });

        $this->assertSuccessResponse($result);
        $data = $result['plugindata']['data'];
        $this->assertEquals('enabled', $data['streaming']);
        $this->assertFalse($data['enabled']);
    }

    /**
     * Test creating mountpoint with different types
     * 
     * @dataProvider mountpointTypeProvider
     * @return void
     */
    public function testCreateMountpointWithDifferentTypes(string $type, array $config): void
    {
        $mountpointId = $this->generateTestRoomId();
        $this->testMountpoints[] = $mountpointId;

        $fullConfig = array_merge([
            'id' => $mountpointId,
            'name' => "Test Mountpoint - {$type}",
            'type' => $type,
        ], $config);

        $result = $this->executePromiseTest(function () use ($fullConfig) {
            return $this->streaming->createMountpoint($fullConfig);
        });

        $this->assertSuccessResponse($result);
        $data = $result['plugindata']['data'];
        $this->assertEquals('created', $data['streaming']);
    }

    /**
     * Provider for mountpoint type tests
     * 
     * @return array
     */
    public function mountpointTypeProvider(): array
    {
        return [
            'rtp_audio_only' => ['rtp', [
                'audio' => true,
                'audioport' => 5002,
                'audiopt' => 111,
                'audiocodec' => 'opus',
            ]],
            'rtp_video_only' => ['rtp', [
                'video' => true,
                'videoport' => 5004,
                'videopt' => 96,
                'videocodec' => 'vp8',
            ]],
            'rtp_audio_video' => ['rtp', [
                'audio' => true,
                'video' => true,
                'audioport' => 5002,
                'audiopt' => 111,
                'audiocodec' => 'opus',
                'videoport' => 5004,
                'videopt' => 96,
                'videocodec' => 'vp8',
            ]],
        ];
    }

    /**
     * Test destroying non-existent mountpoint
     * 
     * @return void
     */
    public function testDestroyNonExistentMountpoint(): void
    {
        $mountpointId = 99999;

        try {
            $result = $this->executePromiseTest(function () use ($mountpointId) {
                return $this->streaming->destroyMountpoint($mountpointId);
            });

            // Should return error
            $data = $result['plugindata']['data'];
            $this->assertArrayHasKey('error', $data);
        } catch (\Exception $e) {
            // Expected exception
            $this->assertInstanceOf(\Exception::class, $e);
        }
    }

    /**
     * Test creating duplicate mountpoint
     * 
     * @return void
     */
    public function testCreateDuplicateMountpoint(): void
    {
        $mountpointId = $this->generateTestRoomId();
        $this->testMountpoints[] = $mountpointId;

        // Create mountpoint first time
        $this->executePromiseTest(function () use ($mountpointId) {
            return $this->streaming->createMountpoint([
                'id' => $mountpointId,
                'name' => 'First Creation',
                'type' => 'rtp',
                'audio' => true,
                'audioport' => 5002,
                'audiopt' => 111,
                'audiocodec' => 'opus',
            ]);
        });

        // Try to create same mountpoint again
        try {
            $result = $this->executePromiseTest(function () use ($mountpointId) {
                return $this->streaming->createMountpoint([
                    'id' => $mountpointId,
                    'name' => 'Second Creation',
                    'type' => 'rtp',
                    'audio' => true,
                    'audioport' => 5002,
                    'audiopt' => 111,
                    'audiocodec' => 'opus',
                ]);
            });

            // Should return error
            $data = $result['plugindata']['data'];
            $this->assertArrayHasKey('error', $data);
        } catch (\Exception $e) {
            // Expected exception
            $this->assertInstanceOf(\Exception::class, $e);
        }
    }
}
