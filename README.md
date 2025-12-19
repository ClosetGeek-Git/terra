"Terra - Proof of concept of Janus Admin Framework over ZeroMQ, not yet guaranteed to work!

Terra is a high-level PHP framework for interacting with Janus Gateway's Admin API over ZeroMQ. Built with ReactPHP, it provides asynchronous, event-driven communication with Janus Gateway, making it easy to manage sessions, plugins, and real-time events.

## Features

- **Asynchronous Communication**: Built on ReactPHP and ZeroMQ for non-blocking, efficient communication
- **Full Admin API Coverage**: Support for all Janus Gateway admin operations
- **Plugin-Specific Controllers**: Dedicated admin controllers for:
  - VideoRoom (room management, participant control)
  - VideoCall (call management)
  - Streaming (mountpoint administration)
  - EchoTest (diagnostics)
  - Record&Play (recording management)
- **Event Handling**: Real-time event processing with customizable handlers
- **Configurable**: Flexible configuration management with sensible defaults
- **Logging**: Built-in logging with Monolog
- **Error Handling**: Comprehensive exception handling for network and protocol errors

## Requirements

- PHP >= 7.4
- ZMQ Extension (`ext-zmq`)
- Janus Gateway with ZeroMQ transport enabled

## Installation

### 1. Install ZMQ Extension

```bash
# Ubuntu/Debian
sudo apt-get install libzmq3-dev php-zmq

# macOS (using Homebrew)
brew install zmq
pecl install zmq-beta
```

### 2. Install Terra via Composer

```bash
composer require closetgeek-git/terra
```

Or clone the repository and install dependencies:

```bash
git clone https://github.com/ClosetGeek-Git/terra.git
cd terra
composer install
```

## Quick Start

### Basic Usage

```php
<?php

require 'vendor/autoload.php';

use Terra\Admin\AdminClient;

// Configure the client
$config = [
    'janus' => [
        'admin_address' => 'tcp://localhost:7889',
        'admin_secret' => 'janusoverlord',
    ],
];

// Create client and connect
$client = new AdminClient($config);
$client->connect()->then(
    function () use ($client) {
        echo "Connected!\n";
        return $client->getInfo();
    }
)->then(
    function ($info) {
        echo "Server: " . json_encode($info, JSON_PRETTY_PRINT) . "\n";
    }
);

$client->run();
```

### VideoRoom Plugin

```php
use Terra\Plugin\VideoRoomAdmin;

$videoRoom = new VideoRoomAdmin($client);
$client->registerPlugin('videoroom', $videoRoom);

// List all rooms
$videoRoom->listRooms()->then(function ($rooms) {
    var_dump($rooms);
});

// Create a new room
$videoRoom->createRoom([
    'room' => 1234,
    'description' => 'My Test Room',
    'publishers' => 6,
    'audiocodec' => 'opus',
    'videocodec' => 'vp8',
])->then(function ($response) {
    echo "Room created!\n";
});
```

### Event Handling

```php
// Register event handler
$client->onEvent(function ($event) {
    echo "Event received: " . json_encode($event) . "\n";
});
```

## Configuration

Configuration can be passed to the AdminClient constructor:

```php
$config = [
    'janus' => [
        'admin_address' => 'tcp://localhost:7889',  // ZMQ admin endpoint
        'admin_secret' => 'janusoverlord',          // Admin API secret
        'timeout' => 30,                             // Request timeout in seconds
    ],
    'zmq' => [
        'persistent' => true,                        // Use persistent connections
        'linger' => 0,                              // Socket linger time
    ],
    'logging' => [
        'enabled' => true,                          // Enable logging
        'level' => 'info',                          // Log level
        'path' => null,                             // Log path (null = stdout)
    ],
];
```

You can also use environment variables:

```bash
export JANUS_ADMIN_ADDRESS="tcp://localhost:7889"
export JANUS_ADMIN_SECRET="janusoverlord"
export LOG_LEVEL="debug"
```

## Architecture

### Core Components

#### AdminClient
The main entry point for the framework. Handles connection management and provides access to general admin API methods.

**Methods:**
- `connect()` - Connect to Janus Gateway
- `disconnect()` - Disconnect from Janus Gateway
- `getInfo()` - Get server information
- `listSessions()` - List all sessions
- `listHandles($sessionId)` - List handles for a session
- `handleInfo($sessionId, $handleId)` - Get handle information
- `setLogLevel($level)` - Set Janus log level
- `getLogLevel()` - Get current log level
- `onEvent($callback)` - Register event handler
- `registerPlugin($name, $controller)` - Register plugin controller

#### ZmqTransport
Handles low-level ZeroMQ communication with ReactPHP integration.

**Features:**
- Asynchronous request/response handling
- Transaction ID management
- Timeout handling
- Event dispatching
- Promise-based API

#### ConfigManager
Manages configuration with dot notation support.

