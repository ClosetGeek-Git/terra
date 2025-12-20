<?php

namespace Terra\Tests\Unit\Transport;

use PHPUnit\Framework\TestCase;
use React\EventLoop\Factory as LoopFactory;
use Terra\Transport\Socket\SeqpacketSocket;

/**
 * SeqpacketSocket Shim Test
 * 
 * Tests the SOCK_SEQPACKET shim implementation
 */
class SeqpacketSocketTest extends TestCase
{
    /**
     * @var string Test socket path
     */
    private $testSocketPath;

    /**
     * @var resource|null Server socket
     */
    private $serverSocket;

    /**
     * Setup test environment
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Skip tests on Windows
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $this->markTestSkipped('UnixSocket not supported on Windows');
        }

        // Create temporary socket path
        $this->testSocketPath = sys_get_temp_dir() . '/terra-test-' . uniqid() . '.sock';
    }

    /**
     * Cleanup test environment
     */
    protected function tearDown(): void
    {
        if ($this->serverSocket) {
            @socket_close($this->serverSocket);
        }

        if (file_exists($this->testSocketPath)) {
            @unlink($this->testSocketPath);
        }

        parent::tearDown();
    }

    /**
     * Create a test server socket
     * 
     * @param int|object $type Socket type (SOCK_STREAM, SOCK_SEQPACKET, etc.)
     * @return resource|object|false Socket resource/object or false
     */
    private function createTestServer($type = SOCK_STREAM)
    {
        if (!function_exists('socket_create')) {
            $this->markTestSkipped('socket_create not available');
            return false;
        }

        // Handle both PHP 7.x int constants and PHP 8.x enum values
        $socketType = is_int($type) ? $type : $type->value ?? SOCK_STREAM;

        $socket = @socket_create(AF_UNIX, $socketType, 0);
        if ($socket === false) {
            return false;
        }

        @socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);

        if (@socket_bind($socket, $this->testSocketPath) === false) {
            socket_close($socket);
            return false;
        }

        if (@socket_listen($socket, 1) === false) {
            socket_close($socket);
            return false;
        }

        return $socket;
    }

    /**
     * Test socket creation with SOCK_SEQPACKET
     */
    public function testSeqpacketSocketCreation(): void
    {
        if (!defined('STREAM_SOCK_SEQPACKET')) {
            $this->markTestSkipped('STREAM_SOCK_SEQPACKET not available on this system');
        }

        // Create server socket
        $this->serverSocket = $this->createTestServer(STREAM_SOCK_SEQPACKET);
        
        if ($this->serverSocket === false) {
            $this->markTestSkipped('Could not create SEQPACKET server socket');
        }

        // Create client socket using shim
        $loop = LoopFactory::create();
        
        try {
            $socket = SeqpacketSocket::create($this->testSocketPath, $loop);
            
            $this->assertInstanceOf(SeqpacketSocket::class, $socket);
            $this->assertTrue($socket->isConnected());
            $this->assertEquals(SeqpacketSocket::TYPE_SEQPACKET, $socket->getSocketType());
            
            $socket->close();
        } catch (\Exception $e) {
            $this->fail("Failed to create SEQPACKET socket: " . $e->getMessage());
        }
    }

    /**
     * Test fallback to SOCK_STREAM
     */
    public function testFallbackToStream(): void
    {
        // Create SOCK_STREAM server
        $this->serverSocket = $this->createTestServer(SOCK_STREAM);
        
        if ($this->serverSocket === false) {
            $this->markTestSkipped('Could not create STREAM server socket');
        }

        $loop = LoopFactory::create();
        
        try {
            $socket = SeqpacketSocket::create($this->testSocketPath, $loop);
            
            $this->assertInstanceOf(SeqpacketSocket::class, $socket);
            $this->assertTrue($socket->isConnected());
            
            // Should use either SEQPACKET, DGRAM, or STREAM
            $this->assertContains(
                $socket->getSocketType(),
                [SeqpacketSocket::TYPE_SEQPACKET, SeqpacketSocket::TYPE_DGRAM, SeqpacketSocket::TYPE_STREAM]
            );
            
            $socket->close();
        } catch (\Exception $e) {
            $this->fail("Failed to create socket with fallback: " . $e->getMessage());
        }
    }

    /**
     * Test socket info retrieval
     */
    public function testSocketInfo(): void
    {
        $this->serverSocket = $this->createTestServer(SOCK_STREAM);
        
        if ($this->serverSocket === false) {
            $this->markTestSkipped('Could not create server socket');
        }

        $loop = LoopFactory::create();
        $socket = SeqpacketSocket::create($this->testSocketPath, $loop);
        
        $info = $socket->getInfo();
        
        $this->assertIsArray($info);
        $this->assertArrayHasKey('type', $info);
        $this->assertArrayHasKey('connected', $info);
        $this->assertTrue($info['connected']);
        
        $socket->close();
    }

    /**
     * Test error handling for non-existent socket
     */
    public function testNonExistentSocket(): void
    {
        $loop = LoopFactory::create();
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Socket file does not exist');
        
        SeqpacketSocket::create('/nonexistent/socket.sock', $loop);
    }

    /**
     * Test socket close
     */
    public function testSocketClose(): void
    {
        $this->serverSocket = $this->createTestServer(SOCK_STREAM);
        
        if ($this->serverSocket === false) {
            $this->markTestSkipped('Could not create server socket');
        }

        $loop = LoopFactory::create();
        $socket = SeqpacketSocket::create($this->testSocketPath, $loop);
        
        $this->assertTrue($socket->isConnected());
        
        $socket->close();
        
        $this->assertFalse($socket->isConnected());
        $this->assertNull($socket->getResource());
    }
}
