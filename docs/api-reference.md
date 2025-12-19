# Terra API Reference

Complete API reference for Terra Janus Admin Framework.

## Table of Contents

- [AdminClient](#adminclient)
- [Plugin Controllers](#plugin-controllers)
  - [VideoRoomAdmin](#videoroomadmin)
  - [VideoCallAdmin](#videocalladmin)
  - [StreamingAdmin](#streamingadmin)
  - [EchoTestAdmin](#echotestadmin)
  - [RecordPlayAdmin](#recordplayadmin)
- [Transport Layer](#transport-layer)
- [Configuration](#configuration)
- [Logging](#logging)
- [Exceptions](#exceptions)

---

## AdminClient

The main client class for interacting with Janus Gateway Admin API.

### Constructor

```php
public function __construct(array $config = [], ?LoopInterface $loop = null)
```

**Parameters:**
- `$config` (array): Configuration array
- `$loop` (LoopInterface|null): Optional ReactPHP event loop

**Example:**
```php
$client = new AdminClient([
    'janus' => [
        'admin_address' => 'tcp://localhost:7889',
        'admin_secret' => 'janusoverlord',
    ],
]);
```

### Methods

#### connect()

Connect to Janus Gateway.

```php
public function connect(): Promise
```

**Returns:** Promise that resolves when connected

**Example:**
```php
$client->connect()->then(function () {
    echo "Connected!\n";
});
```

#### disconnect()

Disconnect from Janus Gateway.

```php
public function disconnect(): void
```

#### getInfo()

Get server information.

```php
public function getInfo(): Promise
```

**Returns:** Promise resolving to server info array

**Example:**
```php
$client->getInfo()->then(function ($info) {
    echo "Server: {$info['name']}\n";
    echo "Version: {$info['version_string']}\n";
});
```

#### listSessions()

List all active sessions.

```php
public function listSessions(): Promise
```

**Returns:** Promise resolving to sessions array

#### listHandles($sessionId)

List handles for a specific session.

```php
public function listHandles($sessionId): Promise
```

**Parameters:**
- `$sessionId` (int|string): Session ID

**Returns:** Promise resolving to handles array

#### handleInfo($sessionId, $handleId)

Get information about a specific handle.

```php
public function handleInfo($sessionId, $handleId): Promise
```

**Parameters:**
- `$sessionId` (int|string): Session ID
- `$handleId` (int|string): Handle ID

**Returns:** Promise resolving to handle info

#### setLogLevel($level)

Set Janus log level.

```php
public function setLogLevel(string $level): Promise
```

**Parameters:**
- `$level` (string): Log level ('none', 'fatal', 'error', 'warn', 'info', 'verb', 'debug', 'huge')

**Returns:** Promise resolving when level is set

#### getLogLevel()

Get current Janus log level.

```php
public function getLogLevel(): Promise
```

**Returns:** Promise resolving to log level info

#### startPcap($handleId, $folder, $filename, $truncate = 0)

Start packet capture for a handle.

```php
public function startPcap($handleId, string $folder, string $filename, int $truncate = 0): Promise
```

**Parameters:**
- `$handleId` (int|string): Handle ID
- `$folder` (string): Output folder path
- `$filename` (string): Output filename
- `$truncate` (int): Truncate size in bytes (0 = no truncate)

#### stopPcap($handleId)

Stop packet capture for a handle.

```php
public function stopPcap($handleId): Promise
```

**Parameters:**
- `$handleId` (int|string): Handle ID

#### onEvent($handler)

Register an event handler for Janus events.

```php
public function onEvent(callable $handler): void
```

**Parameters:**
- `$handler` (callable): Event handler callback

**Example:**
```php
$client->onEvent(function ($event) {
    echo "Event: {$event['janus']}\n";
});
```

#### registerPlugin($name, $controller)

Register a plugin controller.

```php
public function registerPlugin(string $name, object $controller): void
```

**Parameters:**
- `$name` (string): Plugin name
- `$controller` (object): Plugin controller instance

**Example:**
```php
$videoRoom = new VideoRoomAdmin($client);
$client->registerPlugin('videoroom', $videoRoom);
```

#### plugin($name)

Get a registered plugin controller.

```php
public function plugin(string $name): ?object
```

**Parameters:**
- `$name` (string): Plugin name

**Returns:** Plugin controller or null

---

## Plugin Controllers

### VideoRoomAdmin

Admin controller for Janus VideoRoom plugin.

#### Constructor

```php
public function __construct(AdminClient $client)
```

#### listRooms()

List all video rooms.

```php
public function listRooms(): Promise
```

#### createRoom(array $config)

Create a new video room.

```php
public function createRoom(array $config): Promise
```

**Parameters:**
- `$config` (array): Room configuration

**Config Options:**
- `room` (int|string): Room ID
- `description` (string): Room description
- `publishers` (int): Max publishers
- `audiocodec` (string): Audio codec ('opus', 'pcma', 'pcmu', 'g722')
- `videocodec` (string): Video codec ('vp8', 'vp9', 'h264')
- `record` (bool): Enable recording
- `rec_dir` (string): Recording directory

**Example:**
```php
$videoRoom->createRoom([
    'room' => 1234,
    'description' => 'My Room',
    'publishers' => 6,
    'audiocodec' => 'opus',
    'videocodec' => 'vp8',
    'record' => true,
]);
```

#### destroyRoom($roomId, $secret = null)

Destroy a video room.

```php
public function destroyRoom($roomId, ?string $secret = null): Promise
```

#### getRoomInfo($roomId)

Get information about a room.

```php
public function getRoomInfo($roomId): Promise
```

#### listParticipants($roomId)

List participants in a room.

```php
public function listParticipants($roomId): Promise
```

#### kickParticipant($roomId, $participantId, $secret = null)

Kick a participant from a room.

```php
public function kickParticipant($roomId, $participantId, ?string $secret = null): Promise
```

#### setRecording($roomId, $record, $secret = null)

Enable or disable recording for a room.

```php
public function setRecording($roomId, bool $record, ?string $secret = null): Promise
```

#### moderateParticipant($roomId, $participantId, array $moderation, $secret = null)

Moderate a participant (mute/unmute).

```php
public function moderateParticipant($roomId, $participantId, array $moderation, ?string $secret = null): Promise
```

**Moderation Options:**
- `mute_audio` (bool): Mute audio
- `mute_video` (bool): Mute video
- `mute_data` (bool): Mute data

#### editRoom($roomId, array $config, $secret = null)

Edit room configuration.

```php
public function editRoom($roomId, array $config, ?string $secret = null): Promise
```

#### listForwarders($roomId, $secret = null)

List forwarders for a room.

```php
public function listForwarders($roomId, ?string $secret = null): Promise
```

---

### VideoCallAdmin

Admin controller for Janus VideoCall plugin.

#### listSessions()

List all active video call sessions.

```php
public function listSessions(): Promise
```

#### getUserInfo($username)

Get information about a specific user.

```php
public function getUserInfo(string $username): Promise
```

#### hangup($username)

Hangup a call for a specific user.

```php
public function hangup(string $username): Promise
```

---

### StreamingAdmin

Admin controller for Janus Streaming plugin.

#### listMountpoints()

List all streaming mountpoints.

```php
public function listMountpoints(): Promise
```

#### getMountpointInfo($id)

Get information about a specific mountpoint.

```php
public function getMountpointInfo($id): Promise
```

#### createMountpoint(array $config)

Create a new streaming mountpoint.

```php
public function createMountpoint(array $config): Promise
```

**Config Options:**
- `id` (int|string): Mountpoint ID
- `name` (string): Mountpoint name
- `description` (string): Description
- `type` (string): Type ('rtp', 'live', 'ondemand', 'rtsp')
- `audio` (bool): Enable audio
- `video` (bool): Enable video
- `audioport` (int): Audio RTP port
- `videoport` (int): Video RTP port

#### destroyMountpoint($id, $secret = null)

Destroy a mountpoint.

```php
public function destroyMountpoint($id, ?string $secret = null): Promise
```

#### toggleMountpoint($id, $enabled, $secret = null)

Enable or disable a mountpoint.

```php
public function toggleMountpoint($id, bool $enabled, ?string $secret = null): Promise
```

#### startRecording($id, $secret = null)

Start recording on a mountpoint.

```php
public function startRecording($id, ?string $secret = null): Promise
```

#### stopRecording($id, $secret = null)

Stop recording on a mountpoint.

```php
public function stopRecording($id, ?string $secret = null): Promise
```

---

### EchoTestAdmin

Admin controller for Janus EchoTest plugin.

#### getStats()

Get plugin statistics.

```php
public function getStats(): Promise
```

#### listSessions()

List active echo test sessions.

```php
public function listSessions(): Promise
```

---

### RecordPlayAdmin

Admin controller for Janus Record&Play plugin.

#### listRecordings()

List all available recordings.

```php
public function listRecordings(): Promise
```

#### getRecordingInfo($id)

Get information about a specific recording.

```php
public function getRecordingInfo($id): Promise
```

#### deleteRecording($id)

Delete a recording.

```php
public function deleteRecording($id): Promise
```

---

## Configuration

### ConfigManager

Manages framework configuration.

#### get($key, $default = null)

Get a configuration value.

```php
public function get(string $key, $default = null)
```

**Example:**
```php
$address = $config->get('janus.admin_address');
$timeout = $config->get('janus.timeout', 30);
```

#### set($key, $value)

Set a configuration value.

```php
public function set(string $key, $value): void
```

#### has($key)

Check if a configuration key exists.

```php
public function has(string $key): bool
```

#### merge(array $config)

Merge configuration.

```php
public function merge(array $config): void
```

---

## Exceptions

### TerraException

Base exception class for all Terra exceptions.

### ConnectionException

Thrown when connection to Janus fails.

### InvalidJsonException

Thrown when invalid JSON is encountered.

### TimeoutException

Thrown when a request times out.

**Example:**
```php
use Terra\Exception\TimeoutException;

$client->getInfo()->otherwise(function ($error) {
    if ($error instanceof TimeoutException) {
        echo "Request timed out\n";
    }
});
```

---

## Promise Handling

All asynchronous methods return ReactPHP Promises.

### Basic Usage

```php
$client->getInfo()->then(
    function ($result) {
        // Success handler
        echo "Success: " . json_encode($result) . "\n";
    },
    function ($error) {
        // Error handler
        echo "Error: " . $error->getMessage() . "\n";
    }
);
```

### Chaining

```php
$client->connect()
    ->then(function () use ($client) {
        return $client->getInfo();
    })
    ->then(function ($info) use ($client) {
        echo "Connected to: {$info['name']}\n";
        return $client->listSessions();
    })
    ->then(function ($sessions) {
        echo "Sessions: " . count($sessions) . "\n";
    });
```

### Error Handling

```php
$client->getInfo()->otherwise(function ($error) {
    echo "Error: " . $error->getMessage() . "\n";
});
```
