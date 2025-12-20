<?php

namespace Terra\Transport;

use React\Promise\Promise;

/**
 * Transport Interface
 * 
 * Defines common interface for all transport implementations
 * (ZeroMQ, HTTP, UnixSocket)
 */
interface TransportInterface
{
    /**
     * Connect to Janus Gateway
     * 
     * @return Promise
     */
    public function connect(): Promise;

    /**
     * Send a request to Janus Gateway
     * 
     * @param array $payload Request payload
     * @param float|null $timeout Timeout in seconds
     * @return Promise
     */
    public function sendRequest(array $payload, ?float $timeout = null): Promise;

    /**
     * Register an event handler
     * 
     * @param callable $handler Event handler callback
     * @return void
     */
    public function onEvent(callable $handler): void;

    /**
     * Disconnect from Janus Gateway
     * 
     * @return void
     */
    public function disconnect(): void;

    /**
     * Check if connected
     * 
     * @return bool
     */
    public function isConnected(): bool;
}
