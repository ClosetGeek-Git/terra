<?php

namespace Terra\Admin;

use React\EventLoop\LoopInterface;
use React\EventLoop\Factory as LoopFactory;
use React\Promise\Promise;
use Terra\Config\ConfigManager;
use Terra\Logger\Logger;
use Terra\Transport\ZmqTransport;

/**
 * Base Admin Client for Janus Gateway
 * 
 * Provides core functionality for interacting with Janus Admin API
 */
class AdminClient
{
    /**
     * @var ZmqTransport Transport layer
     */
    protected $transport;

    /**
     * @var ConfigManager Configuration manager
     */
    protected $config;

    /**
     * @var Logger Logger instance
     */
    protected $logger;

    /**
     * @var LoopInterface Event loop
     */
    protected $loop;

    /**
     * @var array Registered plugin controllers
     */
    protected $plugins = [];

    /**
     * Constructor
     * 
     * @param array $config Configuration array
     * @param LoopInterface|null $loop Optional event loop
     */
    public function __construct(array $config = [], ?LoopInterface $loop = null)
    {
        $this->config = new ConfigManager($config);
        $this->logger = new Logger($this->config);
        $this->loop = $loop ?? LoopFactory::create();
        $this->transport = new ZmqTransport($this->loop, $this->config, $this->logger);
    }

    /**
     * Connect to Janus Gateway
     * 
     * @return Promise
     */
    public function connect(): Promise
    {
        return $this->transport->connect();
    }

    /**
     * Disconnect from Janus Gateway
     * 
     * @return void
     */
    public function disconnect(): void
    {
        $this->transport->disconnect();
    }

    /**
     * Get server information
     * 
     * @return Promise
     */
    public function getInfo(): Promise
    {
        return $this->sendAdminRequest([
            'janus' => 'info',
            'admin_secret' => $this->config->get('janus.admin_secret'),
        ]);
    }

    /**
     * Get list of all sessions
     * 
     * @return Promise
     */
    public function listSessions(): Promise
    {
        return $this->sendAdminRequest([
            'janus' => 'list_sessions',
            'admin_secret' => $this->config->get('janus.admin_secret'),
        ]);
    }

    /**
     * Get handles for a specific session
     * 
     * @param int|string $sessionId Session ID
     * @return Promise
     */
    public function listHandles($sessionId): Promise
    {
        return $this->sendAdminRequest([
            'janus' => 'list_handles',
            'session_id' => $sessionId,
            'admin_secret' => $this->config->get('janus.admin_secret'),
        ]);
    }

    /**
     * Get handle information
     * 
     * @param int|string $sessionId Session ID
     * @param int|string $handleId Handle ID
     * @return Promise
     */
    public function handleInfo($sessionId, $handleId): Promise
    {
        return $this->sendAdminRequest([
            'janus' => 'handle_info',
            'session_id' => $sessionId,
            'handle_id' => $handleId,
            'admin_secret' => $this->config->get('janus.admin_secret'),
        ]);
    }

    /**
     * Start packet capture
     * 
     * @param int|string $handleId Handle ID
     * @param string $folder Output folder
     * @param string $filename Output filename
     * @param int $truncate Truncate size
     * @return Promise
     */
    public function startPcap($handleId, string $folder, string $filename, int $truncate = 0): Promise
    {
        return $this->sendAdminRequest([
            'janus' => 'start_pcap',
            'handle_id' => $handleId,
            'folder' => $folder,
            'filename' => $filename,
            'truncate' => $truncate,
            'admin_secret' => $this->config->get('janus.admin_secret'),
        ]);
    }

    /**
     * Stop packet capture
     * 
     * @param int|string $handleId Handle ID
     * @return Promise
     */
    public function stopPcap($handleId): Promise
    {
        return $this->sendAdminRequest([
            'janus' => 'stop_pcap',
            'handle_id' => $handleId,
            'admin_secret' => $this->config->get('janus.admin_secret'),
        ]);
    }

    /**
     * Set log level
     * 
     * @param string $level Log level (e.g., 'debug', 'info', 'warn', 'error')
     * @return Promise
     */
    public function setLogLevel(string $level): Promise
    {
        $levels = [
            'none' => 0,
            'fatal' => 1,
            'error' => 2,
            'warn' => 3,
            'info' => 4,
            'verb' => 5,
            'debug' => 6,
            'huge' => 7,
        ];

        $levelValue = $levels[strtolower($level)] ?? 4;

        return $this->sendAdminRequest([
            'janus' => 'set_log_level',
            'level' => $levelValue,
            'admin_secret' => $this->config->get('janus.admin_secret'),
        ]);
    }

    /**
     * Get current log level
     * 
     * @return Promise
     */
    public function getLogLevel(): Promise
    {
        return $this->sendAdminRequest([
            'janus' => 'get_log_level',
            'admin_secret' => $this->config->get('janus.admin_secret'),
        ]);
    }

    /**
     * Query specific message statistics
     * 
     * @return Promise
     */
    public function messageStats(): Promise
    {
        return $this->sendAdminRequest([
            'janus' => 'message_plugin',
            'admin_secret' => $this->config->get('janus.admin_secret'),
        ]);
    }

    /**
     * Register an event handler
     * 
     * @param callable $handler Event handler callback
     * @return void
     */
    public function onEvent(callable $handler): void
    {
        $this->transport->onEvent($handler);
    }

    /**
     * Register a plugin controller
     * 
     * @param string $name Plugin name
     * @param object $controller Plugin controller instance
     * @return void
     */
    public function registerPlugin(string $name, object $controller): void
    {
        $this->plugins[$name] = $controller;
    }

    /**
     * Get a registered plugin controller
     * 
     * @param string $name Plugin name
     * @return object|null
     */
    public function plugin(string $name): ?object
    {
        return $this->plugins[$name] ?? null;
    }

    /**
     * Send an admin request
     * 
     * @param array $payload Request payload
     * @return Promise
     */
    public function sendAdminRequest(array $payload): Promise
    {
        return $this->transport->sendRequest($payload);
    }

    /**
     * Run the event loop
     * 
     * @return void
     */
    public function run(): void
    {
        $this->loop->run();
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

    /**
     * Get the logger instance
     * 
     * @return Logger
     */
    public function getLogger(): Logger
    {
        return $this->logger;
    }

    /**
     * Get the configuration manager
     * 
     * @return ConfigManager
     */
    public function getConfig(): ConfigManager
    {
        return $this->config;
    }
}
