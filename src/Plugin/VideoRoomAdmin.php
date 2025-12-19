<?php

namespace Terra\Plugin;

use React\Promise\Promise;
use Terra\Admin\AdminClient;

/**
 * Admin controller for Janus VideoRoom Plugin
 * 
 * Manages VideoRoom plugin administration including room creation, 
 * configuration, and participant management
 */
class VideoRoomAdmin
{
    /**
     * @var AdminClient Admin client instance
     */
    private $client;

    /**
     * @var string Plugin identifier
     */
    private $pluginId = 'janus.plugin.videoroom';

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
     * List all video rooms
     * 
     * @return Promise
     */
    public function listRooms(): Promise
    {
        return $this->sendPluginRequest([
            'request' => 'list',
        ]);
    }

    /**
     * Create a new video room
     * 
     * @param array $roomConfig Room configuration
     * @return Promise
     */
    public function createRoom(array $roomConfig): Promise
    {
        $request = array_merge([
            'request' => 'create',
        ], $roomConfig);

        return $this->sendPluginRequest($request);
    }

    /**
     * Destroy a video room
     * 
     * @param int|string $roomId Room ID
     * @param string|null $secret Room secret (if required)
     * @return Promise
     */
    public function destroyRoom($roomId, ?string $secret = null): Promise
    {
        $request = [
            'request' => 'destroy',
            'room' => $roomId,
        ];

        if ($secret !== null) {
            $request['secret'] = $secret;
        }

        return $this->sendPluginRequest($request);
    }

    /**
     * Get information about a specific room
     * 
     * @param int|string $roomId Room ID
     * @return Promise
     */
    public function getRoomInfo($roomId): Promise
    {
        return $this->sendPluginRequest([
            'request' => 'exists',
            'room' => $roomId,
        ]);
    }

    /**
     * List participants in a room
     * 
     * @param int|string $roomId Room ID
     * @return Promise
     */
    public function listParticipants($roomId): Promise
    {
        return $this->sendPluginRequest([
            'request' => 'listparticipants',
            'room' => $roomId,
        ]);
    }

    /**
     * Kick a participant from a room
     * 
     * @param int|string $roomId Room ID
     * @param int|string $participantId Participant ID
     * @param string|null $secret Room secret (if required)
     * @return Promise
     */
    public function kickParticipant($roomId, $participantId, ?string $secret = null): Promise
    {
        $request = [
            'request' => 'kick',
            'room' => $roomId,
            'id' => $participantId,
        ];

        if ($secret !== null) {
            $request['secret'] = $secret;
        }

        return $this->sendPluginRequest($request);
    }

    /**
     * Enable/disable recording for a room
     * 
     * @param int|string $roomId Room ID
     * @param bool $record Enable or disable recording
     * @param string|null $secret Room secret (if required)
     * @return Promise
     */
    public function setRecording($roomId, bool $record, ?string $secret = null): Promise
    {
        $request = [
            'request' => 'enable_recording',
            'room' => $roomId,
            'record' => $record,
        ];

        if ($secret !== null) {
            $request['secret'] = $secret;
        }

        return $this->sendPluginRequest($request);
    }

    /**
     * Moderate a participant (mute/unmute audio/video)
     * 
     * @param int|string $roomId Room ID
     * @param int|string $participantId Participant ID
     * @param array $moderation Moderation settings (mute_audio, mute_video, etc.)
     * @param string|null $secret Room secret (if required)
     * @return Promise
     */
    public function moderateParticipant($roomId, $participantId, array $moderation, ?string $secret = null): Promise
    {
        $request = array_merge([
            'request' => 'moderate',
            'room' => $roomId,
            'id' => $participantId,
        ], $moderation);

        if ($secret !== null) {
            $request['secret'] = $secret;
        }

        return $this->sendPluginRequest($request);
    }

    /**
     * Edit room configuration
     * 
     * @param int|string $roomId Room ID
     * @param array $config Configuration updates
     * @param string|null $secret Room secret (if required)
     * @return Promise
     */
    public function editRoom($roomId, array $config, ?string $secret = null): Promise
    {
        $request = array_merge([
            'request' => 'edit',
            'room' => $roomId,
        ], $config);

        if ($secret !== null) {
            $request['secret'] = $secret;
        }

        return $this->sendPluginRequest($request);
    }

    /**
     * List forwarders for a room
     * 
     * @param int|string $roomId Room ID
     * @param string|null $secret Room secret (if required)
     * @return Promise
     */
    public function listForwarders($roomId, ?string $secret = null): Promise
    {
        $request = [
            'request' => 'listforwarders',
            'room' => $roomId,
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
