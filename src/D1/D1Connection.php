<?php

namespace Milcomp\CFD1\D1;

use Illuminate\Database\SQLiteConnection;
use Milcomp\CFD1\CloudflareD1Connector;
use Milcomp\CFD1\D1\Pdo\D1Pdo;

class D1Connection extends SQLiteConnection
{
    protected CloudflareD1Connector $connector;
    protected array $config;

    public function __construct(CloudflareD1Connector $connector, array $config = [])
    {
        $this->connector = $connector;
        $this->config = array_merge([
            'pool_size' => 10,
            'timeout' => 30,
            'max_packet_size' => 1048576,
            'keep_alive' => true
        ], $config);

        // Configure the connector with our settings
        $connector->setTimeout($this->config['timeout']);
        $connector->setMaxPacketSize($this->config['max_packet_size']);
        $connector->setKeepAlive($this->config['keep_alive']);

        parent::__construct(
            new D1Pdo('sqlite::memory:', $connector),
            $config['database'] ?? '',
            $config['prefix'] ?? '',
            $config
        );
    }

    public function getDriverTitle(): string
    {
        return 'D1';
    }

    public function d1(): CloudflareD1Connector
    {
        return $this->connector;
    }

    /**
     * Get query performance statistics
     */
    public function getQueryStats(): array
    {
        return $this->connector->getQueryStats();
    }
}

