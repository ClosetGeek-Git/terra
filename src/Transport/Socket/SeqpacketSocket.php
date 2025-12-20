<?php

namespace Terra\Transport\Socket;

use React\EventLoop\LoopInterface;

/**
 * SOCK_SEQPACKET Shim for ReactPHP
 * 
 * Provides SOCK_SEQPACKET support for Unix Domain Sockets
 * with fallback mechanisms for compatibility
 */
class SeqpacketSocket
{
    /**
     * @var resource|object Socket resource (resource in PHP 7.x, Socket object in PHP 8.x)
     */
    private $socket;

    /**
     * @var LoopInterface Event loop
     */
    private $loop;

    /**
     * @var string Socket type used
     */
    private $socketType;

    /**
     * @var callable[] Read callbacks
     */
    private $readCallbacks = [];

    /**
     * @var callable[] Error callbacks
     */
    private $errorCallbacks = [];

    /**
     * @var bool Is socket connected
     */
    private $connected = false;

    /**
     * Socket type constants
     */
    const TYPE_SEQPACKET = 'SOCK_SEQPACKET';
    const TYPE_DGRAM = 'SOCK_DGRAM';
    const TYPE_STREAM = 'STREAM';

    /**
     * Create a SEQPACKET socket
     * 
     * @param string $socketPath Path to Unix socket
     * @param LoopInterface $loop Event loop
     * @return SeqpacketSocket
     * @throws \RuntimeException
     */
    public static function create(string $socketPath, LoopInterface $loop): self
    {
        $instance = new self($loop);
        $instance->connect($socketPath);
        return $instance;
    }

    /**
     * Constructor
     * 
     * @param LoopInterface $loop Event loop
     */
    public function __construct(LoopInterface $loop)
    {
        $this->loop = $loop;
    }

    /**
     * Connect to Unix socket
     * 
     * @param string $socketPath Path to Unix socket
     * @return void
     * @throws \RuntimeException
     */
    public function connect(string $socketPath): void
    {
        // Verify socket file exists
        if (!file_exists($socketPath)) {
            throw new \RuntimeException("Socket file does not exist: {$socketPath}");
        }

        // Try SOCK_SEQPACKET first (native support)
        if (defined('SOCK_SEQPACKET')) {
            $this->socket = $this->trySeqpacket($socketPath);
            if ($this->socket !== false) {
                $this->socketType = self::TYPE_SEQPACKET;
                $this->connected = true;
                return;
            }
        }

        // Fallback to SOCK_DGRAM (similar semantics)
        if (defined('SOCK_DGRAM')) {
            $this->socket = $this->tryDgram($socketPath);
            if ($this->socket !== false) {
                $this->socketType = self::TYPE_DGRAM;
                $this->connected = true;
                return;
            }
        }

        // Final fallback to stream socket
        $this->socket = $this->tryStream($socketPath);
        if ($this->socket !== false) {
            $this->socketType = self::TYPE_STREAM;
            $this->connected = true;
            return;
        }

        throw new \RuntimeException("Failed to create Unix socket connection to: {$socketPath}");
    }

