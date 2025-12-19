<?php

namespace Terra\Plugin;

use React\Promise\Promise;
use Terra\Admin\AdminClient;

/**
 * Admin controller for Janus EchoTest Plugin
 * 
 * Manages EchoTest plugin administration
 */
class EchoTestAdmin
{
    /**
     * @var AdminClient Admin client instance
     */
    private $client;

    /**
     * @var string Plugin identifier
     */
    private $pluginId = 'janus.plugin.echotest';

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
     * Get statistics about the EchoTest plugin
     * 
     * @return Promise
     */
    public function getStats(): Promise
    {
        return $this->sendPluginRequest([
            'request' => 'stats',
        ]);
    }

    /**
     * List all active echotest sessions
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
