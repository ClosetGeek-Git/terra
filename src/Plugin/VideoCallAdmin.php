<?php

namespace Terra\Plugin;

use React\Promise\Promise;
use Terra\Admin\AdminClient;

/**
 * Admin controller for Janus VideoCall Plugin
 * 
 * Manages VideoCall plugin administration
 */
class VideoCallAdmin
{
    /**
     * @var AdminClient Admin client instance
     */
    private $client;

    /**
     * @var string Plugin identifier
     */
    private $pluginId = 'janus.plugin.videocall';

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
     * List all active videocall sessions
     * 
     * @return Promise
     */
    public function listSessions(): Promise
    {
        return $this->sendPluginRequest([
            'request' => 'list',
        ]);
    }

    /**
     * Get information about a specific videocall room/user
     * 
     * @param string $username Username to query
     * @return Promise
     */
    public function getUserInfo(string $username): Promise
    {
        return $this->sendPluginRequest([
            'request' => 'info',
            'username' => $username,
        ]);
    }

    /**
     * Forcefully hangup a call
     * 
     * @param string $username Username to hangup
     * @return Promise
     */
    public function hangup(string $username): Promise
    {
        return $this->sendPluginRequest([
            'request' => 'hangup',
            'username' => $username,
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
