Terra - High-level Janus Admin Framework with Multi-Transport Support

Terra is a high-level PHP framework for interacting with Janus Gateway's Admin API. Built with ReactPHP, it provides asynchronous, event-driven communication with Janus Gateway through multiple transport options, making it easy to manage sessions, plugins, and real-time events.

## Features

- **Multiple Transport Options**: Support for ZeroMQ, HTTP/REST API, and UnixSocket
- **Asynchronous Communication**: Built on ReactPHP for non-blocking, efficient communication
- **Full Admin API Coverage**: Support for all Janus Gateway admin operations
- **Plugin-Specific Controllers**: Dedicated admin controllers for:
  - VideoRoom (room management, participant control)
  - VideoCall (call management)
  - Streaming (mountpoint administration)
  - EchoTest (diagnostics)
  - Record&Play (recording management)
- **Event Handling**: Real-time event processing with customizable handlers
- **ZMQ Implementation Fallback**: Automatic fallback between ZMQ implementations
- **HTTP Long Polling**: Support for event streaming via HTTP long polling
- **SOCK_SEQPACKET Support**: Native Unix socket communication with fallback mechanisms
- **Configurable**: Flexible configuration management with sensible defaults
- **Logging**: Built-in logging with Monolog
- **Error Handling**: Comprehensive exception handling for network and protocol errors

## Requirements

- PHP >= 7.4
- Janus Gateway with desired transport(s) enabled
- For ZMQ: ZMQ Extension (`ext-zmq`)
- For HTTP: No special requirements
- For UnixSocket: Unix-like OS (not supported on Windows)

## Installation

### 1. Install Janus Gateway

Use the provided setup script to install and configure Janus with all transport options:

```bash
sudo ./setup-janus.sh
```

This script will:
- Install Janus Gateway and development files
- Configure HTTP/REST API transport (port 7088)
- Configure UnixSocket transport
- Attempt to install ZeroMQ transport
- Set up proper configuration files
- Start the Janus service

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

### 3. Optional: Install ZMQ Extension (for ZeroMQ transport)

```bash
# Ubuntu/Debian
sudo apt-get install libzmq3-dev php-zmq

# macOS (using Homebrew)
brew install zmq
pecl install zmq-beta
```

## Quick Start

### HTTP/REST API Transport (Recommended)

```php
<?php

require 'vendor/autoload.php';

use Terra\Admin\AdminClient;

// Configure the client for HTTP transport
$config = [
    'janus' => [
        'transport' => 'http',
        'http_address' => 'http://localhost:7088/admin',
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

### UnixSocket Transport

```php
$config = [
    'janus' => [
        'transport' => 'unix',
        'unix_socket_path' => '/var/run/janus/janus-admin.sock',
        'admin_secret' => 'janusoverlord',
    ],
];

$client = new AdminClient($config);
// ... rest of the code
```

### ZeroMQ Transport

```php
$config = [
    'janus' => [
        'transport' => 'zmq',
        'admin_address' => 'tcp://localhost:7889',
        'admin_secret' => 'janusoverlord',
    ],
    'zmq' => [
        'preferred_implementation' => 'auto', // auto, ClosetGeek\ReactPHPZMQ\Context, etc.
    ],
];

$client = new AdminClient($config);
// ... rest of the code
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

Terra supports multiple transport configurations. Choose the one that best fits your needs:

### HTTP/REST API Configuration

```php
$config = [
    'janus' => [
        'transport' => 'http',
        'http_address' => 'http://localhost:7088/admin',  // HTTP admin endpoint
        'http_event_address' => 'http://localhost:7088/admin',  // For long polling events
        'admin_secret' => 'janusoverlord',                // Admin API secret
        'timeout' => 30,                                   // Request timeout in seconds
    ],
    'http' => [
        'long_polling' => true,                           // Enable long polling for events
        'long_poll_timeout' => 30,                        // Long poll timeout
    ],
    'logging' => [
        'enabled' => true,                                // Enable logging
        'level' => 'info',                                // Log level
        'path' => null,                                   // Log path (null = stdout)
    ],
];
```

