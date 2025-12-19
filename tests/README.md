# Terra Tests

This directory contains tests for the Terra Janus Admin Framework.

## Test Structure

- `Unit/` - Unit tests for individual classes and components
- `Integration/` - Integration tests that require a running Janus Gateway instance

## Running Tests

### Prerequisites

For unit tests:
```bash
composer install
```

For integration tests, you also need:
- Janus Gateway with ZeroMQ transport enabled
- ZMQ extension for PHP

### Running Unit Tests

```bash
vendor/bin/phpunit --testsuite Unit
```

### Running Integration Tests

**Note:** Integration tests require a running Janus Gateway instance.

1. Start Janus Gateway with ZeroMQ transport enabled
2. Configure test environment:
   ```bash
   export JANUS_ADMIN_ADDRESS="tcp://localhost:7889"
   export JANUS_ADMIN_SECRET="janusoverlord"
   ```
3. Run tests:
   ```bash
   vendor/bin/phpunit --testsuite Integration
   ```

### Running All Tests

```bash
vendor/bin/phpunit
```

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
- Verify end-to-end functionality
- Clean up resources after testing

Example:
```php
<?php

namespace Terra\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Terra\Admin\AdminClient;

class AdminClientTest extends TestCase
{
    private $client;

    protected function setUp(): void
    {
        $this->client = new AdminClient([
            'janus' => [
                'admin_address' => getenv('JANUS_ADMIN_ADDRESS'),
                'admin_secret' => getenv('JANUS_ADMIN_SECRET'),
            ],
        ]);
    }

    public function testConnection()
    {
        $connected = false;
        $this->client->connect()->then(function () use (&$connected) {
            $connected = true;
        });
        $this->client->run();
        $this->assertTrue($connected);
    }
}
```

## Coverage

Generate code coverage report:

```bash
vendor/bin/phpunit --coverage-html coverage
```

View the report by opening `coverage/index.html` in a browser.

## Continuous Integration

Tests are automatically run in CI/CD pipelines. Ensure all tests pass before submitting a pull request.
