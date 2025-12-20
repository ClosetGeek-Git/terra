# Test Validation Guide

This document explains how to validate that the comprehensive admin test suite is properly installed and ready to run.

## Quick Validation Checklist

### 1. Verify Files Exist

Check that all test files have been created:

```bash
# Test files
ls -l tests/Integration/AdminClientTest.php
ls -l tests/Integration/VideoRoomAdminTest.php
ls -l tests/Integration/StreamingAdminTest.php
ls -l tests/Integration/PluginAdminTest.php

# Documentation
ls -l tests/Integration/README.md
ls -l IMPLEMENTATION_SUMMARY.md

# Test runners
ls -l run-admin-tests.sh
ls -l run-integration-tests.sh
```

All files should exist and show appropriate sizes.

### 2. Verify PHP Syntax

Check that all test files have valid PHP syntax:

```bash
php -l tests/Integration/AdminClientTest.php
php -l tests/Integration/VideoRoomAdminTest.php
php -l tests/Integration/StreamingAdminTest.php
php -l tests/Integration/PluginAdminTest.php
```

Expected output for each: `No syntax errors detected`

### 3. Verify PHPUnit Recognition

Check that PHPUnit recognizes all tests:

```bash
vendor/bin/phpunit --list-tests | grep "Terra\\Tests\\Integration"
```

Expected: Should list 65 integration tests including:
- AdminClientTest (13 tests)
- VideoRoomAdminTest (11 tests)
- StreamingAdminTest (9 tests)
- PluginAdminTest (11 tests)
- Plus inherited transport tests

### 4. Verify Test Runner Scripts

Check that test runner scripts are executable:

```bash
# Check permissions
ls -l run-admin-tests.sh
ls -l run-integration-tests.sh

# Verify help works
./run-admin-tests.sh --help
```

Expected: Scripts should be executable (x permission) and help should display.

### 5. Verify Documentation

Check that documentation is complete:

```bash
# View test documentation
less tests/Integration/README.md

# View implementation summary
less IMPLEMENTATION_SUMMARY.md

# Check main README has testing section
grep -A 20 "## Testing" README.md
```

Expected: All documentation files should be readable and complete.

## Test Structure Validation

### Verify Test Hierarchy

Run this command to see the test structure:

```bash
vendor/bin/phpunit --list-tests | grep -E "AdminClient|VideoRoom|Streaming|Plugin" | head -20
```

Expected output should show tests organized by class:
```
Terra\Tests\Integration\AdminClientTest::testGetServerInfo
Terra\Tests\Integration\AdminClientTest::testListAllSessions
Terra\Tests\Integration\VideoRoomAdminTest::testListRooms
Terra\Tests\Integration\VideoRoomAdminTest::testCreateRoom
Terra\Tests\Integration\StreamingAdminTest::testListMountpoints
...
```

### Verify Base Test Class Usage

Check that all tests extend BaseIntegrationTest:

```bash
grep "extends BaseIntegrationTest" tests/Integration/AdminClientTest.php
grep "extends BaseIntegrationTest" tests/Integration/VideoRoomAdminTest.php
grep "extends BaseIntegrationTest" tests/Integration/StreamingAdminTest.php
grep "extends BaseIntegrationTest" tests/Integration/PluginAdminTest.php
```

Expected: Each command should return a match.

### Verify Transport Configuration

Check that tests implement transport configuration:

```bash
grep "getTransportConfig" tests/Integration/AdminClientTest.php
grep "getTransportName" tests/Integration/AdminClientTest.php
```

Expected: Methods should be found in the test files.

## Dependency Validation

### Verify Composer Dependencies

Check that all required dependencies are installed:

```bash
# Check vendor directory
ls -ld vendor/

# Check PHPUnit
vendor/bin/phpunit --version

# Check autoloader
php -r "require 'vendor/autoload.php'; echo 'Autoloader OK\n';"

# Check Terra classes are autoloadable
php -r "require 'vendor/autoload.php'; echo class_exists('Terra\Admin\AdminClient') ? 'AdminClient OK\n' : 'AdminClient MISSING\n';"
```

