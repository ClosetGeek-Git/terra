<?php

namespace Terra\Transport;

use React\Promise\Promise;

/**
 * Transport interface for Janus Gateway communication
 * 
 * Provides a common interface for different transport implementations
 * (ZeroMQ, Unix Sockets, HTTP/REST)
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
     * Disconnect from Janus Gateway
     * 
     * @return void
     */
    public function disconnect(): void;

    /**
     * Send a request to Janus Gateway
     * 
     * @param array $payload Request payload
     * @return Promise
     */
    public function sendRequest(array $payload): Promise;

    /**
     * Register an event handler
     * 
     * @param callable $handler Event handler callback
     * @return void
     */
    public function onEvent(callable $handler): void;

    /**
     * Check if transport is connected
     * 
     * @return bool
     */
    public function isConnected(): bool;
}
