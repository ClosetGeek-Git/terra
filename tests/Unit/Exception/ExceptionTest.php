<?php

namespace Terra\Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use Terra\Exception\TerraException;
use Terra\Exception\ConnectionException;
use Terra\Exception\InvalidJsonException;
use Terra\Exception\TimeoutException;

/**
 * Unit tests for Exception classes
 */
class ExceptionTest extends TestCase
{
    public function testTerraException()
    {
        $exception = new TerraException('Test message');
        
        $this->assertInstanceOf(\Exception::class, $exception);
        $this->assertEquals('Test message', $exception->getMessage());
    }

    public function testConnectionException()
    {
        $exception = new ConnectionException('Connection failed');
        
        $this->assertInstanceOf(TerraException::class, $exception);
        $this->assertEquals('Connection failed', $exception->getMessage());
    }

    public function testInvalidJsonException()
    {
        $exception = new InvalidJsonException('Invalid JSON');
        
        $this->assertInstanceOf(TerraException::class, $exception);
        $this->assertEquals('Invalid JSON', $exception->getMessage());
    }

    public function testTimeoutException()
    {
        $exception = new TimeoutException('Request timeout');
        
        $this->assertInstanceOf(TerraException::class, $exception);
        $this->assertEquals('Request timeout', $exception->getMessage());
    }

    public function testExceptionCode()
    {
        $exception = new ConnectionException('Test', 500);
        
        $this->assertEquals(500, $exception->getCode());
    }
}