### UnixSocket Configuration

```php
$config = [
    'janus' => [
        'transport' => 'unix',
        'unix_socket_path' => '/var/run/janus/janus-admin.sock',  // Socket path
        'admin_secret' => 'janusoverlord',                         // Admin API secret
        'timeout' => 30,                                           // Request timeout
    ],
    'logging' => [
        'enabled' => true,
        'level' => 'info',
    ],
];
```

### ZeroMQ Configuration

```php
$config = [
    'janus' => [
        'transport' => 'zmq',
        'admin_address' => 'tcp://localhost:7889',  // ZMQ admin endpoint
        'admin_secret' => 'janusoverlord',          // Admin API secret
        'timeout' => 30,                            // Request timeout in seconds
    ],
    'zmq' => [
        'persistent' => true,                       // Use persistent connections
        'linger' => 0,                              // Socket linger time
        'preferred_implementation' => 'auto',       // ZMQ implementation (auto, or specific class)
    ],
    'logging' => [
        'enabled' => true,
        'level' => 'info',
        'path' => null,
    ],
];
```

### Environment Variables

You can also use environment variables:

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

#### Transport Layer
Terra supports multiple transport implementations through a common interface.

**Available Transports:**
- `ZmqTransport` - ZeroMQ transport with automatic implementation fallback
- `HttpTransport` - HTTP/REST API transport with long polling support
- `UnixSocketTransport` - Unix domain socket transport with SOCK_SEQPACKET support

**Common Features:**
- Asynchronous request/response handling
- Transaction ID management
- Timeout handling
- Event dispatching
- Promise-based API

**ZMQ Implementation Fallback:**
Terra automatically tries the following ZMQ implementations in order:
1. @ClosetGeek-Git/ReactPHPZMQ (if available)
2. @friends-of-reactphp/zmq (if available)
3. react/zmq (default fallback)

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

**Note:** AdminClient works transparently with any transport. Simply configure the desired transport in the configuration.

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

The `examples/` directory contains demonstration scripts for all transport options:

- **basic_usage.php** - Basic connection and server info (ZMQ transport)
- **http_transport_example.php** - HTTP/REST API transport usage
- **unixsocket_transport_example.php** - UnixSocket transport usage
- **videoroom_example.php** - VideoRoom plugin usage
- **streaming_example.php** - Streaming plugin usage
- **event_handler.php** - Event handling demonstration
- **cli_tool.php** - Interactive CLI tool

Run examples:

```bash
# HTTP transport (recommended for testing)
php examples/http_transport_example.php

# UnixSocket transport
php examples/unixsocket_transport_example.php

# ZMQ transport (requires ext-zmq)
php examples/basic_usage.php

# Other examples
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

Terra includes comprehensive integration tests for all transport options and admin features.

### Comprehensive Admin Test Suite

The admin test suite provides complete coverage of all high-level admin features:

```bash
# Run complete admin test suite
./run-admin-tests.sh

# Run with verbose output
./run-admin-tests.sh --verbose

# Run only integration tests
./run-admin-tests.sh --integration-only
```

The admin test suite includes:
- **Admin Client Tests**: Server info, session management, log levels, event handling
- **VideoRoom Plugin Tests**: Room management, participants, recording, forwarders
- **Streaming Plugin Tests**: Mountpoint management, recording, configuration
- **Other Plugin Tests**: VideoCall, EchoTest, RecordPlay features
- **Transport Tests**: HTTP, UnixSocket, and ZeroMQ validation

### Automated Setup and Testing

Use the provided scripts for quick setup and testing:

```bash
# 1. Install and configure Janus Gateway
sudo ./setup-janus.sh

# 2. Run all integration tests
./run-integration-tests.sh

# 3. Run admin-specific tests
./run-admin-tests.sh
```

The test runners will:
- Check all prerequisites
- Verify transport availability
- Run tests for HTTP, UnixSocket, and ZMQ (if available)
- Provide detailed troubleshooting guidance for any failures

### Manual Testing

You can also test individual components:

```bash
# Unit tests
vendor/bin/phpunit --testsuite Unit

