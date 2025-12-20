<?php

namespace Terra\Transport;

use React\EventLoop\LoopInterface;
use React\Http\Browser;
use React\Promise\Promise;
use React\Promise\Deferred;
use Terra\Config\ConfigManager;
use Terra\Logger\Logger;
use Terra\Exception\ConnectionException;
use Terra\Exception\InvalidJsonException;
use Terra\Exception\TimeoutException;

/**
 * HTTP Restful Transport for Janus Admin API
 * 
 * Handles asynchronous communication with Janus Gateway over HTTP
 * Supports long polling for events
 */
class HttpTransport implements TransportInterface
{
    /**
     * @var LoopInterface ReactPHP event loop
     */
    private $loop;

    /**
     * @var Browser HTTP client
     */
    private $browser;

    /**
     * @var ConfigManager Configuration manager
     */
    private $config;

    /**
     * @var Logger Logger instance
     */
    private $logger;

    /**
     * @var bool Connection status
     */
    private $connected = false;

    /**
     * @var array Event handlers
     */
    private $eventHandlers = [];

    /**
     * @var bool Long polling active
     */
    private $longPollingActive = false;

    /**
     * @var string Session token
     */
    private $sessionToken = null;

    /**
     * Constructor
     * 
     * @param LoopInterface $loop ReactPHP event loop
     * @param ConfigManager $config Configuration manager
     * @param Logger $logger Logger instance
     */
    public function __construct(LoopInterface $loop, ConfigManager $config, Logger $logger)
    {
        $this->loop = $loop;
        $this->config = $config;
        $this->logger = $logger;
        $this->browser = new Browser($loop);
    }

    /**
     * Connect to Janus Gateway
     * 
     * @return Promise
     */
    public function connect(): Promise
    {
        $deferred = new Deferred();

        try {
            $baseUrl = $this->config->get('janus.http_address', 'http://localhost:7088/admin');
            $this->logger->info("Connecting to Janus Gateway via HTTP", ['url' => $baseUrl]);

            // Test connection with a simple info request
            $this->sendRequest(['janus' => 'info'])->then(
                function ($response) use ($deferred) {
                    $this->connected = true;
                    $this->logger->info("Connected to Janus Gateway via HTTP");
                    
                    // Start long polling for events if enabled
                    if ($this->config->get('http.long_polling', true)) {
                        $this->startLongPolling();
                    }
                    
                    $deferred->resolve(true);
                },
                function ($error) use ($deferred) {
                    $this->logger->error("Failed to connect via HTTP", ['error' => $error->getMessage()]);
                    $deferred->reject(new ConnectionException("HTTP connection failed: " . $error->getMessage()));
                }
            );

        } catch (\Exception $e) {
            $this->logger->error("Failed to connect to Janus Gateway", ['error' => $e->getMessage()]);
            $deferred->reject(new ConnectionException("Connection failed: " . $e->getMessage()));
        }

        return $deferred->promise();
    }

