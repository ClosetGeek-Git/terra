<?php

namespace Terra\Plugin;

use React\Promise\Promise;
use Terra\Admin\AdminClient;

/**
 * Admin controller for Janus Streaming Plugin
 * 
 * Manages Streaming plugin administration including mountpoint management
 */
class StreamingAdmin
{
    /**
     * @var AdminClient Admin client instance
     */
    private $client;

    /**
     * @var string Plugin identifier
     */
    private $pluginId = 'janus.plugin.streaming';

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
     * List all streaming mountpoints
     * 
     * @return Promise
     */
    public function listMountpoints(): Promise
    {
        return $this->sendPluginRequest([
            'request' => 'list',
        ]);
    }

    /**
     * Get information about a specific mountpoint
     * 
     * @param int|string $mountpointId Mountpoint ID
     * @return Promise
     */
    public function getMountpointInfo($mountpointId): Promise
    {
        return $this->sendPluginRequest([
            'request' => 'info',
            'id' => $mountpointId,
        ]);
    }

    /**
     * Create a new streaming mountpoint
     * 
     * @param array $mountpointConfig Mountpoint configuration
     * @return Promise
     */
    public function createMountpoint(array $mountpointConfig): Promise
    {
        $request = array_merge([
            'request' => 'create',
        ], $mountpointConfig);

        return $this->sendPluginRequest($request);
    }

    /**
     * Destroy a streaming mountpoint
     * 
     * @param int|string $mountpointId Mountpoint ID
     * @param string|null $secret Mountpoint secret (if required)
     * @return Promise
     */
    public function destroyMountpoint($mountpointId, ?string $secret = null): Promise
    {
        $request = [
            'request' => 'destroy',
            'id' => $mountpointId,
        ];

        if ($secret !== null) {
            $request['secret'] = $secret;
        }

        return $this->sendPluginRequest($request);
    }

    /**
     * Enable/disable a mountpoint
     * 
     * @param int|string $mountpointId Mountpoint ID
     * @param bool $enabled Enable or disable
     * @param string|null $secret Mountpoint secret (if required)
     * @return Promise
     */
    public function toggleMountpoint($mountpointId, bool $enabled, ?string $secret = null): Promise
    {
        $request = [
            'request' => $enabled ? 'enable' : 'disable',
            'id' => $mountpointId,
        ];

        if ($secret !== null) {
            $request['secret'] = $secret;
        }

        return $this->sendPluginRequest($request);
    }

    /**
     * Start recording on a mountpoint
     * 
     * @param int|string $mountpointId Mountpoint ID
     * @param string|null $secret Mountpoint secret (if required)
     * @return Promise
     */
    public function startRecording($mountpointId, ?string $secret = null): Promise
    {
        $request = [
            'request' => 'recording',
            'id' => $mountpointId,
            'record' => true,
        ];

        if ($secret !== null) {
            $request['secret'] = $secret;
        }

        return $this->sendPluginRequest($request);
    }

    /**
     * Stop recording on a mountpoint
     * 
     * @param int|string $mountpointId Mountpoint ID
     * @param string|null $secret Mountpoint secret (if required)
     * @return Promise
     */
    public function stopRecording($mountpointId, ?string $secret = null): Promise
    {
        $request = [
            'request' => 'recording',
            'id' => $mountpointId,
            'record' => false,
        ];

        if ($secret !== null) {
            $request['secret'] = $secret;
        }

        return $this->sendPluginRequest($request);
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
