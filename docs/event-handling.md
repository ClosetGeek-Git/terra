# Event Handling in Terra

This guide explains how to handle events from Janus Gateway using Terra.

## Overview

Janus Gateway can send various events over the ZeroMQ transport, such as:
- Session events (created, destroyed)
- Handle events (attached, detached)
- Plugin events (specific to each plugin)
- WebRTC events (ICE state changes, media state, etc.)

Terra provides a flexible event handling system to capture and process these events.

## Basic Event Handling

### Registering an Event Handler

Use the `onEvent()` method to register a callback function:

```php
use Terra\Admin\AdminClient;

$client = new AdminClient($config);

$client->onEvent(function ($event) {
    echo "Received event: " . json_encode($event, JSON_PRETTY_PRINT) . "\n";
});

$client->connect();
$client->run();
```

### Multiple Event Handlers

You can register multiple event handlers:

```php
// Handler 1: Log all events
$client->onEvent(function ($event) {
    $logger->info('Event received', $event);
});

// Handler 2: Handle specific events
$client->onEvent(function ($event) {
    if (isset($event['emitter']) && $event['emitter'] === 'session') {
        handleSessionEvent($event);
    }
});
```

## Event Types

### Session Events

```php
$client->onEvent(function ($event) {
    if ($event['type'] === 'session') {
        switch ($event['event']) {
            case 'created':
                echo "Session created: {$event['id']}\n";
                break;
            case 'destroyed':
                echo "Session destroyed: {$event['id']}\n";
                break;
        }
    }
});
```

### Handle Events

```php
$client->onEvent(function ($event) {
    if ($event['type'] === 'handle') {
        switch ($event['event']) {
            case 'attached':
                echo "Handle attached: {$event['id']}\n";
                echo "Plugin: {$event['plugin']}\n";
                break;
            case 'detached':
                echo "Handle detached: {$event['id']}\n";
                break;
        }
    }
});
```

### WebRTC Events

```php
$client->onEvent(function ($event) {
    if ($event['type'] === 'webrtc') {
        switch ($event['event']) {
            case 'up':
                echo "WebRTC is up for handle {$event['handle_id']}\n";
                break;
            case 'down':
                echo "WebRTC is down for handle {$event['handle_id']}\n";
                break;
        }
    }
});
```

## Plugin-Specific Events

### VideoRoom Events

```php
$client->onEvent(function ($event) {
    if (isset($event['plugin']) && $event['plugin'] === 'janus.plugin.videoroom') {
        $pluginData = $event['plugindata']['data'] ?? [];
        
        switch ($pluginData['videoroom'] ?? '') {
            case 'event':
                echo "VideoRoom event: {$pluginData['event']}\n";
                if (isset($pluginData['room'])) {
                    echo "Room: {$pluginData['room']}\n";
                }
                break;
            case 'joined':
                echo "Participant joined room {$pluginData['room']}\n";
                break;
            case 'leaving':
                echo "Participant leaving room {$pluginData['room']}\n";
                break;
        }
    }
});
```

### Streaming Events

```php
$client->onEvent(function ($event) {
    if (isset($event['plugin']) && $event['plugin'] === 'janus.plugin.streaming') {
        $pluginData = $event['plugindata']['data'] ?? [];
        
        if (isset($pluginData['streaming'])) {
            echo "Streaming event: {$pluginData['streaming']}\n";
            if (isset($pluginData['id'])) {
                echo "Mountpoint: {$pluginData['id']}\n";
            }
        }
    }
});
```

## Advanced Event Processing

### Event Filtering

Create specialized handlers for different event types:

```php
class EventProcessor
{
    private $sessionHandlers = [];
    private $handleHandlers = [];
    private $pluginHandlers = [];

    public function __construct($client)
    {
        $client->onEvent([$this, 'processEvent']);
    }

    public function processEvent($event)
    {
        $type = $event['type'] ?? 'unknown';

        switch ($type) {
            case 'session':
                $this->processSessionEvent($event);
                break;
            case 'handle':
                $this->processHandleEvent($event);
                break;
            case 'webrtc':
                $this->processWebRTCEvent($event);
                break;
        }

        if (isset($event['plugin'])) {
            $this->processPluginEvent($event);
        }
    }

    public function onSession(callable $handler)
    {
        $this->sessionHandlers[] = $handler;
    }

    private function processSessionEvent($event)
    {
        foreach ($this->sessionHandlers as $handler) {
            $handler($event);
        }
    }

    // ... other methods
}

// Usage
$processor = new EventProcessor($client);
$processor->onSession(function ($event) {
    echo "Session event: {$event['event']}\n";
});
```

### Event Statistics

Track event statistics:

```php
class EventStats
{
    private $counts = [];
    private $startTime;

    public function __construct($client)
    {
        $this->startTime = time();
        $client->onEvent([$this, 'trackEvent']);
    }

    public function trackEvent($event)
    {
        $type = $event['type'] ?? 'unknown';
        $this->counts[$type] = ($this->counts[$type] ?? 0) + 1;
    }

    public function getStats()
    {
        $duration = time() - $this->startTime;
        return [
            'duration' => $duration,
            'counts' => $this->counts,
            'rates' => array_map(
                function ($count) use ($duration) {
                    return $duration > 0 ? round($count / $duration, 2) : 0;
                },
                $this->counts
            ),
        ];
    }
}

// Usage
$stats = new EventStats($client);

// Later...
print_r($stats->getStats());
```

