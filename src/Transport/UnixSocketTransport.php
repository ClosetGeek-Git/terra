<?php

namespace Terra\Transport;

use React\EventLoop\LoopInterface;
use React\Promise\Promise;
use React\Promise\Deferred;
use Terra\Config\ConfigManager;
use Terra\Logger\Logger;
use Terra\Transport\Socket\SeqpacketSocket;
use Terra\Exception\ConnectionException;
use Terra\Exception\InvalidJsonException;
use Terra\Exception\TimeoutException;

/**
 * UnixSocket Transport with SOCK_SEQPACKET support
 * 
 * Handles asynchronous communication with Janus Gateway over Unix Domain Sockets
 * Uses SOCK_SEQPACKET for reliable, sequenced packet delivery with fallback mechanisms
 */
class UnixSocketTransport implements TransportInterface
{
    /**
     * @var LoopInterface ReactPHP event loop
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
     * @var SeqpacketSocket|null Unix socket instance
     */
    private $socket = null;

    /**
     * @var bool Connection status
     */
    private $connected = false;

    /**
     * @var array Event handlers
     */
    private $eventHandlers = [];

    /**
     * @var array Pending requests waiting for responses
     */
    private $pendingRequests = [];

    /**
     * @var string Buffer for incomplete messages
     */
    private $buffer = '';

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
            // Check if running on Windows
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $this->logger->error("UnixSocket transport not supported on Windows");
                $deferred->reject(new ConnectionException("UnixSocket transport not supported on Windows"));
                return $deferred->promise();
            }

            $socketPath = $this->config->get('janus.unix_socket_path', '/var/run/janus/janus-admin.sock');
            $this->logger->info("Connecting to Janus Gateway via UnixSocket", ['path' => $socketPath]);

            // Create SOCK_SEQPACKET socket using our shim
            $this->socket = SeqpacketSocket::create($socketPath, $this->loop);
            
            // Log socket information
            $socketInfo = $this->socket->getInfo();
            $this->logger->info("UnixSocket created", $socketInfo);

            // Set up read handler
            $this->socket->on('data', function ($data) {
                $this->handleData($data);
            });

            $this->connected = true;
            $this->logger->info("Connected to Janus Gateway via UnixSocket", [
                'socket_type' => $this->socket->getSocketType()
            ]);
            $deferred->resolve(true);

        } catch (\Exception $e) {
            $this->logger->error("Failed to connect to Janus Gateway", ['error' => $e->getMessage()]);
            $deferred->reject(new ConnectionException("Connection failed: " . $e->getMessage()));
        }

        return $deferred->promise();
    }

    /**
     * Handle incoming data
     * 
     * @param string $data Raw data
     * @return void
     */
    private function handleData(string $data): void
    {
        if ($data === '' || $data === false) {
            return;
        }

        $this->buffer .= $data;
        $this->processBuffer();
    }

    /**
     * Process buffered data and extract complete JSON messages
     * 
     * @return void
     */
    private function processBuffer(): void
    {
        // Try to find complete JSON objects in the buffer
        while (strlen($this->buffer) > 0) {
            // Try to decode from the start
            $decoded = json_decode($this->buffer, true);
            
            if (json_last_error() === JSON_ERROR_NONE) {
                // Complete JSON found
                $this->handleMessage($decoded);
                $this->buffer = '';
                break;
            }

            // Try to find JSON object boundaries
            $bracketCount = 0;
            $inString = false;
            $escape = false;
            $messageEnd = -1;

            for ($i = 0; $i < strlen($this->buffer); $i++) {
                $char = $this->buffer[$i];

                if ($escape) {
                    $escape = false;
                    continue;
                }

                if ($char === '\\') {
                    $escape = true;
                    continue;
                }

                if ($char === '"') {
                    $inString = !$inString;
                    continue;
                }

                if (!$inString) {
                    if ($char === '{') {
                        $bracketCount++;
                    } elseif ($char === '}') {
                        $bracketCount--;
                        if ($bracketCount === 0) {
                            $messageEnd = $i + 1;
                            break;
                        }
                    }
                }
            }

            if ($messageEnd > 0) {
                $jsonStr = substr($this->buffer, 0, $messageEnd);
                $decoded = json_decode($jsonStr, true);
                
                if (json_last_error() === JSON_ERROR_NONE) {
                    $this->handleMessage($decoded);
                    $this->buffer = substr($this->buffer, $messageEnd);
                } else {
                    // Invalid JSON, discard
                    $this->logger->warning("Invalid JSON in buffer", ['json' => substr($jsonStr, 0, 100)]);
                    $this->buffer = substr($this->buffer, $messageEnd);
                }
            } else {
                // No complete message yet, wait for more data
                break;
            }
        }
    }

    /**
     * Handle a complete message
     * 
     * @param array $data Message data
     * @return void
     */
    private function handleMessage(array $data): void
    {
        $this->logger->debug("Received message via UnixSocket", ['data' => $data]);

        // Check if this is a response to a pending request
        if (isset($data['transaction']) && isset($this->pendingRequests[$data['transaction']])) {
            $this->handleResponse($data);
        } else {
            // Handle as event
            $this->handleEvent($data);
        }
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

        // Add admin secret if configured
        if ($this->config->has('janus.admin_secret') && !isset($payload['admin_secret'])) {
            $payload['admin_secret'] = $this->config->get('janus.admin_secret');
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
                $deferred->reject(new TimeoutException("Request timeout after {$timeout} seconds"));
            }
        });

        // Store timer for cleanup
        $this->pendingRequests[$transaction]['timer'] = $timer;

        try {
            $json = json_encode($payload);
            $this->logger->debug("Sending request via UnixSocket", ['payload' => $json]);
            
            $result = $this->socket->send($json);
            
            if ($result === false) {
                unset($this->pendingRequests[$transaction]);
                $this->loop->cancelTimer($timer);
                $deferred->reject(new ConnectionException("Failed to write to socket"));
            }

        } catch (\Exception $e) {
            unset($this->pendingRequests[$transaction]);
            $this->loop->cancelTimer($timer);
            $deferred->reject(new InvalidJsonException("Failed to encode JSON: " . $e->getMessage()));
        }

        return $deferred->promise();
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
        $this->logger->debug("Received event via UnixSocket", ['data' => $data]);

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
        $this->logger->info("Disconnected from Janus Gateway via UnixSocket");
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
        return uniqid('terra_unix_', true);
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
