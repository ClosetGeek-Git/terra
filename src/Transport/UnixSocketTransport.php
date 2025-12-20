<?php

namespace Terra\Transport;

use React\EventLoop\LoopInterface;
use React\Promise\Promise;
use React\Promise\Deferred;
use React\Socket\Connector;
use React\Socket\ConnectionInterface;
use Terra\Config\ConfigManager;
use Terra\Logger\Logger;
use Terra\Exception\ConnectionException;
use Terra\Exception\TimeoutException;

/**
 * Unix Socket Transport for Janus Gateway
 * 
 * Provides communication with Janus Gateway via Unix domain sockets
 */
class UnixSocketTransport implements TransportInterface
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
     * @var ConnectionInterface|null Socket connection
     */
    private $connection;

    /**
     * @var array Pending requests
     */
    private $pendingRequests = [];

    /**
     * @var array Event handlers
     */
    private $eventHandlers = [];

    /**
     * @var string Buffer for incomplete messages
     */
    private $buffer = '';

    /**
     * @var bool Connection status
     */
    private $connected = false;

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
    }

    /**
     * Connect to Janus Gateway via Unix socket
     * 
     * @return Promise
     */
    public function connect(): Promise
    {
        $deferred = new Deferred();

        $socketPath = $this->config->get('janus.admin_socket', '/tmp/janus_admin.sock');
        $connector = new Connector($this->loop);

        $this->logger->info("Connecting to Janus via Unix socket: {$socketPath}");

        $connector->connect('unix://' . $socketPath)
            ->then(
                function (ConnectionInterface $connection) use ($deferred) {
                    $this->connection = $connection;
                    $this->connected = true;
                    $this->logger->info("Connected to Janus via Unix socket");

                    // Set up data handler
                    $connection->on('data', function ($data) {
                        $this->handleIncomingData($data);
                    });

                    // Set up close handler
                    $connection->on('close', function () {
                        $this->connected = false;
                        $this->logger->info("Unix socket connection closed");
                    });

                    // Set up error handler
                    $connection->on('error', function (\Exception $e) {
                        $this->logger->error("Unix socket error: " . $e->getMessage());
                    });

                    $deferred->resolve();
                },
                function (\Exception $e) use ($deferred, $socketPath) {
                    $this->logger->error("Failed to connect to Unix socket: " . $e->getMessage());
                    $deferred->reject(new ConnectionException(
                        "Failed to connect to Unix socket: {$socketPath}"
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
        if ($this->connection) {
            $this->connection->close();
            $this->connection = null;
            $this->connected = false;
            $this->logger->info("Disconnected from Janus Unix socket");
        }
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

        if (!$this->connected || !$this->connection) {
            $deferred->reject(new ConnectionException("Not connected to Janus Gateway"));
            return $deferred->promise();
        }

        // Generate transaction ID
        $transaction = $this->generateTransaction();
        $payload['transaction'] = $transaction;

        // Store pending request
        $this->pendingRequests[$transaction] = [
            'deferred' => $deferred,
            'timestamp' => time(),
        ];

        // Set up timeout
        $timeout = $this->config->get('janus.timeout', 30);
        $this->loop->addTimer($timeout, function () use ($transaction, $deferred) {
            if (isset($this->pendingRequests[$transaction])) {
                unset($this->pendingRequests[$transaction]);
                $deferred->reject(new TimeoutException("Request timeout after {$timeout} seconds"));
            }
        });

        // Send request
        $jsonData = json_encode($payload);
        $this->logger->debug("Sending request via Unix socket", ['payload' => $payload]);
        $this->connection->write($jsonData . "\n");

        return $deferred->promise();
    }

    /**
     * Handle incoming data from the socket
     * 
     * @param string $data Incoming data
     * @return void
     */
    private function handleIncomingData(string $data): void
    {
        $this->buffer .= $data;

        // Process complete messages (newline-delimited)
        while (($pos = strpos($this->buffer, "\n")) !== false) {
            $message = substr($this->buffer, 0, $pos);
            $this->buffer = substr($this->buffer, $pos + 1);

            if (!empty($message)) {
                $this->processMessage($message);
            }
        }
    }

    /**
     * Process a complete message
     * 
     * @param string $message JSON message
     * @return void
     */
    private function processMessage(string $message): void
    {
        $data = json_decode($message, true);

        if ($data === null) {
            $this->logger->error("Failed to decode JSON message", ['message' => $message]);
            return;
        }

        $this->logger->debug("Received message via Unix socket", ['data' => $data]);

        // Check if this is a response to a pending request
        if (isset($data['transaction']) && isset($this->pendingRequests[$data['transaction']])) {
            $transaction = $data['transaction'];
            $request = $this->pendingRequests[$transaction];
            unset($this->pendingRequests[$transaction]);

            // Resolve the promise
            $request['deferred']->resolve($data);
        }
        // Check if this is an event
        elseif (isset($data['janus']) && $data['janus'] === 'event') {
            $this->dispatchEvent($data);
        }
    }

    /**
     * Dispatch an event to registered handlers
     * 
     * @param array $event Event data
     * @return void
     */
    private function dispatchEvent(array $event): void
    {
        foreach ($this->eventHandlers as $handler) {
            try {
                $handler($event);
            } catch (\Exception $e) {
                $this->logger->error("Event handler error: " . $e->getMessage());
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
        return uniqid('terra_', true);
    }
}
