<?php

namespace Terra\Transport;

use React\EventLoop\LoopInterface;
use React\Promise\Promise;
use React\Promise\Deferred;
use React\Http\Browser;
use Terra\Config\ConfigManager;
use Terra\Logger\Logger;
use Terra\Exception\ConnectionException;
use Terra\Exception\TimeoutException;
use Terra\Exception\InvalidJsonException;

/**
 * HTTP/REST Transport for Janus Gateway
 * 
 * Provides communication with Janus Gateway via RESTful API
 */
class HttpTransport implements TransportInterface
{
    /**
     * @var LoopInterface Event loop
     */
    private $loop;

    /**
     * @var ConfigManager Configuration manager
     */
    private $config;

    /**
     * @var Logger Logger instance
     */
    private $logger;

    /**
     * @var Browser HTTP client
     */
    private $httpClient;

    /**
     * @var array Event handlers
     */
    private $eventHandlers = [];

    /**
     * @var bool Connection status
     */
    private $connected = false;

    /**
     * @var string Base URL for Janus admin API
     */
    private $baseUrl;

    /**
     * Constructor
     * 
     * @param LoopInterface $loop Event loop
     * @param ConfigManager $config Configuration manager
     * @param Logger $logger Logger instance
     */
    public function __construct(LoopInterface $loop, ConfigManager $config, Logger $logger)
    {
        $this->loop = $loop;
        $this->config = $config;
        $this->logger = $logger;
        $this->httpClient = new Browser($this->loop);

        // Set timeout for HTTP client
        $timeout = $this->config->get('janus.timeout', 30);
        $this->httpClient = $this->httpClient->withTimeout($timeout);
    }

    /**
     * Connect to Janus Gateway via HTTP
     * 
     * @return Promise
     */
    public function connect(): Promise
    {
        $deferred = new Deferred();

        $host = $this->config->get('janus.admin_host', 'localhost');
        $port = $this->config->get('janus.admin_port', 7088);
        $basePath = $this->config->get('janus.admin_base_path', '/admin');
        $secure = $this->config->get('janus.admin_secure', false);

        $protocol = $secure ? 'https' : 'http';
        $this->baseUrl = "{$protocol}://{$host}:{$port}{$basePath}";

        $this->logger->info("Connecting to Janus via HTTP: {$this->baseUrl}");

        // Test connection with an info request
        $this->httpClient->get($this->baseUrl . '/info')
            ->then(
                function ($response) use ($deferred) {
                    $body = (string) $response->getBody();
                    $data = json_decode($body, true);

                    if ($data === null) {
                        $deferred->reject(new InvalidJsonException("Invalid JSON response from server"));
                        return;
                    }

                    $this->connected = true;
                    $this->logger->info("Connected to Janus via HTTP", ['info' => $data]);
                    $deferred->resolve();
                },
                function (\Exception $e) use ($deferred) {
                    $this->logger->error("Failed to connect via HTTP: " . $e->getMessage());
                    $deferred->reject(new ConnectionException(
                        "Failed to connect to Janus HTTP API: " . $e->getMessage()
                    ));
                }
            );

        return $deferred->promise();
    }

    /**
     * Disconnect from Janus Gateway
     * 
     * @return void
     */
    public function disconnect(): void
    {
        $this->connected = false;
        $this->logger->info("Disconnected from Janus HTTP API");
    }

    /**
     * Send a request to Janus Gateway
     * 
     * @param array $payload Request payload
     * @return Promise
     */
    public function sendRequest(array $payload): Promise
    {
        $deferred = new Deferred();

        if (!$this->connected) {
            $deferred->reject(new ConnectionException("Not connected to Janus Gateway"));
            return $deferred->promise();
        }

        // Generate transaction ID
        $transaction = $this->generateTransaction();
        $payload['transaction'] = $transaction;

        // Determine the endpoint based on the request type
        $endpoint = $this->determineEndpoint($payload);
        $url = $this->baseUrl . $endpoint;

        $this->logger->debug("Sending HTTP request", ['url' => $url, 'payload' => $payload]);

        // Add admin secret to request
        $headers = [
            'Content-Type' => 'application/json',
        ];

        $adminSecret = $this->config->get('janus.admin_secret');
        if ($adminSecret) {
            $payload['admin_secret'] = $adminSecret;
        }

        // Send POST request
        $this->httpClient->post($url, $headers, json_encode($payload))
            ->then(
                function ($response) use ($deferred, $transaction) {
                    $body = (string) $response->getBody();
                    $data = json_decode($body, true);

                    if ($data === null) {
                        $deferred->reject(new InvalidJsonException("Invalid JSON response"));
                        return;
                    }

                    $this->logger->debug("Received HTTP response", ['data' => $data]);

                    // Check for errors
                    if (isset($data['janus']) && $data['janus'] === 'error') {
                        $error = $data['error']['reason'] ?? 'Unknown error';
                        $deferred->reject(new \RuntimeException("Janus error: {$error}"));
                        return;
                    }

                    $deferred->resolve($data);
                },
                function (\Exception $e) use ($deferred) {
                    $this->logger->error("HTTP request failed: " . $e->getMessage());
                    $deferred->reject($e);
                }
            );

        return $deferred->promise();
    }

    /**
     * Determine the API endpoint based on the request
     * 
     * @param array $payload Request payload
     * @return string Endpoint path
     */
    private function determineEndpoint(array $payload): string
    {
        $janus = $payload['janus'] ?? '';

        switch ($janus) {
            case 'info':
                return '/info';
            case 'list_sessions':
                return '/sessions';
            case 'list_handles':
                return '/handles/' . ($payload['session_id'] ?? '');
            case 'handle_info':
                return '/handle/' . ($payload['session_id'] ?? '') . '/' . ($payload['handle_id'] ?? '');
            case 'message_plugin':
                return '/plugin';
            case 'set_log_level':
            case 'get_log_level':
                return '/loglevel';
            case 'start_pcap':
            case 'stop_pcap':
                return '/pcap';
            default:
                return '';
        }
    }

    /**
     * Register an event handler
     * 
     * @param callable $handler Event handler callback
     * @return void
     */
    public function onEvent(callable $handler): void
    {
        $this->eventHandlers[] = $handler;
        // Note: HTTP transport doesn't support push events by default
        // Would need WebSocket or polling for real-time events
        $this->logger->warning("HTTP transport does not support push events");
    }

    /**
     * Check if transport is connected
     * 
     * @return bool
     */
    public function isConnected(): bool
    {
        return $this->connected;
    }

    /**
     * Generate a unique transaction ID
     * 
     * @return string
     */
    private function generateTransaction(): string
    {
        return uniqid('terra_http_', true);
    }
}