**Methods:**
- `get($key, $default)` - Get configuration value
- `set($key, $value)` - Set configuration value
- `has($key)` - Check if key exists
- `merge($config)` - Merge configuration

#### Logger
Logging wrapper using Monolog.

**Methods:**
- `debug($message, $context)` - Log debug message
- `info($message, $context)` - Log info message
- `warning($message, $context)` - Log warning message
- `error($message, $context)` - Log error message

### Plugin Controllers

#### VideoRoomAdmin
Manages video room operations.

**Methods:**
- `listRooms()` - List all rooms
- `createRoom($config)` - Create a new room
- `destroyRoom($roomId, $secret)` - Destroy a room
- `getRoomInfo($roomId)` - Get room information
- `listParticipants($roomId)` - List room participants
- `kickParticipant($roomId, $participantId, $secret)` - Kick a participant
- `setRecording($roomId, $record, $secret)` - Enable/disable recording
- `moderateParticipant($roomId, $participantId, $moderation, $secret)` - Moderate participant
- `editRoom($roomId, $config, $secret)` - Edit room configuration

#### VideoCallAdmin
Manages video call operations.

**Methods:**
- `listSessions()` - List all active calls
- `getUserInfo($username)` - Get user information
- `hangup($username)` - Hangup a call

#### StreamingAdmin
Manages streaming mountpoints.

**Methods:**
- `listMountpoints()` - List all mountpoints
- `getMountpointInfo($id)` - Get mountpoint information
- `createMountpoint($config)` - Create a new mountpoint
- `destroyMountpoint($id, $secret)` - Destroy a mountpoint
- `toggleMountpoint($id, $enabled, $secret)` - Enable/disable mountpoint
- `startRecording($id, $secret)` - Start recording
- `stopRecording($id, $secret)` - Stop recording

#### EchoTestAdmin
Manages echo test plugin.

**Methods:**
- `getStats()` - Get plugin statistics
- `listSessions()` - List active sessions

#### RecordPlayAdmin
Manages recording and playback.

**Methods:**
- `listRecordings()` - List all recordings
- `getRecordingInfo($id)` - Get recording information
- `deleteRecording($id)` - Delete a recording

## Examples

The `examples/` directory contains several demonstration scripts:

- **basic_usage.php** - Basic connection and server info
- **videoroom_example.php** - VideoRoom plugin usage
- **streaming_example.php** - Streaming plugin usage
- **event_handler.php** - Event handling demonstration
- **cli_tool.php** - Interactive CLI tool

Run examples:

```bash
php examples/basic_usage.php
php examples/videoroom_example.php
php examples/cli_tool.php
```

## Error Handling

Terra provides specific exception types for different error conditions:

- `ConnectionException` - Connection failures
- `InvalidJsonException` - JSON parsing errors
- `TimeoutException` - Request timeouts
- `TerraException` - Base exception type

Example:

```php
use Terra\Exception\ConnectionException;
use Terra\Exception\TimeoutException;

$client->connect()->then(
    function () use ($client) {
        return $client->getInfo();
    }
)->otherwise(
    function ($error) {
        if ($error instanceof ConnectionException) {
            echo "Connection failed: " . $error->getMessage() . "\n";
        } elseif ($error instanceof TimeoutException) {
            echo "Request timeout: " . $error->getMessage() . "\n";
        } else {
            echo "Error: " . $error->getMessage() . "\n";
        }
    }
);
```

## Testing

Terra includes example scripts that can be used for testing. To test with a real Janus instance:

1. Ensure Janus Gateway is running with ZeroMQ transport enabled
2. Configure `janus.transport.zmq.jcfg`:
   ```
   general: {
       enabled = true
       admin_base_path = "/admin"
   }
   admin: {
       admin_base_path = "/admin"
       enabled = true
       bind = "tcp://*:7889"
   }
   ```
3. Run example scripts:
   ```bash
   php examples/basic_usage.php
   ```

## Janus Gateway Configuration

To use Terra with Janus Gateway, you need to enable the ZeroMQ transport:

### janus.transport.zmq.jcfg

```
general: {
    enabled = true
    events = true
    json = "compact"
}

admin: {
    admin_enabled = true
    admin_base_path = "/admin"
    bind = "tcp://*:7889"
}
```

Make sure to set an admin secret in `janus.jcfg`:

```
admin_secret = "janusoverlord"
```

## Contributing

Contributions are welcome! Please feel free to submit issues or pull requests.

## License

MIT License

## Credits

Developed by ClosetGeek

## Support

For issues and questions:
- GitHub Issues: https://github.com/ClosetGeek-Git/terra/issues
- Documentation: https://github.com/ClosetGeek-Git/terra

## References

- [Janus Gateway](https://janus.conf.meetecho.com/)
- [Janus Admin API Documentation](https://janus.conf.meetecho.com/docs/admin.html)
- [ReactPHP](https://reactphp.org/)
- [ZeroMQ](https://zeromq.org/)" 
