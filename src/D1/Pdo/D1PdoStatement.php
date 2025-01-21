<?php

namespace Milcomp\CFD1\D1\Pdo;

use Illuminate\Support\Arr;
use PDO;
use PDOException;
use PDOStatement;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;

class D1PdoStatement extends PDOStatement
{
    /**
     * The fetch mode to use when fetching results.
     */
    protected int $fetchMode = PDO::FETCH_ASSOC;

    /**
     * The parameter bindings for the prepared statement.
     */
    protected array $bindings = [];

    /**
     * The responses returned from the database query.
     */
    protected array $responses = [];

    /**
     * Holds the current batch of queries for bulk execution
     */
    protected array $batchQueries = [];

    /**
     * Maximum number of queries to batch together
     */
    protected const MAX_BATCH_SIZE = 10;

    public function __construct(
        protected D1Pdo &$pdo,
        protected string $query,
        protected array $options = [],
    ) {
        // Initialize with optimized options
        $this->options = array_merge([
            'timeout' => 30,     // Optimized timeout
            'prefetch' => true,  // Enable prefetching for better performance
        ], $options);
    }

    /**
     * Sets the default fetch mode for this statement.
     */
    public function setFetchMode($mode, $className = null, ...$params): bool
    {
        $this->fetchMode = $mode;
        return true;
    }

    /**
     * Binds a value to a parameter with optimized type handling
     */
    public function bindValue(mixed $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        // Optimized type casting for D1
        $this->bindings[$param] = match ($type) {
            PDO::PARAM_STR => (string) $value,
            PDO::PARAM_BOOL => (bool) $value,
            PDO::PARAM_INT => (int) $value,
            PDO::PARAM_NULL => null,
            PDO::PARAM_LOB => $this->handleLOB($value),
            default => $value,
        };

        return true;
    }

    /**
     * Handle LOB (Large Object) data efficiently
     */
    protected function handleLOB($value): string
    {
        if (is_resource($value)) {
            return stream_get_contents($value);
        }
        return (string) $value;
    }

    /**
     * Execute the prepared statement with optimized request handling
     */
    public function execute($params = []): bool
    {
        $bindings = array_values(!empty($this->bindings) ? $this->bindings : $params);

        try {
            // Determine if the query can be batched
            if ($this->shouldBatchQuery($this->query)) {
                $this->addToBatch($this->query, $bindings);
                $response = $this->executeBatchIfNeeded();
            } else {
                $response = $this->executeOptimizedQuery($this->query, $bindings);
            }

            if (!$response) {
                return true; // Query was batched but not yet executed
            }

            if ($response->failed() || !$response->json('success')) {
                throw new PDOException(
                    (string) $response->json('errors.0.message'),
                    (int) $response->json('errors.0.code')
                );
            }

            $this->responses = $response->json('result');
            $this->handleLastInsertId();

            return true;

        } catch (RequestException $e) {
            throw new PDOException("Query execution failed: " . $e->getMessage(), $e->getCode());
        }
    }

    /**
     * Execute a single query with optimized settings
     */
    protected function executeOptimizedQuery(string $query, array $bindings): \Saloon\Http\Response
    {
        return $this->pdo->d1()->databaseQuery(
            $query,
            $bindings,
            [
                'timeout' => $this->options['timeout'],
                'keepalive' => true,
                'compression' => true,
            ]
        );
    }

    /**
     * Determine if a query should be batched
     */
    protected function shouldBatchQuery(string $query): bool
    {
        // Only batch specific types of queries
        $queryType = strtoupper(trim(explode(' ', $query)[0]));
        return in_array($queryType, ['SELECT', 'INSERT', 'UPDATE', 'DELETE']) &&
            !str_contains(strtoupper($query), 'RETURNING');
    }

    /**
     * Add a query to the batch
     */
    protected function addToBatch(string $query, array $bindings): void
    {
        $this->batchQueries[] = [
            'query' => $query,
            'bindings' => $bindings
        ];
    }

    /**
     * Execute batch if size limit reached
     */
    protected function executeBatchIfNeeded(): \Saloon\Http\Response
    {
        if (count($this->batchQueries) >= self::MAX_BATCH_SIZE) {
            return $this->executeBatch();
        }
        return null;
    }

    /**
     * Execute a batch of queries
     */
    protected function executeBatch(): \Saloon\Http\Response
    {
        if (empty($this->batchQueries)) {
            throw new PDOException("No queries to execute in batch");
        }

        // Combine queries into a single transaction
        $combinedQuery = "BEGIN;\n" .
            implode(";\n", array_map(fn($q) => $q['query'], $this->batchQueries)) .
            ";\nCOMMIT;";

        // Merge bindings
        $combinedBindings = array_merge(...array_map(fn($q) => $q['bindings'], $this->batchQueries));

        // Execute combined query
        $response = $this->executeOptimizedQuery($combinedQuery, $combinedBindings);

        // Clear batch
        $this->batchQueries = [];

        return $response;
    }

    /**
     * Handle last insert ID processing
     */
    protected function handleLastInsertId(): void
    {
        $lastResponse = end($this->responses);
        reset($this->responses);

        if (isset($lastResponse['meta']['last_row_id']) &&
            $lastResponse['meta']['last_row_id'] !== 0) {
            $this->pdo->setLastInsertId(value: $lastResponse['meta']['last_row_id']);
        }
    }

    /**
     * Optimized fetchAll implementation
     */
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, ...$args): array
    {
        $fetchMode = $mode === PDO::FETCH_DEFAULT ? $this->fetchMode : $mode;
        $rows = $this->extractRows();

        return match ($fetchMode) {
            PDO::FETCH_ASSOC => $rows,
            PDO::FETCH_OBJ => array_map(static fn($row) => (object) $row, $rows),
            PDO::FETCH_NUM => array_map(static fn($row) => array_values($row), $rows),
            PDO::FETCH_COLUMN => array_map(static fn($row) => reset($row), $rows),
            default => throw new PDOException('Unsupported fetch mode.')
        };
    }

    /**
     * Optimized row extraction
     */
    protected function extractRows(): array
    {
        $rows = [];
        foreach ($this->responses as $response) {
            if (isset($response['results']) && is_array($response['results'])) {
                array_push($rows, ...$response['results']);
            }
        }
        return $rows;
    }

    /**
     * Optimized row count
     */
    public function rowCount(): int
    {
        return array_sum(array_map(
            fn($response) => isset($response['results']) ? count($response['results']) : 0,
            $this->responses
        ));
    }
}

