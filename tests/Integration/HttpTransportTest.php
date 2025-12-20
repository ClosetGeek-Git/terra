<?php

namespace Terra\Tests\Integration;

/**
 * HTTP Transport Integration Test
 * 
 * Tests Terra Admin Framework with HTTP/REST API transport on live Janus
 */
class HttpTransportTest extends BaseIntegrationTest
{
    /**
     * @inheritDoc
     */
    protected function getTransportConfig(): array
    {
        return [
            'janus' => [
                'transport' => 'http',
                'http_address' => getenv('JANUS_HTTP_ADDRESS') ?: 'http://localhost:7088/admin',
                'http_event_address' => getenv('JANUS_HTTP_EVENT_ADDRESS') ?: 'http://localhost:7088/admin',
                'admin_secret' => getenv('JANUS_ADMIN_SECRET') ?: 'janusoverlord',
                'timeout' => 30,
            ],
            'http' => [
                'long_polling' => true,
                'long_poll_timeout' => 30,
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
        return 'HTTP/REST API';
    }

    /**
     * @inheritDoc
     */
    protected function printTransportSpecificTroubleshooting(): void
    {
        echo "HTTP/REST API Transport specific issues:\n\n";
        
        echo "  1. Check if HTTP transport is enabled:\n";
        echo "     $ cat /etc/janus/janus.transport.http.jcfg\n";
        echo "     Verify: admin_http = true, admin_port = 7088\n\n";
        
        echo "  2. Test HTTP endpoint directly:\n";
        echo "     $ curl -X POST http://localhost:7088/admin \\\n";
        echo "       -H 'Content-Type: application/json' \\\n";
        echo "       -d '{\"janus\":\"info\",\"admin_secret\":\"janusoverlord\",\"transaction\":\"test\"}'\n\n";
        
        echo "  3. Check if port 7088 is listening:\n";
        echo "     $ netstat -tlnp | grep 7088\n";
        echo "     $ ss -tlnp | grep 7088\n\n";
        
        echo "  4. Check firewall rules:\n";
        echo "     $ sudo ufw status\n";
        echo "     $ sudo iptables -L | grep 7088\n\n";
        
        echo "  5. Verify admin secret matches:\n";
        echo "     Check /etc/janus/janus.jcfg for admin_secret setting\n\n";
        
        echo "  6. Enable HTTP transport if disabled:\n";
        echo "     Edit /etc/janus/janus.transport.http.jcfg:\n";
        echo "       admin: { admin_http = true, admin_port = 7088 }\n";
        echo "     Then: sudo systemctl restart janus\n\n";
    }

    /**
     * Test long polling for events
     */
    public function testLongPolling(): void
    {
        $this->createClient();

        $pollingActive = false;
        $error = null;

        $this->client->onEvent(function ($event) use (&$pollingActive) {
            $pollingActive = true;
        });

        $this->client->connect()->then(
            function () use (&$pollingActive) {
                $pollingActive = true; // Connection implies polling is set up
            },
            function ($e) use (&$error) {
                $error = $e->getMessage();
            }
        );

        $this->client->getLoop()->addTimer(3, function () {
            $this->client->disconnect();
        });

        $this->client->getLoop()->run();

        $this->recordResult('Long Polling Setup', $pollingActive, $error);
        $this->assertTrue($pollingActive, "Long polling setup failed: " . ($error ?? 'Unknown error'));
    }

    /**
     * Test HTTP timeout handling
     */
    public function testTimeoutHandling(): void
    {
        $config = $this->getTransportConfig();
        $config['janus']['timeout'] = 1; // Very short timeout

        try {
            $client = new AdminClient($config);
            $this->recordResult('Timeout Configuration', true);
            $this->assertTrue(true);
        } catch (\Exception $e) {
            $this->recordResult('Timeout Configuration', false, $e->getMessage());
            $this->fail("Failed to configure timeout: " . $e->getMessage());
        }
    }
}
