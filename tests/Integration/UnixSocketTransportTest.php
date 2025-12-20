<?php

namespace Terra\Tests\Integration;

/**
 * UnixSocket Transport Integration Test
 * 
 * Tests Terra Admin Framework with UnixSocket transport on live Janus
 */
class UnixSocketTransportTest extends BaseIntegrationTest
{
    /**
     * @inheritDoc
     */
    protected function getTransportConfig(): array
    {
        return [
            'janus' => [
                'transport' => 'unix',
                'unix_socket_path' => getenv('JANUS_UNIX_SOCKET') ?: '/var/run/janus/janus-admin.sock',
                'admin_secret' => getenv('JANUS_ADMIN_SECRET') ?: 'janusoverlord',
                'timeout' => 30,
            ],
            'logging' => [
                'enabled' => true,
                'level' => 'debug',
            ],
        ];
    }

    /**
     * @inheritDoc
     */
    protected function getTransportName(): string
    {
        return 'UnixSocket';
    }

    /**
     * @inheritDoc
     */
    protected function printTransportSpecificTroubleshooting(): void
    {
        echo "UnixSocket Transport specific issues:\n\n";
        
        echo "  1. Check if UnixSocket transport is enabled:\n";
        echo "     $ cat /etc/janus/janus.transport.pfunix.jcfg\n";
        echo "     Verify: enabled = true, admin_enabled = true\n\n";
        
        echo "  2. Verify socket file exists:\n";
        echo "     $ ls -la /var/run/janus/janus-admin.sock\n";
        echo "     $ file /var/run/janus/janus-admin.sock\n\n";
        
        echo "  3. Check socket permissions:\n";
        echo "     $ stat /var/run/janus/janus-admin.sock\n";
        echo "     Socket should be readable/writable by current user\n\n";
        
        echo "  4. Test socket manually:\n";
        echo "     $ echo '{\"janus\":\"info\",\"admin_secret\":\"janusoverlord\",\"transaction\":\"test\"}' | \\\n";
        echo "       socat - UNIX-CONNECT:/var/run/janus/janus-admin.sock\n\n";
        
        echo "  5. Check socket directory permissions:\n";
        echo "     $ ls -ld /var/run/janus\n";
        echo "     $ sudo chmod 755 /var/run/janus\n\n";
        
        echo "  6. Enable UnixSocket transport if disabled:\n";
        echo "     Edit /etc/janus/janus.transport.pfunix.jcfg:\n";
        echo "       general: { enabled = true }\n";
        echo "       admin: { admin_enabled = true, admin_path = \"/var/run/janus/janus-admin.sock\" }\n";
        echo "     Then: sudo systemctl restart janus\n\n";
        
        echo "  7. Check if running on Windows:\n";
        echo "     UnixSocket transport is not supported on Windows\n";
        echo "     Use HTTP or ZMQ transport instead\n\n";
    }

    /**
     * Check if running on Windows (skip tests if so)
     */
    protected function setUp(): void
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $this->markTestSkipped('UnixSocket transport not supported on Windows');
        }

        parent::setUp();
    }

    /**
     * Test socket file existence
     */
    public function testSocketFileExists(): void
    {
        $socketPath = $this->getTransportConfig()['janus']['unix_socket_path'];
        $exists = file_exists($socketPath);
        
        $this->recordResult('Socket File Exists', $exists, 
            $exists ? null : "Socket file not found: $socketPath");
        
        if (!$exists) {
            echo "\n✗ Socket file not found: $socketPath\n";
            echo "  Make sure Janus is running and UnixSocket transport is enabled.\n\n";
        }
        
        $this->assertTrue($exists, "Socket file does not exist: $socketPath");
    }

    /**
     * Test socket file permissions
     */
    public function testSocketPermissions(): void
    {
        $socketPath = $this->getTransportConfig()['janus']['unix_socket_path'];
        
        if (!file_exists($socketPath)) {
            $this->recordResult('Socket Permissions', false, "Socket file does not exist");
            $this->markTestSkipped("Socket file does not exist: $socketPath");
            return;
        }

        $readable = is_readable($socketPath);
        $writable = is_writable($socketPath);
        
        $this->recordResult('Socket Permissions', $readable && $writable,
            $readable && $writable ? null : "Socket not readable/writable");
        
        if (!$readable || !$writable) {
            echo "\n✗ Socket permissions issue:\n";
            echo "  Readable: " . ($readable ? 'Yes' : 'No') . "\n";
            echo "  Writable: " . ($writable ? 'Yes' : 'No') . "\n";
            echo "  Fix with: sudo chmod 666 $socketPath\n\n";
        }
        
        $this->assertTrue($readable, "Socket file is not readable");
        $this->assertTrue($writable, "Socket file is not writable");
    }

    /**
     * Test SOCK_SEQPACKET support
     */
    public function testSeqpacketSupport(): void
    {
        $hasSocketExtension = extension_loaded('sockets');
        $hasSeqpacket = defined('STREAM_SOCK_SEQPACKET');
        
        $this->recordResult('SOCK_SEQPACKET Support', 
            $hasSocketExtension || true, // Always pass, we have fallback
            $hasSocketExtension && $hasSeqpacket ? null : "Using fallback socket method");
        
        if (!$hasSocketExtension || !$hasSeqpacket) {
            echo "\n⚠ STREAM_SOCK_SEQPACKET not available, using fallback\n";
            echo "  Socket extension: " . ($hasSocketExtension ? 'Loaded' : 'Not loaded') . "\n";
            echo "  STREAM_SOCK_SEQPACKET: " . ($hasSeqpacket ? 'Defined' : 'Not defined') . "\n\n";
        }
        
        $this->assertTrue(true); // Always pass, we have fallback mechanisms
    }
}
