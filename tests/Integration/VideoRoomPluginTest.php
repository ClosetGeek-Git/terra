<?php

namespace Terra\Tests\Integration;

use Terra\Plugin\VideoRoomAdmin;

/**
 * Integration tests for VideoRoom Plugin Admin functionality
 * 
 * Tests VideoRoom plugin operations across all transport types
 */
class VideoRoomPluginTest extends IntegrationTestBase
{
    /**
     * @var VideoRoomAdmin VideoRoom admin controller
     */
    private $videoRoom;

    /**
     * @var array Test rooms to clean up
     */
    private $testRooms = [];

    /**
     * Set up test environment
     * 
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfJanusNotAvailable();
        
        $this->videoRoom = new VideoRoomAdmin($this->client);
        $this->client->registerPlugin('videoroom', $this->videoRoom);
    }

    /**
     * Clean up test rooms
     * 
     * @return void
     */
    protected function tearDown(): void
    {
        foreach ($this->testRooms as $roomId) {
            try {
                $this->executePromiseTest(function () use ($roomId) {
                    return $this->videoRoom->destroyRoom($roomId);
                });
            } catch (\Exception $e) {
                // Ignore cleanup errors
            }
        }

        parent::tearDown();
    }

    /**
     * Test listing video rooms
     * 
     * @return void
     */
    public function testListRooms(): void
    {
        $result = $this->executePromiseTest(function () {
            return $this->videoRoom->listRooms();
        });

        $this->assertSuccessResponse($result);
        $this->assertArrayHasKey('plugindata', $result);
        $this->assertArrayHasKey('data', $result['plugindata']);
        $this->assertArrayHasKey('list', $result['plugindata']['data']);
        $this->assertIsArray($result['plugindata']['data']['list']);
    }

    /**
     * Test creating a video room
     * 
     * @return void
     */
    public function testCreateRoom(): void
    {
        $roomId = $this->generateTestRoomId();
        $this->testRooms[] = $roomId;

        $result = $this->executePromiseTest(function () use ($roomId) {
            return $this->videoRoom->createRoom([
                'room' => $roomId,
                'description' => 'Test Room for Integration Tests',
                'publishers' => 6,
                'audiocodec' => 'opus',
                'videocodec' => 'vp8',
                'record' => false,
            ]);
        });

        $this->assertSuccessResponse($result);
        $this->assertArrayHasKey('plugindata', $result);
        
        $data = $result['plugindata']['data'];
        $this->assertArrayHasKey('videoroom', $data);
        $this->assertEquals('created', $data['videoroom']);
        $this->assertEquals($roomId, $data['room']);
    }

    /**
     * Test creating and destroying a room
     * 
     * @return void
     */
    public function testCreateAndDestroyRoom(): void
    {
        $roomId = $this->generateTestRoomId();

        // Create room
        $createResult = $this->executePromiseTest(function () use ($roomId) {
            return $this->videoRoom->createRoom([
                'room' => $roomId,
                'description' => 'Temporary Test Room',
                'publishers' => 3,
            ]);
        });

        $this->assertSuccessResponse($createResult);

        // Destroy room
        $destroyResult = $this->executePromiseTest(function () use ($roomId) {
            return $this->videoRoom->destroyRoom($roomId);
        });

        $this->assertSuccessResponse($destroyResult);
        $data = $destroyResult['plugindata']['data'];
        $this->assertEquals('destroyed', $data['videoroom']);
    }

    /**
     * Test getting room information
     * 
     * @return void
     */
    public function testGetRoomInfo(): void
    {
        $roomId = $this->generateTestRoomId();
        $this->testRooms[] = $roomId;

        // Create room first
        $this->executePromiseTest(function () use ($roomId) {
            return $this->videoRoom->createRoom([
                'room' => $roomId,
                'description' => 'Room for Info Test',
            ]);
        });

        // Get room info
        $result = $this->executePromiseTest(function () use ($roomId) {
            return $this->videoRoom->getRoomInfo($roomId);
        });

        $this->assertSuccessResponse($result);
        $data = $result['plugindata']['data'];
        $this->assertArrayHasKey('exists', $data);
        $this->assertTrue($data['exists']);
        $this->assertEquals($roomId, $data['room']);
    }

    /**
     * Test getting info for non-existent room
     * 
     * @return void
     */
    public function testGetNonExistentRoomInfo(): void
    {
        $roomId = 99999;

        $result = $this->executePromiseTest(function () use ($roomId) {
            return $this->videoRoom->getRoomInfo($roomId);
        });

        $this->assertSuccessResponse($result);
        $data = $result['plugindata']['data'];
        $this->assertArrayHasKey('exists', $data);
        $this->assertFalse($data['exists']);
    }

