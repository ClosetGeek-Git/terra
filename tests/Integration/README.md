# Terra Admin Test Suite

This directory contains comprehensive integration tests for Terra's Janus Admin Framework. The test suite validates all high-level admin features across multiple transport types (HTTP, UnixSocket, and ZeroMQ).

## Test Coverage

### Admin Client Tests (`AdminClientTest.php`)
Tests core administrative operations:
- **Server Information**: Get Janus server info
- **Session Management**: List sessions, list handles, get handle info
- **Log Level Management**: Get and set Janus log levels
- **Event Handling**: Register and receive events
- **Plugin Management**: Register and retrieve plugin controllers
- **Configuration**: Access and validate configuration settings
- **Error Handling**: Test invalid admin secrets and error responses

### VideoRoom Plugin Tests (`VideoRoomAdminTest.php`)
Tests VideoRoom plugin administration:
- **Room Management**: List, create, edit, destroy rooms
- **Room Information**: Get detailed room info, check room existence
- **Participant Management**: List participants, kick participants, moderate participants
- **Recording**: Enable/disable room recording
- **Forwarders**: List and manage RTP forwarders
- **Configuration**: Edit room settings dynamically

### Streaming Plugin Tests (`StreamingAdminTest.php`)
Tests Streaming plugin administration:
- **Mountpoint Management**: List, create, destroy mountpoints
- **Mountpoint Information**: Get detailed mountpoint info
- **Mountpoint Control**: Enable/disable mountpoints
- **Recording**: Start/stop recording on mountpoints
- **Configuration**: Manage streaming configurations

### Plugin Admin Tests (`PluginAdminTest.php`)
Tests additional plugin administration:
- **VideoCall Plugin**: List sessions, get user info, hangup calls
- **EchoTest Plugin**: Get statistics, list active sessions
- **RecordPlay Plugin**: List recordings, get recording info, delete recordings
- **Plugin Registration**: Validate plugin registration system

## Transport Coverage

All tests are designed to run against three transport types:

1. **HTTP/REST API** (Port 7088)
   - Default and recommended transport
   - Uses long-polling for events
   - Easy to debug with curl

2. **UnixSocket** (/var/run/janus/janus-admin.sock)
   - Low latency local communication
   - Uses SOCK_SEQPACKET
   - Not supported on Windows