    /**
     * Try to create SOCK_SEQPACKET socket
     * 
     * @param string $socketPath Socket path
     * @return resource|object|false Socket resource/object or false
     */
    private function trySeqpacket(string $socketPath)
    {
        if (!function_exists('socket_create')) {
            return false;
        }

        $socket = @socket_create(AF_UNIX, SOCK_SEQPACKET, 0);
        if ($socket === false) {
            return false;
        }

        // Set socket options for better compatibility
        @socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 0, 'usec' => 0]);
        @socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => 0, 'usec' => 0]);
        @socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);

        if (@socket_connect($socket, $socketPath)) {
            // Set non-blocking mode
            @socket_set_nonblock($socket);
            return $socket;
        }

        @socket_close($socket);
        return false;
    }

    /**
     * Try to create SOCK_DGRAM socket
     * 
     * @param string $socketPath Socket path
     * @return resource|object|false Socket resource/object or false
     */
    private function tryDgram(string $socketPath)
    {
        if (!function_exists('socket_create')) {
            return false;
        }

        $socket = @socket_create(AF_UNIX, SOCK_DGRAM, 0);
        if ($socket === false) {
            return false;
        }

        // Set socket options
        @socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 0, 'usec' => 0]);
        @socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => 0, 'usec' => 0]);

        if (@socket_connect($socket, $socketPath)) {
            @socket_set_nonblock($socket);
            return $socket;
        }

        @socket_close($socket);
        return false;
    }

    /**
     * Try to create stream socket
     * 
     * @param string $socketPath Socket path
     * @return resource|false
     */
    private function tryStream(string $socketPath)
    {
        $socket = @stream_socket_client(
            'unix://' . $socketPath,
            $errno,
            $errstr,
            30,
            STREAM_CLIENT_CONNECT
        );

        if ($socket === false) {
            return false;
        }

        stream_set_blocking($socket, false);
        return $socket;
    }

    /**
     * Send data to socket
     * 
     * @param string $data Data to send
     * @return int|false Bytes sent or false on error
     */
    public function send(string $data)
    {
        if (!$this->connected) {
            return false;
        }

        if ($this->socketType === self::TYPE_STREAM) {
            return @fwrite($this->socket, $data);
        } else {
            // Handle both PHP 7.x resource and PHP 8.x Socket object
            return @socket_send($this->socket, $data, strlen($data), 0);
        }
    }

    /**
     * Receive data from socket
     * 
     * @param int $length Maximum bytes to read
     * @return string|false Data received or false on error
     */
    public function recv(int $length = 8192)
    {
        if (!$this->connected) {
            return false;
        }

        if ($this->socketType === self::TYPE_STREAM) {
            return @fread($this->socket, $length);
        } else {
            $data = '';
            $result = @socket_recv($this->socket, $data, $length, 0);
            return $result === false ? false : $data;
        }
    }

    /**
     * Register read callback with event loop
     * 
     * @param callable $callback Read callback
     * @return void
     */
    public function on(string $event, callable $callback): void
    {
        if ($event === 'data' || $event === 'read') {
            $this->readCallbacks[] = $callback;
            
            // Register with event loop if this is the first callback
            if (count($this->readCallbacks) === 1) {
                $this->loop->addReadStream($this->socket, function ($stream) {
                    $data = $this->recv();
                    if ($data !== false && $data !== '') {
                        foreach ($this->readCallbacks as $cb) {
                            call_user_func($cb, $data, $this);
                        }
                    }
                });
            }
        } elseif ($event === 'error') {
            $this->errorCallbacks[] = $callback;
        }
    }

    /**
     * Remove read stream from loop
     * 
     * @return void
     */
    public function removeReadStream(): void
    {
        if ($this->socket) {
            $this->loop->removeReadStream($this->socket);
        }
    }

    /**
     * Close socket
     * 
     * @return void
     */
    public function close(): void
    {
        if ($this->socket) {
            $this->removeReadStream();
            
            if ($this->socketType === self::TYPE_STREAM) {
                @fclose($this->socket);
            } else {
                @socket_close($this->socket);
            }
            
            $this->socket = null;
            $this->connected = false;
        }
    }

    /**
     * Get socket resource
     * 
     * @return resource|object|null Socket resource (resource in PHP 7.x, Socket object in PHP 8.x)
     */
    public function getResource()
    {
        return $this->socket;
    }

    /**
     * Get socket type used
     * 
     * @return string
     */
    public function getSocketType(): string
    {
        return $this->socketType;
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
     * Get socket information
     * 
     * @return array
     */
    public function getInfo(): array
    {
        $info = [
            'type' => $this->socketType,
            'connected' => $this->connected,
        ];

        if ($this->socket && $this->socketType !== self::TYPE_STREAM) {
            @socket_getpeername($this->socket, $peer);
            $info['peer'] = $peer ?? 'unknown';
        }

        return $info;
    }
}
