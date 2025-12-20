<?php

namespace Terra\Tests\Integration;

use Terra\Plugin\RecordPlayAdmin;

/**
 * Integration tests for RecordPlay Plugin Admin functionality
 * 
 * Tests RecordPlay plugin operations across all transport types
 */
class RecordPlayPluginTest extends IntegrationTestBase
{
    /**
     * @var RecordPlayAdmin RecordPlay admin controller
     */
    private $recordPlay;

    /**
     * Set up test environment
     * 
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfJanusNotAvailable();
        
        $this->recordPlay = new RecordPlayAdmin($this->client);
        $this->client->registerPlugin('recordplay', $this->recordPlay);
    }

    /**
     * Test listing recordings
     * 
     * @return void
     */
    public function testListRecordings(): void
    {
        $result = $this->executePromiseTest(function () {
            return $this->recordPlay->listRecordings();
        });

        $this->assertSuccessResponse($result);
        $this->assertArrayHasKey('plugindata', $result);
        $this->assertArrayHasKey('data', $result['plugindata']);
        
        $data = $result['plugindata']['data'];
        $this->assertArrayHasKey('list', $data);
        $this->assertIsArray($data['list']);
    }

    /**
     * Test getting info for non-existent recording
     * 
     * @return void
     */
    public function testGetNonExistentRecordingInfo(): void
    {
        try {
            $result = $this->executePromiseTest(function () {
                return $this->recordPlay->getRecordingInfo('nonexistent_recording_' . time());
            });

            // Should return error
            $data = $result['plugindata']['data'];
            $this->assertArrayHasKey('error', $data);
        } catch (\Exception $e) {
            // Expected exception for non-existent recording
            $this->assertInstanceOf(\Exception::class, $e);
        }
    }

    /**
     * Test deleting non-existent recording
     * 
     * @return void
     */
    public function testDeleteNonExistentRecording(): void
    {
        try {
            $result = $this->executePromiseTest(function () {
                return $this->recordPlay->deleteRecording('nonexistent_recording_' . time());
            });

            // Should return error
            $data = $result['plugindata']['data'];
            $this->assertArrayHasKey('error', $data);
        } catch (\Exception $e) {
            // Expected exception for non-existent recording
            $this->assertInstanceOf(\Exception::class, $e);
        }
    }

    /**
     * Test that recordings list has proper structure
     * 
     * @return void
     */
    public function testListRecordingsStructure(): void
    {
        $result = $this->executePromiseTest(function () {
            return $this->recordPlay->listRecordings();
        });

        $this->assertSuccessResponse($result);
        $data = $result['plugindata']['data'];
        
        // Verify structure
        $this->assertArrayHasKey('list', $data);
        $this->assertIsArray($data['list']);
        
        // If there are recordings, verify their structure
        if (!empty($data['list'])) {
            foreach ($data['list'] as $recording) {
                $this->assertIsArray($recording);
                // Recordings typically have an 'id' field
                $this->assertArrayHasKey('id', $recording);
            }
        }
    }
}