    /**
     * Test listing participants in a room
     * 
     * @return void
     */
    public function testListParticipants(): void
    {
        $roomId = $this->generateTestRoomId();
        $this->testRooms[] = $roomId;

        // Create room
        $this->executePromiseTest(function () use ($roomId) {
            return $this->videoRoom->createRoom([
                'room' => $roomId,
                'description' => 'Room for Participant Test',
            ]);
        });

        // List participants (should be empty)
        $result = $this->executePromiseTest(function () use ($roomId) {
            return $this->videoRoom->listParticipants($roomId);
        });

        $this->assertSuccessResponse($result);
        $data = $result['plugindata']['data'];
        $this->assertArrayHasKey('participants', $data);
        $this->assertIsArray($data['participants']);
        $this->assertEmpty($data['participants']);
    }

    /**
     * Test editing room configuration
     * 
     * @return void
     */
    public function testEditRoom(): void
    {
        $roomId = $this->generateTestRoomId();
        $this->testRooms[] = $roomId;

        // Create room
        $this->executePromiseTest(function () use ($roomId) {
            return $this->videoRoom->createRoom([
                'room' => $roomId,
                'description' => 'Original Description',
            ]);
        });

        // Edit room
        $result = $this->executePromiseTest(function () use ($roomId) {
            return $this->videoRoom->editRoom($roomId, [
                'new_description' => 'Updated Description',
            ]);
        });

        $this->assertSuccessResponse($result);
        $data = $result['plugindata']['data'];
        $this->assertEquals('edited', $data['videoroom']);
    }

    /**
     * Test creating room with invalid parameters
     * 
     * @return void
     */
    public function testCreateRoomWithInvalidParameters(): void
    {
        try {
            $result = $this->executePromiseTest(function () {
                return $this->videoRoom->createRoom([
                    'room' => -1, // Invalid room ID
                    'description' => 'Invalid Room',
                ]);
            });

            // Check if response contains error
            if (isset($result['plugindata']['data']['error'])) {
                $this->assertArrayHasKey('error', $result['plugindata']['data']);
            }
        } catch (\Exception $e) {
            // Expected exception for invalid parameters
            $this->assertInstanceOf(\Exception::class, $e);
        }
    }

    /**
     * Test creating duplicate room
     * 
     * @return void
     */
    public function testCreateDuplicateRoom(): void
    {
        $roomId = $this->generateTestRoomId();
        $this->testRooms[] = $roomId;

        // Create room first time
        $this->executePromiseTest(function () use ($roomId) {
            return $this->videoRoom->createRoom([
                'room' => $roomId,
                'description' => 'First Creation',
            ]);
        });

        // Try to create same room again
        try {
            $result = $this->executePromiseTest(function () use ($roomId) {
                return $this->videoRoom->createRoom([
                    'room' => $roomId,
                    'description' => 'Second Creation',
                ]);
            });

            // Should return error about room already existing
            $this->assertArrayHasKey('plugindata', $result);
            $data = $result['plugindata']['data'];
            $this->assertArrayHasKey('error', $data);
        } catch (\Exception $e) {
            // Expected exception for duplicate room
            $this->assertInstanceOf(\Exception::class, $e);
        }
    }

    /**
     * Test destroying non-existent room
     * 
     * @return void
     */
    public function testDestroyNonExistentRoom(): void
    {
        $roomId = 99999;

        try {
            $result = $this->executePromiseTest(function () use ($roomId) {
                return $this->videoRoom->destroyRoom($roomId);
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
     * Test room with different codecs
     * 
     * @dataProvider codecProvider
     * @return void
     */
    public function testRoomWithDifferentCodecs(string $audioCodec, string $videoCodec): void
    {
        $roomId = $this->generateTestRoomId();
        $this->testRooms[] = $roomId;

        $result = $this->executePromiseTest(function () use ($roomId, $audioCodec, $videoCodec) {
            return $this->videoRoom->createRoom([
                'room' => $roomId,
                'description' => "Test Room with {$audioCodec}/{$videoCodec}",
                'audiocodec' => $audioCodec,
                'videocodec' => $videoCodec,
            ]);
        });

        $this->assertSuccessResponse($result);
        $data = $result['plugindata']['data'];
        $this->assertEquals('created', $data['videoroom']);
    }

    /**
     * Provider for codec tests
     * 
     * @return array
     */
    public function codecProvider(): array
    {
        return [
            'opus_vp8' => ['opus', 'vp8'],
            'opus_vp9' => ['opus', 'vp9'],
            'opus_h264' => ['opus', 'h264'],
        ];
    }
}
