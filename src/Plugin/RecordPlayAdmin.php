<?php

namespace Terra\Plugin;

use React\Promise\Promise;
use Terra\Admin\AdminClient;

/**
 * Admin controller for Janus Record&Play Plugin
 * 
 * Manages Record&Play plugin administration for recording and playback management
 */
class RecordPlayAdmin
{
    /**
     * @var AdminClient Admin client instance
     */
    private $client;

    /**
     * @var string Plugin identifier
     */
    private $pluginId = 'janus.plugin.recordplay';

    /**
     * Constructor
     * 
     * @param AdminClient $client Admin client instance
     */
    public function __construct(AdminClient $client)
    {
        $this->client = $client;
    }

    /**
     * List all available recordings
     * 
     * @return Promise
     */
    public function listRecordings(): Promise
    {
        return $this->sendPluginRequest([
            'request' => 'list',
        ]);
    }

    /**
     * Get information about a specific recording
     * 
     * @param int|string $recordingId Recording ID
     * @return Promise
     */
    public function getRecordingInfo($recordingId): Promise
    {
        return $this->sendPluginRequest([
            'request' => 'info',
            'id' => $recordingId,
        ]);
    }

    /**
     * Delete a recording
     * 
     * @param int|string $recordingId Recording ID
     * @return Promise
     */
    public function deleteRecording($recordingId): Promise
    {
        return $this->sendPluginRequest([
            'request' => 'delete',
            'id' => $recordingId,
        ]);
    }

    /**
     * Send a plugin-specific request
     * 
     * @param array $request Request data
     * @return Promise
     */
    private function sendPluginRequest(array $request): Promise
    {
        $payload = [
            'janus' => 'message_plugin',
            'plugin' => $this->pluginId,
            'request' => $request,
            'admin_secret' => $this->client->getConfig()->get('janus.admin_secret'),
        ];

        return $this->client->sendAdminRequest($payload);
    }
}
