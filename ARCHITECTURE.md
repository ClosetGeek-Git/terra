# Terra Architecture

## System Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                        Application Layer                         │
│  (Your PHP Application using Terra Framework)                   │
└──────────────────────────┬──────────────────────────────────────┘
                           │
                           ↓
┌─────────────────────────────────────────────────────────────────┐
│                      Terra Admin Framework                       │
├─────────────────────────────────────────────────────────────────┤
│  ┌──────────────────┐  ┌────────────────────────────────────┐  │
│  │   AdminClient    │  │      Plugin Controllers           │  │
│  │   - connect()    │  │  - VideoRoomAdmin                 │  │
│  │   - getInfo()    │  │  - VideoCallAdmin                 │  │
│  │   - listSessions│  │  - StreamingAdmin                 │  │
│  │   - onEvent()    │  │  - EchoTestAdmin                  │  │
│  └────────┬─────────┘  │  - RecordPlayAdmin                │  │
│           │            └────────────────────────────────────┘  │
│           ↓                                                     │
│  ┌─────────────────────────────────────────────────────────┐  │
│  │              ZmqTransport Layer                          │  │
│  │  - sendRequest() / handleMessages()                      │  │
│  │  - Transaction ID Management                             │  │
│  │  - Promise-based API                                     │  │
│  │  - Event Dispatching                                     │  │
│  └──────────────────────┬──────────────────────────────────┘  │
│                         │                                       │
│  ┌──────────────────────┴──────────────────────────────────┐  │
│  │              ReactPHP Event Loop                         │  │
│  │  - Asynchronous I/O                                      │  │
│  │  - Timer Management                                      │  │
│  │  - Promise Handling                                      │  │
│  └──────────────────────┬──────────────────────────────────┘  │
└─────────────────────────┼──────────────────────────────────────┘
                          │
                          ↓ ZeroMQ (tcp://)
┌─────────────────────────────────────────────────────────────────┐
│                      Janus Gateway                               │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐          │
│  │  Admin API   │  │   Plugins    │  │  Transports  │          │
│  │  - Sessions  │  │  - VideoRoom │  │  - ZeroMQ    │          │
│  │  - Handles   │  │  - VideoCall │  │  - HTTP      │          │
│  │  - Events    │  │  - Streaming │  │  - WebSocket │          │
│  └──────────────┘  └──────────────┘  └──────────────┘          │
└─────────────────────────────────────────────────────────────────┘
```

## Component Architecture

### 1. Core Layer

```
src/
├── Admin/
│   └── AdminClient.php          ← Main entry point
├── Transport/
│   └── ZmqTransport.php         ← ZeroMQ communication
├── Config/
│   └── ConfigManager.php        ← Configuration management
├── Logger/
│   └── Logger.php               ← Logging wrapper
└── Exception/
    ├── TerraException.php
    ├── ConnectionException.php
    ├── InvalidJsonException.php
    └── TimeoutException.php
```

### 2. Plugin Layer

```
src/Plugin/
├── VideoRoomAdmin.php           ← VideoRoom plugin admin
├── VideoCallAdmin.php           ← VideoCall plugin admin
├── StreamingAdmin.php           ← Streaming plugin admin
├── EchoTestAdmin.php            ← EchoTest plugin admin
└── RecordPlayAdmin.php          ← RecordPlay plugin admin
```

### 3. Supporting Components

```
config/
└── config.example.php           ← Configuration template

examples/
├── basic_usage.php              ← Basic connection example
├── videoroom_example.php        ← VideoRoom example
├── streaming_example.php        ← Streaming example
├── event_handler.php            ← Event handling example
└── cli_tool.php                 ← Interactive CLI tool

docs/
├── api-reference.md             ← Complete API documentation
├── janus-setup.md               ← Janus setup guide
└── event-handling.md            ← Event handling guide
```

## Data Flow

### Request Flow

```
Application
    │
    ↓ (call method)
AdminClient / Plugin Controller
    │
    ↓ (create payload)
ZmqTransport::sendRequest()
    │
    ↓ (add transaction ID)
Promise Created
    │
    ↓ (JSON encode)
ZMQ Socket
    │
    ↓ (tcp://)
Janus Gateway
```

### Response Flow

```
Janus Gateway
    │
    ↓ (tcp://)
ZMQ Socket
    │
    ↓ (on messages event)
ZmqTransport::handleMessages()
    │
    ├─→ (has transaction?) → handleResponse() → Resolve Promise
    │
    └─→ (no transaction?) → handleEvent() → Event Handlers
```

### Event Flow

```
Janus Gateway Event
    │
    ↓ (ZeroMQ)
ZmqTransport::handleEvent()
    │
    ↓ (dispatch)
Registered Event Handlers
    │
    ├─→ Handler 1 (logging)
    ├─→ Handler 2 (statistics)
    └─→ Handler N (custom logic)
```

## Communication Pattern

### Promise-based Asynchronous API

```php
// Request-Response Pattern
$client->getInfo()
    ->then(function ($response) {
        // Handle success
    })
    ->otherwise(function ($error) {
        // Handle error
    });

// Chained Operations
$client->connect()
    ->then(function () use ($client) {
        return $client->listSessions();
    })
    ->then(function ($sessions) {
        // Process sessions
    });
```

### Event-driven Pattern

```php
// Register event handler
$client->onEvent(function ($event) {
    // Process event
    if ($event['type'] === 'session') {
        // Handle session event
    }
});

// Run event loop
$client->run();
```

## Key Design Patterns

### 1. Factory Pattern
- **EventLoop**: Created via ReactPHP Factory
- **Logger**: Monolog factory for handlers

### 2. Strategy Pattern
- **Plugin Controllers**: Different strategies for each plugin
- **Transport**: Abstracted communication layer

### 3. Observer Pattern
- **Event Handling**: Multiple observers for Janus events
- **Promise Callbacks**: Observer pattern for async operations

### 4. Facade Pattern
- **AdminClient**: Simplified interface to complex subsystem
- **Plugin Controllers**: Facade for plugin-specific operations

### 5. Singleton Pattern (Optional)
- **ConfigManager**: Can be used as singleton
- **Logger**: Single instance per application

## Extensibility Points

### 1. Add New Plugin Controller

```php
namespace Terra\Plugin;

class CustomPluginAdmin {
    private $client;
    private $pluginId = 'janus.plugin.custom';
    
    public function __construct(AdminClient $client) {
        $this->client = $client;
    }
    
    public function customMethod() {
        return $this->sendPluginRequest([...]);
    }
    
    private function sendPluginRequest(array $request) {
        return $this->client->sendAdminRequest([
            'janus' => 'message_plugin',
            'plugin' => $this->pluginId,
            'request' => $request,
        ]);
    }
}
```

### 2. Add Custom Event Handler

```php
class CustomEventProcessor {
    public function __construct($client) {
        $client->onEvent([$this, 'process']);
    }
    
    public function process($event) {
        // Custom event processing
    }
}
```

### 3. Extend Configuration

```php
$config = new ConfigManager([
    'custom' => [
        'option1' => 'value1',
        'option2' => 'value2',
    ],
]);
```

## Performance Considerations

### 1. Asynchronous I/O
- Non-blocking operations via ReactPHP
- Multiple concurrent requests possible
- Event-driven architecture

### 2. Connection Pooling
- Persistent ZMQ connections (configurable)
- Single connection per client instance
- Efficient socket reuse

### 3. Memory Management
- Promise cleanup after resolution
- Event handler memory management
- Configurable log output

### 4. Timeout Management
- Per-request timeout configuration
- Automatic cleanup of timed-out requests
- Timer-based timeout enforcement

## Security Considerations

### 1. Authentication
- Admin secret support
- Plugin-specific secrets
- Configurable via environment variables

### 2. Data Validation
- JSON validation on all messages
- Type checking on inputs
- Exception handling for invalid data

### 3. Error Handling
- Comprehensive exception hierarchy
- Safe error propagation
- No sensitive data in logs (configurable)

## Testing Strategy

### Unit Tests
```
tests/Unit/
├── Config/
│   └── ConfigManagerTest.php
└── Exception/
    └── ExceptionTest.php
```

### Integration Tests (Requires Janus)
```
tests/Integration/
└── (Future tests for live Janus interaction)
```

## Deployment Architecture

```
┌─────────────────────────────────────────┐
│         Production Application          │
│  ┌───────────────────────────────────┐  │
│  │  Terra Admin Framework            │  │
│  │  (Composer Package)               │  │
│  └───────────────┬───────────────────┘  │
│                  │                       │
│                  ↓ tcp://janus:7889     │
│  ┌───────────────────────────────────┐  │
│  │     Janus Gateway (Docker)        │  │
│  │  - ZeroMQ Transport Enabled       │  │
│  │  - Admin API Enabled              │  │
│  └───────────────────────────────────┘  │
└─────────────────────────────────────────┘
```

## Summary

Terra provides a well-architected, extensible framework for Janus Gateway administration with:

- **Clear separation of concerns** between layers
- **Asynchronous architecture** for scalability
- **Plugin system** for extensibility
- **Promise-based API** for clean async code
- **Event-driven design** for real-time updates
- **Comprehensive error handling** for robustness
- **Extensive documentation** for ease of use
