<?php

namespace Terra\Transport;

use React\EventLoop\LoopInterface;
use Terra\Config\ConfigManager;
use Terra\Logger\Logger;
use Terra\Exception\ConnectionException;

/**
 * Transport Factory
 * 
 * Creates appropriate transport implementation based on configuration
 */
class TransportFactory
{
    /**
     * Create a transport instance based on configuration
     * 
     * @param LoopInterface $loop Event loop
     * @param ConfigManager $config Configuration
     * @param Logger $logger Logger instance
     * @return TransportInterface
     * @throws ConnectionException
     */
    public static function create(LoopInterface $loop, ConfigManager $config, Logger $logger): TransportInterface
    {
        $transportType = $config->get('janus.transport', 'zmq');

        switch (strtolower($transportType)) {
            case 'zmq':
            case 'zeromq':
                $logger->info("Creating ZMQ transport");
                return new ZmqTransport($loop, $config, $logger);

            case 'http':
            case 'restful':
            case 'rest':
                $logger->info("Creating HTTP transport");
                return new HttpTransport($loop, $config, $logger);

            case 'unix':
            case 'unixsocket':
            case 'socket':
                $logger->info("Creating UnixSocket transport");
                return new UnixSocketTransport($loop, $config, $logger);

            default:
                $logger->error("Unknown transport type", ['type' => $transportType]);
                throw new ConnectionException("Unknown transport type: " . $transportType);
        }
    }

    /**
     * Get list of available transports
     * 
     * @return array
     */
    public static function getAvailableTransports(): array
    {
        $transports = ['zmq', 'http'];

        // Unix socket only available on non-Windows systems
        if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            $transports[] = 'unix';
        }

        return $transports;
    }
}
