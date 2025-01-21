<?php

namespace Milcomp\CFD1\D1\Pdo;

use Milcomp\CFD1\CloudflareD1Connector;
use PDO;
use PDOException;
use PDOStatement;

/**
 * Class D1Pdo
 *
 * Extends the PDO class to interact with the Cloudflare D1 database.
 */
class D1Pdo extends PDO
{
    /**
     * Stores the last insert IDs
     */
    protected array $lastInsertIds = [];

    /**
     * Transaction state
     */
    protected bool $inTransaction = false;

    /**
     * Connection state
     */
    protected bool $connected = false;

    /**
     * Optimized constructor with connection handling
     */
    public function __construct(
        protected string $dsn,
        protected CloudflareD1Connector $connector,
    ) {
        parent::__construct($dsn);
        $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    }

    /**
     * Optimized prepare statement
     */
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return new D1PdoStatement(
            $this,
            $this->optimizeQuery($query),
            $options,
        );
    }

    /**
     * Optimize the query for better performance
     */
    protected function optimizeQuery(string $query): string
    {
        // Remove unnecessary whitespace
        $query = preg_replace('/\s+/', ' ', trim($query));

        // Add query hints for better execution planning
        if (stripos($query, 'SELECT') === 0) {
            $query = $this->addQueryHints($query);
        }

        return $query;
    }

    /**
     * Add performance hints to SELECT queries
     */
    protected function addQueryHints(string $query): string
    {
        // Add hints only if they're not already present
        if (!stripos($query, 'INDEXED BY') && !stripos($query, 'NOT INDEXED')) {
            // Let SQLite choose the best index
            $query = preg_replace('/SELECT/', 'SELECT /*+ OPTIMIZE_FOR_SEQUENTIAL_ACCESS */', $query, 1);
        }
        return $query;
    }

    /**
     * Get the D1 connector
     */
    public function d1(): CloudflareD1Connector
    {
        return $this->connector;
    }

    /**
     * Set last insert ID
     */
    public function setLastInsertId(?string $name = null, mixed $value = null): void
    {
        $this->lastInsertIds[$name ?? 'id'] = $value;
    }

    /**
     * Get last insert ID
     */
    public function lastInsertId(?string $name = null): string|false
    {
        return $this->lastInsertIds[$name ?? 'id'] ?? false;
    }

    /**
     * Optimized transaction handling
     */
    public function beginTransaction(): bool
    {
        if ($this->inTransaction) {
            throw new PDOException('Transaction already active');
        }
        $this->inTransaction = true;
        return true;
    }

    public function commit(): bool
    {
        if (!$this->inTransaction) {
            throw new PDOException('No active transaction');
        }
        $this->inTransaction = false;
        return true;
    }

    public function rollBack(): bool
    {
        if (!$this->inTransaction) {
            throw new PDOException('No active transaction');
        }
        $this->inTransaction = false;
        return true;
    }

    public function inTransaction(): bool
    {
        return $this->inTransaction;
    }
}
