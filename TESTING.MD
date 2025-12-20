# Testing Guide for Terra - Janus Admin Framework

This guide provides comprehensive instructions for setting up and running the Terra test suite against a live Janus Gateway instance. The test suite covers all admin features across multiple transport types: ZeroMQ (ZMQ), Unix Sockets, and RESTful HTTP API.

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Janus Gateway Installation](#janus-gateway-installation)
3. [Janus Configuration](#janus-configuration)
4. [Test Environment Setup](#test-environment-setup)
5. [Running Tests](#running-tests)
6. [Test Coverage](#test-coverage)
7. [Troubleshooting](#troubleshooting)
8. [CI/CD Integration](#cicd-integration)

---

## Prerequisites

### System Requirements

- **Operating System**: Linux (Ubuntu 20.04+ or Debian 10+ recommended)
- **PHP**: Version 7.4 or higher
- **Composer**: Latest version
- **Root Access**: Required for system-level installations

### Required PHP Extensions

```bash
# Install PHP extensions
sudo apt-get update
sudo apt-get install -y \
    php-cli \
    php-zmq \
    php-curl \
    php-json \
    php-xml \
    php-mbstring
```

### Development Tools

```bash
# Install build tools
sudo apt-get install -y \
    build-essential \
    pkg-config \
    git \
    wget \
    curl
```

---

## Janus Gateway Installation

### Option 1: Install from Source

This is the recommended method for development and testing as it provides full control over configuration.

#### Step 1: Install Dependencies

```bash
# Install Janus dependencies
sudo apt-get install -y \
    libmicrohttpd-dev \
    libjansson-dev \
    libssl-dev \
    libsrtp2-dev \
    libsofia-sip-ua-dev \
    libglib2.0-dev \
    libopus-dev \
    libogg-dev \
    libcurl4-openssl-dev \
    liblua5.3-dev \
    libconfig-dev \
    pkg-config \
    gengetopt \
    libtool \
    automake \
    cmake
```

#### Step 2: Install libnice

```bash
cd /tmp
git clone https://gitlab.freedesktop.org/libnice/libnice.git
cd libnice
meson setup build
ninja -C build
sudo ninja -C build install
sudo ldconfig
```

#### Step 3: Install libsrtp

```bash
cd /tmp
wget https://github.com/cisco/libsrtp/archive/v2.4.2.tar.gz
tar xfv v2.4.2.tar.gz
cd libsrtp-2.4.2
./configure --prefix=/usr --enable-openssl
make shared_library
sudo make install
sudo ldconfig
```

#### Step 4: Install ZeroMQ

```bash
# Install ZeroMQ library
sudo apt-get install -y libzmq3-dev libzmq5

# Or build from source for latest version
cd /tmp
git clone https://github.com/zeromq/libzmq.git
cd libzmq
./autogen.sh
./configure
make
sudo make install
sudo ldconfig
```

#### Step 5: Clone and Build Janus Gateway

```bash
cd /tmp
git clone https://github.com/meetecho/janus-gateway.git
cd janus-gateway

# Run autogen
sh autogen.sh

# Configure with all required transports
./configure \
    --prefix=/opt/janus \
    --enable-post-processing \
    --enable-plugin-videoroom \
    --enable-plugin-streaming \
    --enable-plugin-echotest \
    --enable-plugin-videocall \
    --enable-plugin-recordplay \
    --enable-rest \
    --enable-websockets \
    --enable-rabbitmq=no \
    --enable-mqtt=no \
    --enable-nanomsg=no

# Build and install
make
sudo make install
sudo make configs
```

### Option 2: Using Docker

For quick testing, you can use Docker:

```bash
# Pull Janus Gateway image
docker pull meetecho/janus-gateway:latest

# Run with custom configuration (see configuration section below)
docker run -d \
    --name janus-test \
    -p 7088:7088 \
    -p 7889:7889 \
    -v /path/to/configs:/opt/janus/etc/janus \
    meetecho/janus-gateway:latest
```

---

## Janus Configuration

Janus must be configured to support all three transport types for comprehensive testing.

### 1. Configure ZeroMQ Transport

Edit `/opt/janus/etc/janus/janus.transport.zmq.jcfg`:

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

### 2. Configure Unix Socket Transport

Edit `/opt/janus/etc/janus/janus.transport.pfunix.jcfg`:

```
general: {
    enabled = true
    events = true
    json = "compact"
}

admin: {
    admin_enabled = true
    admin_base_path = "/admin"
    path = "/tmp/janus_admin.sock"
}
```

**Important**: Ensure the socket path is writable:

```bash
sudo chmod 777 /tmp
```

### 3. Configure HTTP/REST Transport

Edit `/opt/janus/etc/janus/janus.transport.http.jcfg`:

```
general: {
    json = "indented"
    base_path = "/janus"
    http = true
    port = 8088
    admin_base_path = "/admin"
    admin_http = true
    admin_port = 7088
}
```

### 4. Set Admin Secret

Edit `/opt/janus/etc/janus/janus.jcfg`:

```
general: {
    admin_secret = "janusoverlord"
    api_secret = ""
    # ... other settings ...
}
```

### 5. Enable Required Plugins

Ensure the following plugins are enabled in their respective configuration files:

#### VideoRoom Plugin

Edit `/opt/janus/etc/janus/janus.plugin.videoroom.jcfg`:

```
general: {
    #admin_key = "your_admin_key"  # Optional
    events = true
}
```

#### Streaming Plugin

Edit `/opt/janus/etc/janus/janus.plugin.streaming.jcfg`:

```
general: {
    #admin_key = "your_admin_key"  # Optional
}
```

#### EchoTest Plugin

Edit `/opt/janus/etc/janus/janus.plugin.echotest.jcfg`:

```
general: {
    events = true
}
```

---

## Test Environment Setup

### Step 1: Clone Terra Repository

```bash
git clone https://github.com/ClosetGeek-Git/terra.git
cd terra
```

### Step 2: Install Dependencies

```bash
composer install
```

### Step 3: Start Janus Gateway

```bash
# Start Janus in foreground (for debugging)
/opt/janus/bin/janus

# Or start as background process
/opt/janus/bin/janus -b

# Or with systemd
sudo systemctl start janus
```

### Step 4: Verify Janus is Running

```bash
# Check if Janus is running
ps aux | grep janus

# Check if ports are listening
sudo netstat -tlnp | grep -E '7088|7889'

# Check if Unix socket exists
ls -la /tmp/janus_admin.sock

# Test HTTP endpoint
curl http://localhost:7088/janus/info

# Test admin HTTP endpoint
curl http://localhost:7088/admin/info
```

---

## Running Tests

### Environment Variables

Configure test environment variables based on your transport setup:

#### For ZeroMQ Transport:

```bash
export JANUS_TRANSPORT=zmq
export JANUS_ADMIN_ADDRESS="tcp://localhost:7889"
export JANUS_ADMIN_SECRET="janusoverlord"
```

#### For Unix Socket Transport:

```bash
export JANUS_TRANSPORT=unix
export JANUS_ADMIN_SOCKET="/tmp/janus_admin.sock"
export JANUS_ADMIN_SECRET="janusoverlord"
```

#### For HTTP/REST Transport:

```bash
export JANUS_TRANSPORT=http
export JANUS_ADMIN_HOST="localhost"
export JANUS_ADMIN_PORT=7088
export JANUS_ADMIN_BASE_PATH="/admin"
export JANUS_ADMIN_SECRET="janusoverlord"
export JANUS_ADMIN_SECURE=false
```

### Running All Tests

```bash
# Run all tests
vendor/bin/phpunit

# Run with verbose output
vendor/bin/phpunit --verbose
```

### Running Specific Test Suites

```bash
# Run only unit tests
vendor/bin/phpunit --testsuite Unit

# Run only integration tests
vendor/bin/phpunit --testsuite Integration

# Run specific test class
vendor/bin/phpunit tests/Integration/AdminClientTest.php

# Run specific test method
vendor/bin/phpunit --filter testGetInfo tests/Integration/AdminClientTest.php
```

### Running Tests for Specific Transport

```bash
# Test ZMQ transport
JANUS_TRANSPORT=zmq vendor/bin/phpunit --testsuite Integration

# Test Unix Socket transport
JANUS_TRANSPORT=unix vendor/bin/phpunit --testsuite Integration

# Test HTTP transport
JANUS_TRANSPORT=http vendor/bin/phpunit --testsuite Integration
```

### Running Tests with Coverage

```bash
# Generate HTML coverage report
vendor/bin/phpunit --coverage-html coverage

# Generate text coverage report
vendor/bin/phpunit --coverage-text

# Generate Clover XML coverage report (for CI)
vendor/bin/phpunit --coverage-clover coverage.xml
```

### Automated Test Script

Create a script to test all transports automatically:

```bash
#!/bin/bash
# test-all-transports.sh

echo "Testing ZMQ Transport..."
JANUS_TRANSPORT=zmq vendor/bin/phpunit --testsuite Integration

echo "Testing Unix Socket Transport..."
JANUS_TRANSPORT=unix vendor/bin/phpunit --testsuite Integration

echo "Testing HTTP Transport..."
JANUS_TRANSPORT=http vendor/bin/phpunit --testsuite Integration

echo "All transports tested!"
```

Make it executable and run:

```bash
chmod +x test-all-transports.sh
./test-all-transports.sh
```

---

## Test Coverage

### Test Suite Structure

The test suite is organized as follows:

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
    └── EchoTestPluginTest.php     # EchoTest plugin tests
```

### Features Tested

#### AdminClient Core Features
- Connection establishment and disconnection
- Server information retrieval (`getInfo`)
- Session management (`listSessions`, `listHandles`, `handleInfo`)
- Log level management (`getLogLevel`, `setLogLevel`)
- Error handling and timeout scenarios
- Authentication and authorization

#### VideoRoom Plugin Features
- Room listing (`listRooms`)
- Room creation with various configurations (`createRoom`)
- Room destruction (`destroyRoom`)
- Room information retrieval (`getRoomInfo`)
- Participant listing (`listParticipants`)
- Room editing (`editRoom`)
- Codec configuration (opus, vp8, vp9, h264)
- Edge cases (duplicate rooms, invalid parameters, non-existent rooms)

#### Streaming Plugin Features
- Mountpoint listing (`listMountpoints`)
- Mountpoint creation with different types (`createMountpoint`)
- Mountpoint destruction (`destroyMountpoint`)
- Mountpoint information retrieval (`getMountpointInfo`)
- Mountpoint toggling (`toggleMountpoint`)
- Audio/video configuration
- Edge cases (duplicate mountpoints, invalid configurations)

#### EchoTest Plugin Features
- Statistics retrieval (`getStats`)
- Session listing (`listSessions`)

### Expected Coverage Metrics

- **Line Coverage**: > 80%
- **Function Coverage**: > 90%
- **Class Coverage**: > 95%

---

## Troubleshooting

### Common Issues and Solutions

#### 1. Connection Refused

**Symptoms:**
```
Failed to connect to Janus Gateway: Connection refused
```

**Solutions:**
- Verify Janus is running: `ps aux | grep janus`
- Check if ports are open: `sudo netstat -tlnp | grep 7889`
- Verify firewall settings: `sudo ufw status`
- Check Janus logs: `tail -f /var/log/janus.log`

#### 2. Authentication Failed

**Symptoms:**
```
Unauthorized access
```

**Solutions:**
- Verify `admin_secret` in `janus.jcfg` matches your environment variable
- Check for typos in the secret
- Ensure the secret is set in both Janus config and test environment

#### 3. Unix Socket Not Found

**Symptoms:**
```
Failed to connect to Unix socket: No such file or directory
```

**Solutions:**
- Verify Unix socket transport is enabled in Janus
- Check if socket file exists: `ls -la /tmp/janus_admin.sock`
- Verify socket permissions: `chmod 777 /tmp/janus_admin.sock`
- Restart Janus after configuration changes

#### 4. HTTP Endpoint Not Responding

**Symptoms:**
```
Failed to connect to Janus HTTP API
```

**Solutions:**
- Test HTTP endpoint manually: `curl http://localhost:7088/janus/info`
- Verify HTTP transport is enabled in `janus.transport.http.jcfg`
- Check `admin_http` and `admin_port` settings
- Verify no other service is using port 7088

#### 5. Plugin Not Found

**Symptoms:**
```
Plugin not available or not enabled
```

**Solutions:**
- Verify plugin is installed: `ls /opt/janus/lib/janus/plugins/`
- Check plugin configuration files
- Ensure plugin is enabled in Janus configuration
- Rebuild Janus with plugin support if necessary

#### 6. Test Timeout

**Symptoms:**
```
Test timeout after 30 seconds
```

**Solutions:**
- Increase timeout in test environment variables
- Check Janus performance and resource usage
- Verify network connectivity
- Check Janus logs for errors or warnings

### Debugging Tips

#### Enable Debug Logging

```bash
# Set environment variable for debug output
export LOG_LEVEL=debug

# Run tests with debug output
vendor/bin/phpunit --verbose --debug
```

#### Check Janus Logs

```bash
# View Janus logs in real-time
tail -f /var/log/janus.log

# Or if running in foreground, check console output
/opt/janus/bin/janus -o stdout
```

#### Test Individual Transports

```bash
# Test each transport individually to isolate issues
JANUS_TRANSPORT=zmq vendor/bin/phpunit tests/Integration/AdminClientTest.php --filter testGetInfo
```

#### Use PHPUnit Filters

```bash
# Run only specific test methods
vendor/bin/phpunit --filter testConnection

# Run tests matching a pattern
vendor/bin/phpunit --filter "test.*Room"
```

---

## CI/CD Integration

### GitHub Actions Example

Create `.github/workflows/tests.yml`:

```yaml
name: Tests

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main, develop ]

jobs:
  test:
    runs-on: ubuntu-latest
    
    services:
      janus:
        image: meetecho/janus-gateway:latest
        ports:
          - 7088:7088
          - 7889:7889
        options: --health-cmd="curl -f http://localhost:7088/janus/info || exit 1" --health-interval=10s --health-timeout=5s --health-retries=3
    
    steps:
      - uses: actions/checkout@v2
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '7.4'
          extensions: zmq, curl, json
          
      - name: Install dependencies
        run: composer install --prefer-dist --no-progress
        
      - name: Run unit tests
        run: vendor/bin/phpunit --testsuite Unit
        
      - name: Run integration tests (ZMQ)
        env:
          JANUS_TRANSPORT: zmq
          JANUS_ADMIN_ADDRESS: tcp://localhost:7889
          JANUS_ADMIN_SECRET: janusoverlord
        run: vendor/bin/phpunit --testsuite Integration
        
      - name: Run integration tests (HTTP)
        env:
          JANUS_TRANSPORT: http
          JANUS_ADMIN_HOST: localhost
          JANUS_ADMIN_PORT: 7088
          JANUS_ADMIN_SECRET: janusoverlord
        run: vendor/bin/phpunit --testsuite Integration
        
      - name: Generate coverage report
        run: vendor/bin/phpunit --coverage-clover coverage.xml
        
      - name: Upload coverage to Codecov
        uses: codecov/codecov-action@v2
        with:
          files: ./coverage.xml
```

### GitLab CI Example

Create `.gitlab-ci.yml`:

```yaml
image: php:7.4-cli

stages:
  - test

before_script:
  - apt-get update
  - apt-get install -y libzmq3-dev git
  - pecl install zmq-beta
  - docker-php-ext-enable zmq
  - composer install

test:unit:
  stage: test
  script:
    - vendor/bin/phpunit --testsuite Unit

test:integration:
  stage: test
  services:
    - name: meetecho/janus-gateway:latest
      alias: janus
  variables:
    JANUS_TRANSPORT: "http"
    JANUS_ADMIN_HOST: "janus"
    JANUS_ADMIN_PORT: "7088"
    JANUS_ADMIN_SECRET: "janusoverlord"
  script:
    - vendor/bin/phpunit --testsuite Integration
  artifacts:
    reports:
      junit: phpunit-report.xml
      cobertura: coverage.xml
```

### Docker Compose for Local Testing

Create `docker-compose.test.yml`:

```yaml
version: '3.8'

services:
  janus:
    image: meetecho/janus-gateway:latest
    ports:
      - "7088:7088"
      - "7889:7889"
    volumes:
      - ./janus-configs:/opt/janus/etc/janus
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:7088/janus/info"]
      interval: 10s
      timeout: 5s
      retries: 3
  
  terra-tests:
    build:
      context: .
      dockerfile: Dockerfile.test
    depends_on:
      janus:
        condition: service_healthy
    environment:
      - JANUS_TRANSPORT=http
      - JANUS_ADMIN_HOST=janus
      - JANUS_ADMIN_PORT=7088
      - JANUS_ADMIN_SECRET=janusoverlord
    command: vendor/bin/phpunit
```

Run with:

```bash
docker-compose -f docker-compose.test.yml up --abort-on-container-exit
```

---

## Additional Resources

- [Janus Gateway Documentation](https://janus.conf.meetecho.com/docs/)
- [Janus Admin API Reference](https://janus.conf.meetecho.com/docs/admin.html)
- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Terra Repository](https://github.com/ClosetGeek-Git/terra)

## Support

For issues and questions:
- **GitHub Issues**: https://github.com/ClosetGeek-Git/terra/issues
- **Documentation**: https://github.com/ClosetGeek-Git/terra

---

## License

This testing guide is part of the Terra project and is licensed under the MIT License.
