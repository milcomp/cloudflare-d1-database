<?php

namespace Milcomp\CFD1;

use Saloon\Http\Auth\TokenAuthenticator;
use Saloon\Http\Connector;
use Saloon\Http\Response;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Traits\Body\HasJsonBody;
use Saloon\Traits\Plugins\AcceptsJson;
use Saloon\Contracts\Body\HasBody;
use Saloon\Http\PendingRequest;
use Saloon\Enums\Method;
use Saloon\Http\Pool;
use Illuminate\Support\Collection;

class CloudflareD1Connector extends Connector
{
    use AcceptsJson;
    use HasJsonBody;

    /**
     * @var array Connection pool
     */
    protected static array $connectionPool = [];

    /**
     * @var int Maximum pool size
     */
    protected int $maxPoolSize = 10;

    /**
     * @var int Network timeout in seconds
     */
    protected int $timeout = 30;

    /**
     * @var int Maximum packet size
     */
    protected int $maxPacketSize = 1048576; // 1MB

    /**
     * @var bool Keep-alive setting
     */
    protected bool $keepAlive = true;

    /**
     * @var array Active requests tracking
     */
    protected array $activeRequests = [];

    public function __construct(
        #[\SensitiveParameter] public ?string $database = null,
        #[\SensitiveParameter] protected ?string $token = null,
        #[\SensitiveParameter] public ?string $accountId = null,
        public string $apiUrl = 'https://api.cloudflare.com/client/v4',
        array $config = []
    ) {
        $this->maxPoolSize = $config['pool_size'] ?? 10;
        $this->timeout = $config['timeout'] ?? 30;
        $this->maxPacketSize = $config['max_packet_size'] ?? 1048576;
        $this->keepAlive = $config['keep_alive'] ?? true;

        $this->initializePool();
    }

    /**
     * Initialize the connection pool
     */
    protected function initializePool(): void
    {
        for ($i = count(static::$connectionPool); $i < $this->maxPoolSize; $i++) {
            static::$connectionPool[] = [
                'connection' => clone $this,
                'in_use' => false,
                'last_used' => microtime(true)
            ];
        }
    }

    /**
     * Get a connection from the pool
     */
    protected function getPooledConnection(): self
    {
        foreach (static::$connectionPool as &$poolItem) {
            if (!$poolItem['in_use']) {
                $poolItem['in_use'] = true;
                $poolItem['last_used'] = microtime(true);
                return $poolItem['connection'];
            }
        }

        // If all connections are in use, create a temporary one
        return clone $this;
    }

    /**
     * Release a connection back to the pool
     */
    protected function releaseConnection(self $connection): void
    {
        foreach (static::$connectionPool as &$poolItem) {
            if ($poolItem['connection'] === $connection) {
                $poolItem['in_use'] = false;
                break;
            }
        }
    }

    /**
     * Execute an optimized database query
     */
    public function databaseQuery(string $query, array $params = [], array $options = []): Response
    {
        $connection = $this->getPooledConnection();

        try {
            $request = new D1\Requests\D1QueryRequest(
                $connection,
                $this->database,
                $query,
                $params
            );

            $request->headers()->add('CF-D1-Max-Packet-Size', $this->maxPacketSize);
            $request->headers()->add('Connection', $this->keepAlive ? 'keep-alive' : 'close');

            // Track the request
            $requestId = uniqid('req_', true);
            $this->activeRequests[$requestId] = [
                'query' => $query,
                'start_time' => microtime(true)
            ];

            $response = $connection->send($request);

            // Update request tracking
            $this->activeRequests[$requestId]['end_time'] = microtime(true);
            $this->activeRequests[$requestId]['duration'] =
                $this->activeRequests[$requestId]['end_time'] -
                $this->activeRequests[$requestId]['start_time'];

            return $response;
        } finally {
            $this->releaseConnection($connection);
        }
    }

    /**
     * Execute multiple queries in parallel using the connection pool
     */
    public function executeBatch(array $queries): Collection
    {
        $pool = Pool::build()
            ->withMaxConcurrency($this->maxPoolSize)
            ->withRequestsFromIterable(
                array_map(
                    fn($q) => new D1\Requests\D1QueryRequest(
                        $this,
                        $this->database,
                        $q['query'],
                        $q['params'] ?? []
                    ),
                    $queries
                )
            );

        return $this->sendPool($pool);
    }

    protected function defaultAuth(): TokenAuthenticator
    {
        return new TokenAuthenticator($this->token);
    }

    public function resolveBaseUrl(): string
    {
        return $this->apiUrl;
    }

    protected function defaultHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => 'CFD1-Connector/1.0',
        ];
    }

    /**
     * Configure request for optimal performance
     */
    public function beforeSend(PendingRequest $request): void
    {
        // Add performance headers
        $request->withHeaders([
            'CF-D1-Max-Packet-Size' => $this->maxPacketSize,
            'Connection' => $this->keepAlive ? 'keep-alive' : 'close',
            'CF-D1-No-Rate-Limit' => 'true', // Request no rate limiting
        ]);

        // Set timeout
        $request->timeout($this->timeout);

        // Enable compression if supported
        $request->withHeader('Accept-Encoding', 'gzip, deflate');
    }

    public function setTimeout(int $timeout): self
    {
        $this->timeout = $timeout;
        return $this;
    }

    public function setMaxPacketSize(int $size): self
    {
        $this->maxPacketSize = $size;
        return $this;
    }

    public function setKeepAlive(bool $keepAlive): self
    {
        $this->keepAlive = $keepAlive;
        return $this;
    }

    /**
     * Get query performance statistics
     */
    public function getQueryStats(): array
    {
        $stats = [];
        foreach ($this->activeRequests as $requestId => $request) {
            if (isset($request['duration'])) {
                $stats[] = [
                    'query' => $request['query'],
                    'duration' => $request['duration'],
                    'timestamp' => $request['start_time']
                ];
            }
        }
        return $stats;
    }
}
