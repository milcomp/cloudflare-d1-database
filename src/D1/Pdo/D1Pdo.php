<?php

namespace Milcomp\CFD1\D1\Pdo;

use Milcomp\CFD1\CloudflareD1Connector;
use PDO;
use PDOStatement;

/**
 * Class D1Pdo
 *
 * Extends the PDO class to interact with the Cloudflare D1 database.
 */
class D1Pdo extends PDO
{
    /**
     * Stores the last insert IDs for different sequences.
     *
     * @var array<string, mixed>
     */
    protected array $lastInsertIds = [];

    /**
     * Indicates whether a transaction is currently active.
     *
     * @var bool
     */
    protected bool $inTransaction = false;

    /**
     * D1Pdo constructor.
     *
     * Initializes a PDO instance for Cloudflare D1 database interactions.
     *
     * @param string $dsn The Data Source Name.
     * @param CloudflareD1Connector $connector The Cloudflare D1 Connector instance.
     */
    public function __construct(
        protected string $dsn,
        protected CloudflareD1Connector $connector,
    ) {
        // Initialize parent PDO with in-memory SQLite database for compatibility.
        parent::__construct('sqlite::memory:');
    }

    /**
     * Prepares a statement for execution and returns a statement object.
     *
     * @param string $query The SQL statement to prepare.
     * @param array $options Optional array of driver-specific options.
     * @return PDOStatement|false Returns a PDOStatement object, or false on failure.
     */
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return new D1PdoStatement(
            $this,
            $query,
            $options,
        );
    }

    /**
     * Returns the Cloudflare D1 Connector instance.
     *
     * @return CloudflareD1Connector
     */
    public function d1(): CloudflareD1Connector
    {
        return $this->connector;
    }

    /**
     * Sets the last insert ID for a given sequence name.
     *
     * @param string|null $name The name of the sequence object (defaults to 'id' if null).
     * @param mixed $value The last insert ID value.
     * @return void
     */
    public function setLastInsertId(?string $name = null, mixed $value = null): void
    {
        if ($name === null) {
            $name = 'id';
        }

        $this->lastInsertIds[$name] = $value;
    }

    /**
     * Returns the ID of the last inserted row or sequence value.
     *
     * @param string|null $name Name of the sequence object from which the ID should be returned.
     * @return string|false Returns the ID as a string, or false if not set.
     */
    public function lastInsertId(?string $name = null): string|false
    {
        if ($name === null) {
            $name = 'id';
        }

        return $this->lastInsertIds[$name] ?? false;
    }

    /**
     * Initiates a transaction.
     *
     * @return bool True on success or false on failure.
     */
    public function beginTransaction(): bool
    {
        $this->inTransaction = true;
        return true;
    }

    /**
     * Commits a transaction.
     *
     * @return bool True on success or false on failure.
     */
    public function commit(): bool
    {
        $this->inTransaction = false;
        return true;
    }

    /**
     * Checks if a transaction is currently active.
     *
     * @return bool True if a transaction is active, false otherwise.
     */
    public function inTransaction(): bool
    {
        return $this->inTransaction;
    }

    public function rollBack(): bool
    {
        // If you want to be extra cautious:
        if (! $this->inTransaction) {
            // Or return false, though Laravel might interpret false as "failed to roll back"
            return true;
        }

        $this->inTransaction = false;
        return true;
    }
}
