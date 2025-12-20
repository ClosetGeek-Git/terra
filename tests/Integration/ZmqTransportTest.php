<?php

namespace Terra\Tests\Integration;

/**
 * ZMQ Transport Integration Test
 * 
 * Tests Terra Admin Framework with ZeroMQ transport on live Janus
 */
class ZmqTransportTest extends BaseIntegrationTest
{
    /**
     * @inheritDoc
     */
    protected function getTransportConfig(): array
    {
        return [
            'janus' => [
                'transport' => 'zmq',
                'admin_address' => getenv('JANUS_ADMIN_ADDRESS') ?: 'tcp://localhost:7889',
                'admin_secret' => getenv('JANUS_ADMIN_SECRET') ?: 'janusoverlord',
                'timeout' => 30,
            ],
            'zmq' => [
                'persistent' => true,
                'linger' => 0,
                'preferred_implementation' => 'auto',
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
        return 'ZeroMQ';
    }

    /**
     * @inheritDoc
     */
    protected function printTransportSpecificTroubleshooting(): void
    {
        echo "ZeroMQ Transport specific issues:\n\n";
        
        echo "  1. Check if ZMQ extension is installed:\n";
        echo "     $ php -m | grep zmq\n";
        echo "     $ pecl list | grep zmq\n\n";
        
        echo "  2. Install ZMQ extension if missing:\n";
        echo "     $ sudo apt-get install libzmq3-dev php-zmq\n";
        echo "     Or: $ pecl install zmq-beta\n\n";
        
        echo "  3. Check if ZMQ transport is enabled in Janus:\n";
        echo "     $ cat /etc/janus/janus.transport.zmq.jcfg\n";
        echo "     Verify: enabled = true, admin_enabled = true\n\n";
        
        echo "  4. Verify ZMQ transport library exists:\n";
        echo "     $ ls -la /usr/lib/janus/transports/libjanus_zmq.so\n\n";
        
        echo "  5. Install janus-zeromq if missing:\n";
        echo "     $ sudo apt-get install janus-zeromq\n";
        echo "     Or compile from source\n\n";
        
        echo "  6. Check if port 7889 is listening:\n";
        echo "     $ netstat -tlnp | grep 7889\n";
        echo "     $ ss -tlnp | grep 7889\n\n";
        
        echo "  7. Test ZMQ endpoint:\n";
        echo "     Use tools like zmq_monitor or custom test script\n\n";
        
        echo "  8. Enable ZMQ transport if disabled:\n";
        echo "     Edit /etc/janus/janus.transport.zmq.jcfg:\n";
        echo "       general: { enabled = true }\n";
        echo "       admin: { admin_enabled = true, bind = \"tcp://*:7889\" }\n";
        echo "     Then: sudo systemctl restart janus\n\n";
    }

    /**
     * Check if ZMQ extension is loaded
     */
    protected function setUp(): void
    {
        if (!extension_loaded('zmq')) {
            $this->markTestSkipped('ZMQ extension is not loaded. Install with: sudo apt-get install php-zmq');
        }

        parent::setUp();
    }

    /**
     * Test ZMQ extension availability
     */
    public function testZmqExtensionLoaded(): void
    {
        $loaded = extension_loaded('zmq');
        
        $this->recordResult('ZMQ Extension', $loaded,
            $loaded ? null : "ZMQ extension not loaded");
        
        if (!$loaded) {
            echo "\n✗ ZMQ extension not loaded\n";
            echo "  Install with: sudo apt-get install libzmq3-dev php-zmq\n\n";
        }
        
        $this->assertTrue($loaded, "ZMQ extension is not loaded");
    }

    /**
     * Test ZMQ implementation fallback
     */
    public function testZmqImplementationFallback(): void
    {
        $this->createClient();
        
        // Check which implementations are available
        $available = \Terra\Transport\ZmqFactory::getAvailableImplementations();
        
        $hasImplementation = !empty($available);
        
        $this->recordResult('ZMQ Implementation', $hasImplementation,
            $hasImplementation ? null : "No ZMQ implementation available");
        
        if (!empty($available)) {
            echo "\n✓ Available ZMQ implementations:\n";
            foreach ($available as $impl) {
                echo "  - $impl\n";
            }
            echo "\n";
        } else {
            echo "\n✗ No ZMQ implementations found\n";
            echo "  Install react/zmq with: composer require react/zmq\n\n";
        }
        
        $this->assertTrue($hasImplementation, "No ZMQ implementation available");
    }

    /**
     * Test ZMQ socket types
     */
    public function testZmqSocketTypes(): void
    {
        $hasDealer = defined('ZMQ::SOCKET_DEALER');
        $hasReq = defined('ZMQ::SOCKET_REQ');
        
        $this->recordResult('ZMQ Socket Types', $hasDealer && $hasReq,
            $hasDealer && $hasReq ? null : "Required ZMQ socket types not available");
        
        if (!$hasDealer || !$hasReq) {
            echo "\n⚠ ZMQ socket type issue:\n";
            echo "  SOCKET_DEALER: " . ($hasDealer ? 'Available' : 'Not available') . "\n";
            echo "  SOCKET_REQ: " . ($hasReq ? 'Available' : 'Not available') . "\n\n";
        }
        
        $this->assertTrue($hasDealer, "ZMQ::SOCKET_DEALER not defined");
    }
}
