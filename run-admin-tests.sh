#!/bin/bash

# Terra Admin Test Suite Runner
# Comprehensive test execution for all admin features across all transports

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Test results
TOTAL_SUITES=0
PASSED_SUITES=0
FAILED_SUITES=0
SKIPPED_SUITES=0

# Log file
LOG_FILE="/tmp/terra-admin-test-results-$(date +%Y%m%d_%H%M%S).log"

echo "========================================="
echo "Terra Admin Test Suite Runner"
echo "========================================="
echo ""

echo -e "${BLUE}Test logs will be saved to: $LOG_FILE${NC}"
echo ""

# Function to print section header
print_section() {
    echo ""
    echo "========================================="
    echo "$1"
    echo "========================================="
    echo ""
}

# Function to check prerequisites
check_prerequisites() {
    print_section "Checking Prerequisites"
    
    local all_ok=true
    
    # Check PHP
    if command -v php &> /dev/null; then
        PHP_VERSION=$(php -v | head -n 1)
        echo -e "${GREEN}✓${NC} PHP: $PHP_VERSION"
    else
        echo -e "${RED}✗${NC} PHP not found"
        all_ok=false
    fi
    
    # Check Composer
    if command -v composer &> /dev/null; then
        COMPOSER_VERSION=$(composer --version)
        echo -e "${GREEN}✓${NC} Composer: $COMPOSER_VERSION"
    else
        echo -e "${RED}✗${NC} Composer not found"
        all_ok=false
    fi
    
    # Check PHPUnit
    if [ -f "vendor/bin/phpunit" ]; then
        PHPUNIT_VERSION=$(vendor/bin/phpunit --version | head -n 1)
        echo -e "${GREEN}✓${NC} PHPUnit: $PHPUNIT_VERSION"
    else
        echo -e "${RED}✗${NC} PHPUnit not found (run: composer install)"
        all_ok=false
    fi
    
    # Check Janus
    if command -v janus &> /dev/null; then
        JANUS_VERSION=$(janus --version 2>&1 | head -n 1 || echo "Unknown")
        echo -e "${GREEN}✓${NC} Janus: $JANUS_VERSION"
    else
        echo -e "${YELLOW}⚠${NC} Janus not found (run ./setup-janus.sh)"
        all_ok=false
    fi
    
    # Check if Janus is running
    if systemctl is-active --quiet janus 2>/dev/null || pgrep -x janus > /dev/null 2>&1; then
        echo -e "${GREEN}✓${NC} Janus service is running"
    else
        echo -e "${YELLOW}⚠${NC} Janus service not running (start with: sudo systemctl start janus)"
        all_ok=false
    fi
    
    # Check ZMQ extension
    if php -m | grep -q zmq; then
        echo -e "${GREEN}✓${NC} PHP ZMQ extension loaded"
    else
        echo -e "${YELLOW}⚠${NC} PHP ZMQ extension not loaded (ZMQ tests will be skipped)"
    fi
    
    echo ""
    
    if [ "$all_ok" = false ]; then
        echo -e "${RED}✗ Prerequisites check failed${NC}"
        echo ""
        echo "Please fix the issues above before running tests."
        echo ""
        echo "Quick fixes:"
        echo "  1. Install dependencies: composer install"
        echo "  2. Install Janus: sudo ./setup-janus.sh"
        echo "  3. Start Janus: sudo systemctl start janus"
        echo ""
        exit 1
    fi
    
    echo -e "${GREEN}✓ All prerequisites met${NC}"
}

# Function to check transport availability
check_transports() {
    print_section "Checking Transport Availability"
    
    # HTTP Transport
    if curl -s -X POST http://localhost:7088/admin \
        -H 'Content-Type: application/json' \
        -d '{"janus":"info","admin_secret":"janusoverlord","transaction":"test"}' \
        --max-time 5 &>/dev/null; then
        echo -e "${GREEN}✓${NC} HTTP transport available (localhost:7088)"
    else
        echo -e "${YELLOW}⚠${NC} HTTP transport not responding"
        echo "  Check: cat /etc/janus/janus.transport.http.jcfg"
    fi
    
    # UnixSocket Transport
    if [ -S /var/run/janus/janus-admin.sock ]; then
        echo -e "${GREEN}✓${NC} UnixSocket available (/var/run/janus/janus-admin.sock)"
    else
        echo -e "${YELLOW}⚠${NC} UnixSocket not found"
        echo "  Check: cat /etc/janus/janus.transport.pfunix.jcfg"
    fi
    
    # ZMQ Transport
    if [ -f /usr/lib/janus/transports/libjanus_zmq.so ] || [ -f /usr/lib/x86_64-linux-gnu/janus/transports/libjanus_zmq.so ]; then
        echo -e "${GREEN}✓${NC} ZMQ transport library found"
        if netstat -tlnp 2>/dev/null | grep -q 7889 || ss -tlnp 2>/dev/null | grep -q 7889; then
            echo -e "${GREEN}✓${NC} ZMQ transport listening (tcp://localhost:7889)"
        else
            echo -e "${YELLOW}⚠${NC} ZMQ transport not listening on port 7889"
            echo "  Check: cat /etc/janus/janus.transport.zmq.jcfg"
        fi
    else
        echo -e "${YELLOW}⚠${NC} ZMQ transport library not found"
        echo "  Install with: sudo apt-get install janus-zeromq"
    fi
    
    echo ""
}