### Event Storage

Store events for later analysis:

```php
class EventStore
{
    private $events = [];
    private $maxEvents;

    public function __construct($client, $maxEvents = 1000)
    {
        $this->maxEvents = $maxEvents;
        $client->onEvent([$this, 'storeEvent']);
    }

    public function storeEvent($event)
    {
        $event['_timestamp'] = microtime(true);
        $this->events[] = $event;

        // Keep only the last N events
        if (count($this->events) > $this->maxEvents) {
            array_shift($this->events);
        }
    }

    public function getEvents($filter = null)
    {
        if ($filter === null) {
            return $this->events;
        }

        return array_filter($this->events, $filter);
    }

    public function getEventsByType($type)
    {
        return $this->getEvents(function ($event) use ($type) {
            return ($event['type'] ?? '') === $type;
        });
    }
}

// Usage
$store = new EventStore($client);

// Later...
$sessionEvents = $store->getEventsByType('session');
echo "Total session events: " . count($sessionEvents) . "\n";
```

## Event Loop Integration

### Running the Event Loop

Terra uses ReactPHP's event loop. To keep listening for events:

```php
$client->connect()->then(function () use ($client) {
    echo "Connected and listening for events...\n";
});

// This will run indefinitely
$client->run();
```

### Stopping the Event Loop

To stop processing events:

```php
$client->onEvent(function ($event) use ($client) {
    if ($event['type'] === 'shutdown') {
        echo "Shutdown event received, stopping...\n";
        $client->getLoop()->stop();
    }
});
```

### Periodic Event Processing

Process events periodically:

```php
$client->connect()->then(function () use ($client, $processor) {
    // Process stats every 10 seconds
    $client->getLoop()->addPeriodicTimer(10, function () use ($processor) {
        $stats = $processor->getStats();
        echo "Event stats: " . json_encode($stats) . "\n";
    });
});

$client->run();
```

## Real-World Examples

### Monitor Room Activity

```php
$roomStats = [];

$client->onEvent(function ($event) use (&$roomStats) {
    if (isset($event['plugin']) && $event['plugin'] === 'janus.plugin.videoroom') {
        $data = $event['plugindata']['data'] ?? [];
        $room = $data['room'] ?? null;

        if ($room !== null) {
            if (!isset($roomStats[$room])) {
                $roomStats[$room] = ['joins' => 0, 'leaves' => 0];
            }

            if (($data['videoroom'] ?? '') === 'joined') {
                $roomStats[$room]['joins']++;
            } elseif (($data['videoroom'] ?? '') === 'leaving') {
                $roomStats[$room]['leaves']++;
            }
        }
    }
});

// Print stats periodically
$client->getLoop()->addPeriodicTimer(60, function () use (&$roomStats) {
    echo "Room Statistics:\n";
    foreach ($roomStats as $room => $stats) {
        echo "Room $room: {$stats['joins']} joins, {$stats['leaves']} leaves\n";
    }
});
```

### Alert on Errors

```php
$client->onEvent(function ($event) {
    if (isset($event['error'])) {
        $error = $event['error'];
        echo "ERROR: " . ($error['reason'] ?? 'Unknown error') . "\n";
        
        // Send alert (email, Slack, etc.)
        sendAlert("Janus error: " . json_encode($error));
    }
});
```

### Connection Monitoring

```php
$connectionStates = [];

$client->onEvent(function ($event) use (&$connectionStates) {
    if ($event['type'] === 'webrtc') {
        $handleId = $event['handle_id'] ?? null;
        $state = $event['event'] ?? null;

        if ($handleId !== null && $state !== null) {
            $connectionStates[$handleId] = [
                'state' => $state,
                'timestamp' => time(),
            ];

            if ($state === 'down') {
                echo "Connection lost for handle $handleId\n";
            }
        }
    }
});
```

## Best Practices

1. **Keep handlers lightweight**: Event handlers should process quickly to avoid blocking the event loop
2. **Use filtering**: Filter events early to avoid processing unnecessary data
3. **Log events**: Always log important events for debugging and auditing
4. **Handle errors**: Wrap event handlers in try-catch blocks
5. **Limit storage**: Don't store unlimited events in memory
6. **Use timers**: Process accumulated events periodically rather than individually

## Debugging Events

Enable debug logging to see all events:

```php
$config = [
    'logging' => [
        'enabled' => true,
        'level' => 'debug',
    ],
];

$client = new AdminClient($config);
```

Or create a debug handler:

```php
$client->onEvent(function ($event) {
    file_put_contents(
        '/tmp/janus-events.log',
        date('Y-m-d H:i:s') . ' ' . json_encode($event) . "\n",
        FILE_APPEND
    );
});
```

## Further Reading

- [Janus Event Documentation](https://janus.conf.meetecho.com/docs/eventhandlers.html)
- [ReactPHP Documentation](https://reactphp.org/)
- [Promise API](https://github.com/reactphp/promise)