Expected: All checks should pass.

### Verify Plugin Classes

Check that all plugin admin classes exist:

```bash
ls -l src/Plugin/VideoRoomAdmin.php
ls -l src/Plugin/StreamingAdmin.php
ls -l src/Plugin/VideoCallAdmin.php
ls -l src/Plugin/EchoTestAdmin.php
ls -l src/Plugin/RecordPlayAdmin.php
```

Expected: All plugin files should exist.

## Code Quality Validation

### Check Test Organization

Verify that tests are properly organized:

```bash
# Count test methods in each file
echo "AdminClientTest:"
grep -c "public function test" tests/Integration/AdminClientTest.php

echo "VideoRoomAdminTest:"
grep -c "public function test" tests/Integration/VideoRoomAdminTest.php

echo "StreamingAdminTest:"
grep -c "public function test" tests/Integration/StreamingAdminTest.php

echo "PluginAdminTest:"
grep -c "public function test" tests/Integration/PluginAdminTest.php
```

Expected counts:
- AdminClientTest: 13 tests
- VideoRoomAdminTest: 11 tests
- StreamingAdminTest: 9 tests
- PluginAdminTest: 11 tests

### Check Documentation Coverage

Verify that each test class has documentation:

```bash
# Check class-level docblocks
grep -A 3 "\/\*\*" tests/Integration/AdminClientTest.php | head -5
grep -A 3 "\/\*\*" tests/Integration/VideoRoomAdminTest.php | head -5
```

Expected: Each file should have descriptive class-level documentation.

### Check Error Handling

Verify that tests include error handling:

```bash
# Check for error handling patterns
grep -c "catch" tests/Integration/AdminClientTest.php
grep -c "recordResult" tests/Integration/VideoRoomAdminTest.php
```

Expected: Tests should include error handling and result recording.

## Pre-Execution Validation

Before running tests against a live Janus instance, verify these prerequisites:

### 1. Check Janus Installation

```bash
# Check if Janus is installed
which janus

# Check Janus version
janus --version

# Check if Janus is running
systemctl status janus || pgrep janus
```

### 2. Check Transport Configurations

```bash
# Check HTTP transport
cat /etc/janus/janus.transport.http.jcfg | grep -A 5 "admin"

# Check UnixSocket transport
cat /etc/janus/janus.transport.pfunix.jcfg | grep -A 5 "admin"

# Check ZMQ transport (if available)
cat /etc/janus/janus.transport.zmq.jcfg | grep -A 5 "admin" 2>/dev/null || echo "ZMQ not configured"
```

### 3. Check Admin Secret

```bash
# Verify admin secret in Janus config
cat /etc/janus/janus.jcfg | grep admin_secret
```

Expected: Should show admin_secret = "janusoverlord" (or your configured secret)

### 4. Test Transport Connectivity

```bash
# Test HTTP transport
curl -X POST http://localhost:7088/admin \
  -H 'Content-Type: application/json' \
  -d '{"janus":"info","admin_secret":"janusoverlord","transaction":"test"}' \
  --max-time 5

# Check UnixSocket exists
ls -la /var/run/janus/janus-admin.sock

# Check ZMQ port (if configured)
netstat -tlnp | grep 7889 || ss -tlnp | grep 7889
```

## Expected Test Behavior

### Without Janus Running

If Janus is not running, tests should:
- ✅ Be recognized by PHPUnit
- ✅ Have valid syntax
- ❌ Fail with connection errors (expected behavior)
- ✅ Provide clear error messages
- ✅ Suggest troubleshooting steps

### With Janus Running

If Janus is properly configured and running, tests should:
- ✅ Connect successfully to all configured transports
- ✅ Execute all admin operations
- ✅ Create/modify/delete test resources
- ✅ Clean up after themselves
- ✅ Pass all assertions
- ✅ Report results clearly

## Validation Scripts

### Automated Validation

Run this complete validation:

```bash
#!/bin/bash
echo "=== Terra Test Suite Validation ==="
echo ""

echo "1. Checking files..."
for f in tests/Integration/AdminClientTest.php tests/Integration/VideoRoomAdminTest.php tests/Integration/StreamingAdminTest.php tests/Integration/PluginAdminTest.php; do
  [ -f "$f" ] && echo "  ✅ $f" || echo "  ❌ $f MISSING"
done
echo ""

echo "2. Checking syntax..."
php -l tests/Integration/AdminClientTest.php > /dev/null 2>&1 && echo "  ✅ AdminClientTest" || echo "  ❌ AdminClientTest"
php -l tests/Integration/VideoRoomAdminTest.php > /dev/null 2>&1 && echo "  ✅ VideoRoomAdminTest" || echo "  ❌ VideoRoomAdminTest"
php -l tests/Integration/StreamingAdminTest.php > /dev/null 2>&1 && echo "  ✅ StreamingAdminTest" || echo "  ❌ StreamingAdminTest"
php -l tests/Integration/PluginAdminTest.php > /dev/null 2>&1 && echo "  ✅ PluginAdminTest" || echo "  ❌ PluginAdminTest"
echo ""

echo "3. Checking PHPUnit..."
if [ -f vendor/bin/phpunit ]; then
  TEST_COUNT=$(vendor/bin/phpunit --list-tests 2>&1 | grep -c "Terra\\\\Tests\\\\Integration")
  echo "  ✅ PHPUnit found ($TEST_COUNT tests)"
else
  echo "  ❌ PHPUnit not found (run: composer install)"
fi
echo ""

echo "4. Checking Janus..."
if systemctl is-active --quiet janus 2>/dev/null || pgrep -x janus > /dev/null 2>&1; then
  echo "  ✅ Janus is running"
else
  echo "  ⚠️  Janus not running (tests will fail)"
fi
echo ""

echo "=== Validation Complete ==="
```

## Troubleshooting Validation Issues

### Issue: PHPUnit Not Found

**Symptoms**: `vendor/bin/phpunit: No such file or directory`

**Solution**:
```bash
composer install
```

### Issue: Tests Not Listed

**Symptoms**: `phpunit --list-tests` doesn't show Terra tests

**Solution**: Check autoloader configuration
```bash
composer dump-autoload
```

### Issue: Syntax Errors

**Symptoms**: `php -l` reports errors

**Solution**: This shouldn't happen. Check that files were created correctly.

### Issue: Wrong Test Count

**Symptoms**: Fewer than 65 integration tests shown

**Solution**: 
```bash
# Verify all test files exist
ls -l tests/Integration/*.php

# Regenerate autoloader
composer dump-autoload
```

## Success Criteria

The test suite validation is successful when:

1. ✅ All 7 files exist and have content
2. ✅ All PHP test files have valid syntax
3. ✅ PHPUnit recognizes 65 integration tests
4. ✅ Test runner scripts are executable
5. ✅ Documentation is complete and readable
6. ✅ All test classes extend BaseIntegrationTest
7. ✅ Composer dependencies are installed
8. ✅ Terra admin classes are autoloadable

Once all validation checks pass, the test suite is ready to run against a live Janus instance.

## Next Steps

After validation:

1. **Install Janus**: If not already installed
   ```bash
   sudo ./setup-janus.sh
   ```

2. **Run Tests**: Execute the test suite
   ```bash
   ./run-admin-tests.sh
   ```

3. **Review Results**: Check test output and logs
   ```bash
   cat /tmp/terra-admin-test-results-*.log
   ```

4. **Troubleshoot Failures**: Use the troubleshooting guides in `tests/Integration/README.md`

## Documentation References

- **Test Suite Documentation**: `tests/Integration/README.md`
- **Implementation Summary**: `IMPLEMENTATION_SUMMARY.md`
- **Main README**: `README.md` (Testing section)
- **This Guide**: `TEST_VALIDATION.md`

## Support

For issues with validation or test execution:

1. Check the troubleshooting sections in `tests/Integration/README.md`
2. Review the implementation summary in `IMPLEMENTATION_SUMMARY.md`
3. Check Janus logs: `sudo journalctl -u janus -n 100`
4. Open an issue on GitHub with validation output