3. **ZeroMQ** (tcp://localhost:7889)
   - High performance messaging
   - Requires PHP ZMQ extension
   - Optional transport

## Prerequisites

### Required Software
```bash
# PHP 7.4 or higher
php --version

# Composer
composer --version

# Janus Gateway (with admin API enabled)
janus --version
```

### Install Janus Gateway
Use the provided setup script:
```bash
sudo ./setup-janus.sh
```

This will:
- Install Janus Gateway
- Configure all transport types
- Enable admin API
- Set up proper permissions
- Start Janus service

### Install PHP Dependencies
```bash
cd /path/to/terra
composer install
```

### Optional: Install ZMQ Extension (for ZeroMQ tests)
```bash
# Ubuntu/Debian
sudo apt-get install libzmq3-dev php-zmq

# macOS
brew install zmq
pecl install zmq-beta

# Verify installation
php -m | grep zmq
```

## Running the Tests

### Quick Start (All Tests, All Transports)
```bash
# Run complete test suite with automatic transport detection
./run-integration-tests.sh
```

This script will:
- Check all prerequisites
- Verify transport availability
- Run tests for all available transports
- Provide detailed troubleshooting for failures

### Run Specific Test Suites

#### All Integration Tests
```bash
vendor/bin/phpunit --testsuite Integration
```

#### Admin Client Tests Only
```bash
vendor/bin/phpunit tests/Integration/AdminClientTest.php
```

#### VideoRoom Plugin Tests Only
```bash
vendor/bin/phpunit tests/Integration/VideoRoomAdminTest.php
```

#### Streaming Plugin Tests Only
```bash
vendor/bin/phpunit tests/Integration/StreamingAdminTest.php
```

#### Other Plugin Tests
```bash
vendor/bin/phpunit tests/Integration/PluginAdminTest.php
```

#### Transport-Specific Tests
```bash
# HTTP transport
vendor/bin/phpunit --testsuite Integration --filter HttpTransportTest

# UnixSocket transport
vendor/bin/phpunit --testsuite Integration --filter UnixSocketTransportTest

# ZeroMQ transport
vendor/bin/phpunit --testsuite Integration --filter ZmqTransportTest
```

### Run Tests with Verbose Output
```bash
vendor/bin/phpunit --testsuite Integration --verbose
```

### Run Tests with Coverage
```bash
vendor/bin/phpunit --testsuite Integration --coverage-html coverage/
```

## Environment Configuration

### Environment Variables
Configure test environment using environment variables:

```bash
# HTTP Transport
export JANUS_HTTP_ADDRESS="http://localhost:7088/admin"
export JANUS_HTTP_EVENT_ADDRESS="http://localhost:7088/admin"

# UnixSocket Transport
export JANUS_UNIX_SOCKET="/var/run/janus/janus-admin.sock"

# ZeroMQ Transport
export JANUS_ADMIN_ADDRESS="tcp://localhost:7889"

# Admin Secret (required for all transports)
export JANUS_ADMIN_SECRET="janusoverlord"
```

### Custom Configuration
To use custom Janus endpoints or secrets, set the appropriate environment variables before running tests.

## Validating Results

### Successful Test Run
```
✓ PASS      Get Server Info
✓ PASS      List Sessions
✓ PASS      Get Log Level
✓ PASS      Set Log Level
...

Total: 45 tests | Passed: 45 | Failed: 0
```

### Test Failure Example
```
✗ FAIL      Get Server Info
           Error: Connection refused

✗ FAIL      List Sessions
           Error: Timeout waiting for response
```

## Troubleshooting

### Common Issues

#### 1. Janus Not Running
**Symptoms**: Connection errors, "Connection refused"

**Solutions**:
```bash
# Check status
sudo systemctl status janus

# Start Janus
sudo systemctl start janus

# View logs
sudo journalctl -u janus -f
```

#### 2. HTTP Transport Not Available
**Symptoms**: HTTP tests fail, port 7088 not responding

**Solutions**:
```bash
# Check if port is listening
netstat -tlnp | grep 7088

# Verify HTTP transport config
cat /etc/janus/janus.transport.http.jcfg

# Should show:
# admin: {
#     admin_http = true
#     admin_port = 7088
# }

# Restart Janus
sudo systemctl restart janus
```

#### 3. UnixSocket Not Available
**Symptoms**: UnixSocket tests fail, socket file not found

**Solutions**:
```bash
# Check if socket exists
ls -la /var/run/janus/janus-admin.sock

# Check permissions
stat /var/run/janus/janus-admin.sock

# Fix permissions
sudo chmod 666 /var/run/janus/janus-admin.sock

# Verify UnixSocket config
cat /etc/janus/janus.transport.pfunix.jcfg

# Should show:
# admin: {
#     admin_enabled = true
#     admin_path = "/var/run/janus/janus-admin.sock"
# }
```

#### 4. ZeroMQ Transport Not Available
**Symptoms**: ZMQ tests skipped or fail

**Solutions**:
```bash
# Check ZMQ extension
php -m | grep zmq

# Install ZMQ extension
sudo apt-get install libzmq3-dev php-zmq

# Check if ZMQ port is listening
netstat -tlnp | grep 7889

# Verify ZMQ transport
ls -la /usr/lib/janus/transports/libjanus_zmq.so

# Install if missing
sudo apt-get install janus-zeromq

# Check ZMQ config
cat /etc/janus/janus.transport.zmq.jcfg
```

#### 5. Invalid Admin Secret
**Symptoms**: "Unauthorized" or "Invalid admin secret" errors

**Solutions**:
```bash
# Check admin secret in Janus config
cat /etc/janus/janus.jcfg | grep admin_secret

# Should match the secret used in tests (default: janusoverlord)

# Update test environment variable if needed
export JANUS_ADMIN_SECRET="your_actual_secret"
```

#### 6. Plugin Not Available
**Symptoms**: Plugin tests fail, "Plugin not found" errors

**Solutions**:
```bash
# List installed plugins
ls -la /usr/lib/janus/plugins/

# Install missing plugins
sudo apt-get install janus-plugins-extra

# Check plugin configs
ls -la /etc/janus/janus.plugin.*.jcfg

# Restart Janus after installing plugins
sudo systemctl restart janus
```

### Debug Mode

Run tests with PHP debug output:
```bash
vendor/bin/phpunit --testsuite Integration --debug
```

View Janus logs in real-time:
```bash
sudo journalctl -u janus -f
```

Enable Terra debug logging by setting log level to 'debug' in test configuration.

## Test Architecture

### Base Test Class (`BaseIntegrationTest.php`)
- Provides common functionality for all integration tests
- Handles client creation and connection
- Records test results for reporting
- Provides troubleshooting guidance
- Manages teardown and cleanup

### Transport Configuration
Each test class implements `getTransportConfig()` to return the appropriate configuration for the current transport type. Tests can be run against any transport by changing the configuration.

### Modular Design
- Tests are organized by functionality (Admin, VideoRoom, Streaming, etc.)
- Each test is independent and can run in isolation
- Automatic cleanup after each test
- Reusable test patterns across different plugins

## Continuous Integration

### CI Pipeline Integration
```yaml
# Example GitHub Actions configuration
- name: Install Janus
  run: sudo ./setup-janus.sh

- name: Run Tests
  run: ./run-integration-tests.sh
```

### Docker Support
```dockerfile
# Example Dockerfile for testing
FROM ubuntu:20.04
RUN apt-get update && apt-get install -y janus
# ... configure Janus
CMD ["./run-integration-tests.sh"]
```

## Performance Considerations

- Tests use short timeouts (30 seconds) to fail fast
- Async operations use ReactPHP event loop for efficiency
- Cleanup operations prevent resource leaks
- Tests can run in parallel across different transports

## Contributing

When adding new tests:
1. Extend `BaseIntegrationTest` for integration tests
2. Follow existing test patterns and naming conventions
3. Add proper cleanup in `tearDown()`
4. Document new test cases in this README
5. Ensure tests work across all three transports
6. Add troubleshooting guidance for common failures

## Support

For issues or questions:
- GitHub Issues: https://github.com/ClosetGeek-Git/terra/issues
- Documentation: See main README.md and ARCHITECTURE.md
- Janus Documentation: https://janus.conf.meetecho.com/docs/

## License

MIT License - See LICENSE file in repository root
