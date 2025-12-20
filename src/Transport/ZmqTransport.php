<?php

namespace Terra\Transport;

use React\EventLoop\LoopInterface;
use React\ZMQ\Context as ZMQContext;
use React\Promise\Promise;
use React\Promise\Deferred;
use Terra\Config\ConfigManager;
use Terra\Logger\Logger;
use Terra\Exception\ConnectionException;
use Terra\Exception\InvalidJsonException;
use Terra\Exception\TimeoutException;
use ZMQ;

/**
 * ZeroMQ Transport for Janus Admin API
 * 
 * Handles asynchronous communication with Janus Gateway over ZeroMQ
 */
class ZmqTransport implements TransportInterface
{
    /**
     * @var LoopInterface ReactPHP event loop
     */
    private $loop;

    /**
     * @var ZMQContext ZMQ context
     */
    private $context;

    /**
     * @var \React\ZMQ\SocketWrapper ZMQ socket
     */
    private $socket;

    /**
     * @var ConfigManager Configuration manager
     */
    private $config;

    /**
     * @var Logger Logger instance
     */
    private $logger;

    /**
     * @var array Pending requests waiting for responses
     */
    private $pendingRequests = [];

    /**
     * @var bool Connection status
     */
    private $connected = false;

    /**
     * @var array Event handlers
     */
    private $eventHandlers = [];

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
        $this->context = new ZMQContext($loop);
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
            $address = $this->config->get('janus.admin_address');
            $this->logger->info("Connecting to Janus Gateway", ['address' => $address]);

            // Create REQ socket for request-reply pattern
            $this->socket = $this->context->getSocket(ZMQ::SOCKET_DEALER);
            $this->socket->connect($address);

            // Set up message handler
            $this->socket->on('messages', function ($messages) {
                $this->handleMessages($messages);
            });

            $this->connected = true;
            $this->logger->info("Connected to Janus Gateway");
            $deferred->resolve(true);

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

        if (!$this->connected) {
            $deferred->reject(new ConnectionException("Not connected to Janus Gateway"));
            return $deferred->promise();
        }

        // Add transaction ID if not present
        if (!isset($payload['transaction'])) {
            $payload['transaction'] = $this->generateTransactionId();
        }

        $transaction = $payload['transaction'];
        $timeout = $timeout ?? $this->config->get('janus.timeout', 30);

        // Store pending request
        $this->pendingRequests[$transaction] = [
            'deferred' => $deferred,
            'payload' => $payload,
            'timestamp' => microtime(true),
        ];

        // Set timeout
        $timer = $this->loop->addTimer($timeout, function () use ($transaction, $deferred) {
            if (isset($this->pendingRequests[$transaction])) {
                unset($this->pendingRequests[$transaction]);
                $this->logger->warning("Request timeout", ['transaction' => $transaction]);
                $deferred->reject(new TimeoutException("Request timeout after {$this->config->get('janus.timeout')} seconds"));
            }
        });

        // Store timer for cleanup
        $this->pendingRequests[$transaction]['timer'] = $timer;

        try {
            $json = json_encode($payload);
            $this->logger->debug("Sending request", ['payload' => $json]);
            $this->socket->send($json);
        } catch (\Exception $e) {
            unset($this->pendingRequests[$transaction]);
            $this->loop->cancelTimer($timer);
            $deferred->reject(new InvalidJsonException("Failed to encode JSON: " . $e->getMessage()));
        }

        return $deferred->promise();
    }

    /**
     * Handle incoming messages from Janus Gateway
     * 
     * @param array $messages Raw messages
     * @return void
     */
    private function handleMessages(array $messages): void
    {
        foreach ($messages as $message) {
            try {
                $data = json_decode($message, true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->logger->error("Invalid JSON received", ['error' => json_last_error_msg()]);
                    continue;
                }

                $this->logger->debug("Received message", ['data' => $data]);

                // Check if this is a response to a pending request
                if (isset($data['transaction']) && isset($this->pendingRequests[$data['transaction']])) {
                    $this->handleResponse($data);
                } else {
                    // Handle as event
                    $this->handleEvent($data);
                }

            } catch (\Exception $e) {
                $this->logger->error("Error handling message", ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Handle response to a pending request
     * 
     * @param array $data Response data
     * @return void
     */
    private function handleResponse(array $data): void
    {
        $transaction = $data['transaction'];
        $request = $this->pendingRequests[$transaction];

        // Cancel timeout timer
        if (isset($request['timer'])) {
            $this->loop->cancelTimer($request['timer']);
        }

        // Resolve the promise
        if (isset($data['janus']) && $data['janus'] === 'error') {
            $error = $data['error'] ?? ['code' => 0, 'reason' => 'Unknown error'];
            $this->logger->error("Request failed", ['transaction' => $transaction, 'error' => $error]);
            $request['deferred']->reject(new \RuntimeException($error['reason'], $error['code']));
        } else {
            $request['deferred']->resolve($data);
        }

        unset($this->pendingRequests[$transaction]);
    }

    /**
     * Handle event from Janus Gateway
     * 
     * @param array $data Event data
     * @return void
     */
    private function handleEvent(array $data): void
    {
        $this->logger->debug("Received event", ['data' => $data]);

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
        if ($this->socket) {
            $this->socket->close();
            $this->socket = null;
        }

        $this->connected = false;
        $this->logger->info("Disconnected from Janus Gateway");
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
        return uniqid('terra_', true);
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