# Function to run test suite
run_test_suite() {
    local suite=$1
    local description=$2
    
    print_section "Running $description"
    
    echo "Test suite: $suite"
    echo "Started at: $(date)"
    echo ""
    
    # Run PHPUnit
    if vendor/bin/phpunit tests/Integration/$suite 2>&1 | tee -a "$LOG_FILE"; then
        echo -e "${GREEN}✓${NC} $description passed"
        ((PASSED_SUITES++))
    else
        EXIT_CODE=${PIPESTATUS[0]}
        if [ $EXIT_CODE -eq 0 ]; then
            echo -e "${YELLOW}⚠${NC} $description skipped"
            ((SKIPPED_SUITES++))
        else
            echo -e "${RED}✗${NC} $description failed"
            ((FAILED_SUITES++))
        fi
    fi
    
    ((TOTAL_SUITES++))
    echo ""
}

# Function to print summary
print_summary() {
    print_section "Test Summary"
    
    echo "Total test suites: $TOTAL_SUITES"
    echo -e "${GREEN}Passed: $PASSED_SUITES${NC}"
    echo -e "${RED}Failed: $FAILED_SUITES${NC}"
    echo -e "${YELLOW}Skipped: $SKIPPED_SUITES${NC}"
    echo ""
    echo "Detailed logs: $LOG_FILE"
    echo ""
    
    if [ $FAILED_SUITES -gt 0 ]; then
        print_section "Troubleshooting Failed Tests"
        
        echo "Common issues and fixes:"
        echo ""
        echo "1. Janus not running:"
        echo "   $ sudo systemctl status janus"
        echo "   $ sudo systemctl start janus"
        echo ""
        echo "2. Transport not configured:"
        echo "   $ ls -la /etc/janus/*.jcfg"
        echo "   $ cat /etc/janus/janus.transport.*.jcfg"
        echo ""
        echo "3. Permissions issue:"
        echo "   $ sudo chmod 666 /var/run/janus/janus-admin.sock"
        echo ""
        echo "4. Check Janus logs:"
        echo "   $ sudo journalctl -u janus -n 100"
        echo "   $ sudo tail -f /var/log/janus/janus.log"
        echo ""
        echo "5. Re-run setup:"
        echo "   $ sudo ./setup-janus.sh"
        echo ""
        echo "6. Run specific test for more details:"
        echo "   $ vendor/bin/phpunit tests/Integration/AdminClientTest.php --verbose"
        echo ""
        
        return 1
    fi
    
    echo -e "${GREEN}✓ All tests passed or skipped${NC}"
    return 0
}

# Main execution
main() {
    cd "$(dirname "$0")"
    
    # Parse arguments
    VERBOSE=false
    RUN_UNIT=true
    RUN_INTEGRATION=true
    
    while [[ $# -gt 0 ]]; do
        case $1 in
            -v|--verbose)
                VERBOSE=true
                shift
                ;;
            --integration-only)
                RUN_UNIT=false
                shift
                ;;
            --unit-only)
                RUN_INTEGRATION=false
                shift
                ;;
            -h|--help)
                echo "Usage: $0 [OPTIONS]"
                echo ""
                echo "Options:"
                echo "  -v, --verbose           Run tests with verbose output"
                echo "  --integration-only      Run only integration tests"
                echo "  --unit-only            Run only unit tests"
                echo "  -h, --help             Show this help message"
                echo ""
                exit 0
                ;;
            *)
                echo "Unknown option: $1"
                echo "Use -h or --help for usage information"
                exit 1
                ;;
        esac
    done
    
    # Check prerequisites
    check_prerequisites
    
    # Check transport availability (only for integration tests)
    if [ "$RUN_INTEGRATION" = true ]; then
        check_transports
    fi
    
    # Run unit tests if requested
    if [ "$RUN_UNIT" = true ]; then
        print_section "Running Unit Tests"
        if vendor/bin/phpunit --testsuite Unit 2>&1 | tee -a "$LOG_FILE"; then
            echo -e "${GREEN}✓${NC} Unit tests passed"
            ((PASSED_SUITES++))
        else
            echo -e "${RED}✗${NC} Unit tests failed"
            ((FAILED_SUITES++))
        fi
        ((TOTAL_SUITES++))
        echo ""
    fi
    
    # Run integration test suites if requested
    if [ "$RUN_INTEGRATION" = true ]; then
        # Core admin features tests
        run_test_suite "AdminClientTest.php" "Admin Client Tests"
        
        # Plugin admin tests
        run_test_suite "VideoRoomAdminTest.php" "VideoRoom Plugin Tests"
        run_test_suite "StreamingAdminTest.php" "Streaming Plugin Tests"
        run_test_suite "PluginAdminTest.php" "Other Plugin Tests (VideoCall, EchoTest, RecordPlay)"
        
        # Transport tests
        run_test_suite "HttpTransportTest.php" "HTTP Transport Tests"
        run_test_suite "UnixSocketTransportTest.php" "UnixSocket Transport Tests"
        
        # Only run ZMQ tests if extension is available
        if php -m | grep -q zmq; then
            run_test_suite "ZmqTransportTest.php" "ZeroMQ Transport Tests"
        else
            echo -e "${YELLOW}⚠ Skipping ZMQ tests (extension not loaded)${NC}"
            ((SKIPPED_SUITES++))
            ((TOTAL_SUITES++))
            echo ""
        fi
    fi
    
    # Print summary
    print_summary
    
    exit $?
}

# Run main function
main "$@"
