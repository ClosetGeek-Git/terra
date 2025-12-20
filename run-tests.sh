#!/bin/bash
# Test runner script for Terra test suite
# Runs tests against all configured transport types

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Default values
RUN_UNIT=true
RUN_INTEGRATION=true
TRANSPORTS=("zmq" "unix" "http")
COVERAGE=false
VERBOSE=false

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --unit-only)
            RUN_INTEGRATION=false
            shift
            ;;
        --integration-only)
            RUN_UNIT=false
            shift
            ;;
        --transport)
            TRANSPORTS=("$2")
            shift 2
            ;;
        --coverage)
            COVERAGE=true
            shift
            ;;
        --verbose)
            VERBOSE=true
            shift
            ;;
        --help)
            echo "Usage: $0 [options]"
            echo ""
            echo "Options:"
            echo "  --unit-only           Run only unit tests"
            echo "  --integration-only    Run only integration tests"
            echo "  --transport TYPE      Test only specific transport (zmq, unix, http)"
            echo "  --coverage            Generate code coverage report"
            echo "  --verbose             Enable verbose output"
            echo "  --help                Show this help message"
            exit 0
            ;;
        *)
            echo "Unknown option: $1"
            echo "Use --help for usage information"
            exit 1
            ;;
    esac
done

# Check if Janus is running
check_janus() {
    echo -e "${YELLOW}Checking Janus Gateway status...${NC}"
    
    # Check if any Janus process is running
    if pgrep -x "janus" > /dev/null; then
        echo -e "${GREEN}✓ Janus Gateway is running${NC}"
        return 0
    else
        echo -e "${RED}✗ Janus Gateway is not running${NC}"
        echo "Please start Janus Gateway before running integration tests"
        return 1
    fi
}

# Run unit tests
run_unit_tests() {
    echo ""
    echo -e "${YELLOW}======================================${NC}"
    echo -e "${YELLOW}Running Unit Tests${NC}"
    echo -e "${YELLOW}======================================${NC}"
    
    if [ "$COVERAGE" = true ]; then
        vendor/bin/phpunit --testsuite Unit --coverage-text
    elif [ "$VERBOSE" = true ]; then
        vendor/bin/phpunit --testsuite Unit --verbose
    else
        vendor/bin/phpunit --testsuite Unit
    fi
    
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓ Unit tests passed${NC}"
    else
        echo -e "${RED}✗ Unit tests failed${NC}"
        exit 1
    fi
}

# Run integration tests for a specific transport
run_integration_tests_for_transport() {
    local transport=$1
    
    echo ""
    echo -e "${YELLOW}======================================${NC}"
    echo -e "${YELLOW}Running Integration Tests - ${transport^^} Transport${NC}"
    echo -e "${YELLOW}======================================${NC}"
    
    export JANUS_TRANSPORT=$transport
    
    if [ "$VERBOSE" = true ]; then
        vendor/bin/phpunit --testsuite Integration --verbose
    else
        vendor/bin/phpunit --testsuite Integration
    fi
    
    local result=$?
    
    if [ $result -eq 0 ]; then
        echo -e "${GREEN}✓ Integration tests passed for ${transport^^} transport${NC}"
    else
        echo -e "${RED}✗ Integration tests failed for ${transport^^} transport${NC}"
    fi
    
    return $result
}

# Main execution
echo -e "${GREEN}======================================${NC}"
echo -e "${GREEN}Terra Test Suite Runner${NC}"
echo -e "${GREEN}======================================${NC}"

# Check if vendor directory exists
if [ ! -d "vendor" ]; then
    echo -e "${RED}Error: vendor directory not found${NC}"
    echo "Please run 'composer install' first"
    exit 1
fi

# Run unit tests
if [ "$RUN_UNIT" = true ]; then
    run_unit_tests
fi

# Run integration tests
if [ "$RUN_INTEGRATION" = true ]; then
    # Check if Janus is running
    if ! check_janus; then
        echo -e "${YELLOW}Skipping integration tests${NC}"
        exit 0
    fi
    
    # Track overall success
    OVERALL_SUCCESS=true
    
    # Run tests for each transport
    for transport in "${TRANSPORTS[@]}"; do
        if ! run_integration_tests_for_transport "$transport"; then
            OVERALL_SUCCESS=false
        fi
    done
    
    # Generate coverage report if requested
    if [ "$COVERAGE" = true ]; then
        echo ""
        echo -e "${YELLOW}Generating code coverage report...${NC}"
        vendor/bin/phpunit --coverage-html coverage --coverage-clover coverage.xml
        echo -e "${GREEN}✓ Coverage report generated in ./coverage directory${NC}"
    fi
    
    # Print summary
    echo ""
    echo -e "${YELLOW}======================================${NC}"
    echo -e "${YELLOW}Test Summary${NC}"
    echo -e "${YELLOW}======================================${NC}"
    
    if [ "$OVERALL_SUCCESS" = true ]; then
        echo -e "${GREEN}✓ All tests passed!${NC}"
        exit 0
    else
        echo -e "${RED}✗ Some tests failed${NC}"
        exit 1
    fi
fi

echo -e "${GREEN}✓ Test run completed${NC}"
