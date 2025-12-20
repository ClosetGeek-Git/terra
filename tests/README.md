# Terra Tests

This directory contains the comprehensive test suite for the Terra Janus Admin Framework.

## Test Structure

```
tests/
├── Unit/                          # Unit tests (no Janus required)
│   ├── Config/
│   │   └── ConfigManagerTest.php
│   └── Exception/
│       └── ExceptionTest.php
└── Integration/                   # Integration tests (requires Janus)
    ├── IntegrationTestBase.php    # Base class for integration tests
    ├── AdminClientTest.php        # Core admin functionality
    ├── VideoRoomPluginTest.php    # VideoRoom plugin tests
    ├── StreamingPluginTest.php    # Streaming plugin tests
    ├── EchoTestPluginTest.php     # EchoTest plugin tests
    ├── VideoCallPluginTest.php    # VideoCall plugin tests
    └── RecordPlayPluginTest.php   # RecordPlay plugin tests
```

## Running Tests

### Prerequisites

**For unit tests:**
```bash
composer install
```

**For integration tests, you also need:**
- Janus Gateway with required transports enabled (ZMQ, Unix Socket, HTTP)
- PHP ZMQ extension
- Running Janus instance

For detailed setup instructions, see **[TESTING.MD](../TESTING.MD)** in the root directory.

### Quick Start

```bash
# Run all tests
vendor/bin/phpunit

# Run only unit tests
vendor/bin/phpunit --testsuite Unit

# Run only integration tests
vendor/bin/phpunit --testsuite Integration

# Run tests for specific transport
JANUS_TRANSPORT=zmq vendor/bin/phpunit --testsuite Integration
JANUS_TRANSPORT=unix vendor/bin/phpunit --testsuite Integration
JANUS_TRANSPORT=http vendor/bin/phpunit --testsuite Integration

# Run with coverage
vendor/bin/phpunit --coverage-html coverage

# Use the test runner script
./run-tests.sh
```

## Test Configuration

### Environment Variables

Configure test environment using environment variables:

**For ZeroMQ Transport:**
```bash
export JANUS_TRANSPORT=zmq
export JANUS_ADMIN_ADDRESS="tcp://localhost:7889"
export JANUS_ADMIN_SECRET="janusoverlord"
```

**For Unix Socket Transport:**
```bash
export JANUS_TRANSPORT=unix
export JANUS_ADMIN_SOCKET="/tmp/janus_admin.sock"
export JANUS_ADMIN_SECRET="janusoverlord"
```

**For HTTP/REST Transport:**
```bash
export JANUS_TRANSPORT=http
export JANUS_ADMIN_HOST="localhost"
export JANUS_ADMIN_PORT=7088
export JANUS_ADMIN_BASE_PATH="/admin"
export JANUS_ADMIN_SECRET="janusoverlord"
```

### Configuration Files

- **phpunit.xml.dist**: Default PHPUnit configuration (do not modify)
- **phpunit.xml**: Local PHPUnit configuration (git-ignored, copy from .dist)
- **.env.testing.example**: Example environment configuration

## Writing Tests

### Unit Tests

Unit tests should:
- Test individual classes in isolation
- Not require external dependencies (Janus Gateway, network, etc.)
- Use mocks for dependencies
- Be fast and deterministic

Example:
```php
<?php

namespace Terra\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Terra\Config\ConfigManager;

class ConfigManagerTest extends TestCase
{
    public function testDefaultConfiguration()
    {
        $config = new ConfigManager();
        $this->assertEquals('tcp://localhost:7889', $config->get('janus.admin_address'));
    }
}
```

### Integration Tests

Integration tests should:
- Test the framework with a real Janus Gateway instance
- Verify end-to-end functionality across all transports
- Clean up resources after testing
- Extend `IntegrationTestBase` for common functionality

Example:
```php
<?php

namespace Terra\Tests\Integration;

class MyPluginTest extends IntegrationTestBase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfJanusNotAvailable();
    }

    public function testSomeFeature(): void
    {
        $result = $this->executePromiseTest(function () {
            return $this->client->someMethod();
        });

        $this->assertSuccessResponse($result);
    }
}
```

## Test Coverage

The test suite provides comprehensive coverage of:

### Core Admin Features
- Connection management (connect, disconnect)
- Server information retrieval
- Session management (list, info)
- Handle management (list, info)
- Log level management (get, set)
- Packet capture (start, stop)
- Error handling and edge cases

### VideoRoom Plugin
- Room listing
- Room creation/destruction
- Room configuration and editing
- Participant management
- Recording control
- Codec configuration
- Error scenarios

### Streaming Plugin
- Mountpoint listing
- Mountpoint creation/destruction
- Mountpoint configuration
- Enable/disable mountpoints
- Recording control
- Different streaming types (RTP, etc.)

### EchoTest Plugin
- Statistics retrieval
- Session listing

### VideoCall Plugin
- Session listing
- User information
- Call hangup

### RecordPlay Plugin
- Recording listing
- Recording information
- Recording deletion

### Transport Coverage
All features are tested across:
- **ZeroMQ**: Asynchronous messaging transport
- **Unix Socket**: Local IPC transport
- **HTTP/REST**: RESTful API transport

## Continuous Integration

The test suite is designed for CI/CD integration. See examples in **[TESTING.MD](../TESTING.MD)**:
- GitHub Actions
- GitLab CI
- Docker Compose

## Troubleshooting

### Tests Skipped

If you see "Janus Gateway is not available", ensure:
1. Janus is running: `ps aux | grep janus`
2. Required transport is enabled and configured
3. Ports/sockets are accessible

### Connection Failures

Check:
- Janus configuration files
- Firewall settings
- Admin secret matches
- Socket permissions (for Unix socket)

### Plugin Not Available

Verify:
- Plugin is installed in Janus
- Plugin is enabled in configuration
- Plugin configuration file exists

For detailed troubleshooting, see **[TESTING.MD](../TESTING.MD)**.

## Coverage Reports

Generate coverage reports:

```bash
# HTML report
vendor/bin/phpunit --coverage-html coverage
open coverage/index.html

# Text report
vendor/bin/phpunit --coverage-text

# Clover XML (for CI)
vendor/bin/phpunit --coverage-clover coverage.xml
```

## Contributing

When adding new tests:
1. Follow existing test structure and conventions
2. Add both unit and integration tests where applicable
3. Ensure tests pass for all transport types
4. Update documentation as needed
5. Run full test suite before submitting PR

## Resources

- **[TESTING.MD](../TESTING.MD)**: Complete testing guide
- **[Janus Admin API](https://janus.conf.meetecho.com/docs/admin.html)**: Official documentation
- **[PHPUnit Documentation](https://phpunit.de/)**: Testing framework docs

## Support

For issues and questions:
- **GitHub Issues**: https://github.com/ClosetGeek-Git/terra/issues
- **Documentation**: https://github.com/ClosetGeek-Git/terra