    /**
     * Send a request to Janus Gateway
     * 
     * @param array $payload Request payload
     * @param float|null $timeout Timeout in seconds
     * @return Promise
     */
    public function sendRequest(array $payload, ?float $timeout = null): Promise
    {
        $deferred = new Deferred();

        if (!$this->connected && (!isset($payload['janus']) || $payload['janus'] !== 'info')) {
            $deferred->reject(new ConnectionException("Not connected to Janus Gateway"));
            return $deferred->promise();
        }

        // Add admin secret if configured
        if ($this->config->has('janus.admin_secret') && !isset($payload['admin_secret'])) {
            $payload['admin_secret'] = $this->config->get('janus.admin_secret');
        }

        // Add transaction ID if not present
        if (!isset($payload['transaction'])) {
            $payload['transaction'] = $this->generateTransactionId();
        }

        $baseUrl = $this->config->get('janus.http_address', 'http://localhost:7088/admin');
        $timeout = $timeout ?? $this->config->get('janus.timeout', 30);

        $this->logger->debug("Sending HTTP request", ['payload' => $payload]);

        // Send POST request
        $this->browser
            ->withTimeout($timeout)
            ->post(
                $baseUrl,
                [
                    'Content-Type' => 'application/json',
                ],
                json_encode($payload)
            )
            ->then(
                function ($response) use ($deferred, $payload) {
                    $body = (string) $response->getBody();
                    $this->logger->debug("Received HTTP response", ['body' => $body]);

                    try {
                        $data = json_decode($body, true);
                        
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            throw new InvalidJsonException("Invalid JSON response: " . json_last_error_msg());
                        }

                        if (isset($data['janus']) && $data['janus'] === 'error') {
                            $error = $data['error'] ?? ['code' => 0, 'reason' => 'Unknown error'];
                            $this->logger->error("Request failed", ['transaction' => $payload['transaction'], 'error' => $error]);
                            $deferred->reject(new \RuntimeException($error['reason'], $error['code']));
                        } else {
                            $deferred->resolve($data);
                        }

                    } catch (\Exception $e) {
                        $deferred->reject($e);
                    }
                },
                function ($error) use ($deferred) {
                    $this->logger->error("HTTP request failed", ['error' => $error->getMessage()]);
                    
                    if ($error instanceof \React\Promise\Timer\TimeoutException) {
                        $deferred->reject(new TimeoutException("Request timeout"));
                    } else {
                        $deferred->reject(new ConnectionException("HTTP request failed: " . $error->getMessage()));
                    }
                }
            );

        return $deferred->promise();
    }

    /**
     * Start long polling for events
     * 
     * @return void
     */
    private function startLongPolling(): void
    {
        if ($this->longPollingActive) {
            return;
        }

        $this->longPollingActive = true;
        $this->pollForEvents();
    }

    /**
     * Poll for events from Janus Gateway
     * 
     * @return void
     */
    private function pollForEvents(): void
    {
        if (!$this->longPollingActive || !$this->connected) {
            return;
        }

        $eventUrl = $this->config->get('janus.http_event_address', null);
        
        if ($eventUrl === null) {
            // Events not configured, schedule next poll
            $this->loop->addTimer(1, function() {
                $this->pollForEvents();
            });
            return;
        }

        $timeout = $this->config->get('http.long_poll_timeout', 30);

        $this->browser
            ->withTimeout($timeout + 5) // Add buffer to timeout
            ->get($eventUrl . '?maxev=1&rid=' . time())
            ->then(
                function ($response) {
                    $body = (string) $response->getBody();
                    
                    try {
                        $data = json_decode($body, true);
                        
                        if (json_last_error() === JSON_ERROR_NONE && !empty($data)) {
                            // Process events
                            $events = is_array($data) ? $data : [$data];
                            foreach ($events as $event) {
                                $this->handleEvent($event);
                            }
                        }
                    } catch (\Exception $e) {
                        $this->logger->debug("Error processing event", ['error' => $e->getMessage()]);
                    }

                    // Continue polling
                    $this->pollForEvents();
                },
                function ($error) {
                    $this->logger->debug("Event polling error", ['error' => $error->getMessage()]);
                    
                    // Retry after delay
                    $this->loop->addTimer(1, function() {
                        $this->pollForEvents();
                    });
                }
            );
    }

    /**
     * Handle event from Janus Gateway
     * 
     * @param array $data Event data
     * @return void
     */
    private function handleEvent(array $data): void
    {
        $this->logger->debug("Received event via HTTP", ['data' => $data]);

        // Trigger event handlers
        foreach ($this->eventHandlers as $handler) {
            try {
                call_user_func($handler, $data);
            } catch (\Exception $e) {
                $this->logger->error("Error in event handler", ['error' => $e->getMessage()]);
            }
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
    }

    /**
     * Disconnect from Janus Gateway
     * 
     * @return void
     */
    public function disconnect(): void
    {
        $this->longPollingActive = false;
        $this->connected = false;
        $this->logger->info("Disconnected from Janus Gateway via HTTP");
    }

    /**
     * Check if connected
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
    private function generateTransactionId(): string
    {
        return uniqid('terra_http_', true);
    }

    /**
     * Get the event loop
     * 
     * @return LoopInterface
     */
    public function getLoop(): LoopInterface
    {
        return $this->loop;
    }
}
