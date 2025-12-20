<?php

namespace Terra\Transport;

use React\EventLoop\LoopInterface;
use React\Promise\Promise;
use React\Promise\Deferred;
use Terra\Config\ConfigManager;
use Terra\Logger\Logger;
use Terra\Exception\ConnectionException;
use Terra\Exception\InvalidJsonException;
use Terra\Exception\TimeoutException;

/**
 * UnixSocket Transport with SOCK_SEQPACKET support
 * 
 * Handles asynchronous communication with Janus Gateway over Unix Domain Sockets
 * Uses SOCK_SEQPACKET for reliable, sequenced packet delivery
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
     * @var resource|null Unix socket resource
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
     * @var object|null Read stream
     */
    private $readStream = null;

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

            // Check if socket file exists
            if (!file_exists($socketPath)) {
                throw new ConnectionException("Socket file does not exist: " . $socketPath);
            }

            // Create SOCK_SEQPACKET socket
            $this->socket = $this->createSeqpacketSocket($socketPath);
            
            if ($this->socket === false) {
                throw new ConnectionException("Failed to create SOCK_SEQPACKET socket");
            }

            // Set non-blocking mode
            stream_set_blocking($this->socket, false);

            // Add socket to event loop for reading
            $this->setupReadStream();

            $this->connected = true;
            $this->logger->info("Connected to Janus Gateway via UnixSocket");
            $deferred->resolve(true);

        } catch (\Exception $e) {
            $this->logger->error("Failed to connect to Janus Gateway", ['error' => $e->getMessage()]);
            $deferred->reject(new ConnectionException("Connection failed: " . $e->getMessage()));
        }

        return $deferred->promise();
    }

    /**
     * Create a SOCK_SEQPACKET socket
     * 
     * @param string $socketPath Path to Unix socket
     * @return resource|false Socket resource or false on failure
     */
    private function createSeqpacketSocket(string $socketPath)
    {
        // Try to create SOCK_SEQPACKET socket using socket_create
        if (function_exists('socket_create')) {
            $socket = @socket_create(AF_UNIX, SOCK_SEQPACKET, 0);
            
            if ($socket !== false) {
                if (@socket_connect($socket, $socketPath)) {
                    $this->logger->debug("Created SOCK_SEQPACKET socket using socket_create");
                    return $socket;
                }
                socket_close($socket);
            }
        }

        // Fallback: try SOCK_DGRAM (datagram sockets)
        if (function_exists('socket_create')) {
            $socket = @socket_create(AF_UNIX, SOCK_DGRAM, 0);
            
            if ($socket !== false) {
                if (@socket_connect($socket, $socketPath)) {
                    $this->logger->info("Fallback to SOCK_DGRAM socket");
                    return $socket;
                }
                socket_close($socket);
            }
        }

        // Final fallback: try stream socket
        $socket = @stream_socket_client('unix://' . $socketPath, $errno, $errstr, 30);
        
        if ($socket !== false) {
            $this->logger->info("Fallback to stream socket");
            return $socket;
        }

        $this->logger->error("All socket creation methods failed", [
            'errno' => $errno ?? 'N/A',
            'error' => $errstr ?? 'N/A'
        ]);

        return false;
    }

    /**
     * Setup read stream for socket
     * 
     * @return void
     */
    private function setupReadStream(): void
    {
        $this->loop->addReadStream($this->socket, function ($socket) {
            $this->handleRead($socket);
        });
    }

    /**
     * Handle read event on socket
     * 
     * @param resource $socket Socket resource
     * @return void
     */
    private function handleRead($socket): void
    {
        try {
            // Read data from socket
            $data = $this->readFromSocket($socket);
            
            if ($data === false || $data === '') {
                // Connection closed or error
                if (feof($socket)) {
                    $this->logger->warning("Socket connection closed by peer");
                    $this->disconnect();
                }
                return;
            }

            $this->buffer .= $data;

            // Try to extract complete JSON messages
            $this->processBuffer();

        } catch (\Exception $e) {
            $this->logger->error("Error reading from socket", ['error' => $e->getMessage()]);
        }
    }

    /**
     * Read data from socket (handles different socket types)
     * 
     * @param resource $socket Socket resource
     * @return string|false Data read or false on error
     */
    private function readFromSocket($socket)
    {
        if (is_resource($socket) && get_resource_type($socket) === 'Socket') {
            // PHP socket extension
            $data = '';
            $result = @socket_recv($socket, $data, 8192, 0);
            return $result === false ? false : $data;
        } else {
            // Stream socket
            return @fread($socket, 8192);
        }
    }

    /**
     * Write data to socket (handles different socket types)
     * 
     * @param resource $socket Socket resource
     * @param string $data Data to write
     * @return int|false Bytes written or false on error
     */
    private function writeToSocket($socket, string $data)
    {
        if (is_resource($socket) && get_resource_type($socket) === 'Socket') {
            // PHP socket extension
            return @socket_send($socket, $data, strlen($data), 0);
        } else {
            // Stream socket
            return @fwrite($socket, $data);
        }
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
            
            $result = $this->writeToSocket($this->socket, $json);
            
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
            $this->loop->removeReadStream($this->socket);
            
            if (is_resource($this->socket) && get_resource_type($this->socket) === 'Socket') {
                socket_close($this->socket);
            } else {
                fclose($this->socket);
            }
            
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