# All integration tests
vendor/bin/phpunit --testsuite Integration

# Specific test suites
vendor/bin/phpunit tests/Integration/AdminClientTest.php
vendor/bin/phpunit tests/Integration/VideoRoomAdminTest.php
vendor/bin/phpunit tests/Integration/StreamingAdminTest.php
vendor/bin/phpunit tests/Integration/PluginAdminTest.php

# Transport-specific tests
vendor/bin/phpunit --testsuite Integration --filter HttpTransportTest
vendor/bin/phpunit --testsuite Integration --filter UnixSocketTransportTest
vendor/bin/phpunit --testsuite Integration --filter ZmqTransportTest
```

### Test Documentation

Detailed documentation for tests is available in:
- `tests/Integration/README.md` - Comprehensive test suite documentation
- Includes troubleshooting guides, environment setup, and usage examples

### Test Environment Variables

Configure test environment:

```bash
export JANUS_HTTP_ADDRESS="http://localhost:7088/admin"
export JANUS_UNIX_SOCKET="/var/run/janus/janus-admin.sock"
export JANUS_ADMIN_ADDRESS="tcp://localhost:7889"
export JANUS_ADMIN_SECRET="janusoverlord"
```

## Janus Gateway Configuration

Terra can automatically configure Janus using the setup script, or you can configure manually:

### Automated Setup (Recommended)

```bash
sudo ./setup-janus.sh
```

This will configure all transports and start Janus.

### Manual Configuration

#### 1. Main Configuration (janus.jcfg)

```
general: {
    configs_folder = "/etc/janus"
    plugins_folder = "/usr/lib/janus/plugins"
    transports_folder = "/usr/lib/janus/transports"
    admin_secret = "janusoverlord"
    api_secret = "janussecret"
}
```

#### 2. HTTP Transport (janus.transport.http.jcfg)

```
general: {
    json = "indented"
    http = true
    port = 8088
    admin_base_path = "/admin"
}

admin: {
    admin_base_path = "/admin"
    admin_http = true
    admin_port = 7088
}
```

#### 3. UnixSocket Transport (janus.transport.pfunix.jcfg)

```
general: {
    enabled = true
    type = "SOCK_SEQPACKET"
}

admin: {
    admin_enabled = true
    admin_path = "/var/run/janus/janus-admin.sock"
}
```

#### 4. ZeroMQ Transport (janus.transport.zmq.jcfg)

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

After configuration, restart Janus:

```bash
sudo systemctl restart janus
```

## Troubleshooting

### Transport Connection Issues

#### HTTP Transport
```bash
# Check if HTTP transport is listening
curl -X POST http://localhost:7088/admin \
  -H 'Content-Type: application/json' \
  -d '{"janus":"info","admin_secret":"janusoverlord","transaction":"test"}'

# Check port
netstat -tlnp | grep 7088
```

#### UnixSocket Transport
```bash
# Check if socket exists
ls -la /var/run/janus/janus-admin.sock

# Check permissions
stat /var/run/janus/janus-admin.sock

# Test socket
echo '{"janus":"info","admin_secret":"janusoverlord","transaction":"test"}' | \
  socat - UNIX-CONNECT:/var/run/janus/janus-admin.sock
```

#### ZMQ Transport
```bash
# Check if ZMQ extension is loaded
php -m | grep zmq

# Check if port is listening
netstat -tlnp | grep 7889

# Install ZMQ extension if missing
sudo apt-get install libzmq3-dev php-zmq
```

### General Janus Issues

```bash
# Check Janus status
sudo systemctl status janus

# View Janus logs
sudo journalctl -u janus -f
sudo tail -f /var/log/janus/janus.log

# Restart Janus
sudo systemctl restart janus

# Re-run setup script
sudo ./setup-janus.sh
```

### Running Integration Tests with Verbose Output

```bash
# Verbose mode
./run-integration-tests.sh -v

# View test logs
cat /tmp/terra-test-results-*.log
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
