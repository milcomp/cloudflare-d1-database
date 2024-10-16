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
     *
     * @var int
     */
    protected int $fetchMode = PDO::FETCH_ASSOC;

    /**
     * The parameter bindings for the prepared statement.
     *
     * @var array<int|string, mixed>
     */
    protected array $bindings = [];

    /**
     * The responses returned from the database query.
     *
     * @var array
     */
    protected array $responses = [];

    public function __construct(
        protected D1Pdo &$pdo,
        protected string $query,
        protected array $options = [],
    ) {
        //
    }

    /**
     * Sets the default fetch mode for this statement.
     *
     * @param int $mode The fetch mode must be one of the PDO::FETCH_* constants.
     * @return bool True on success, false on failure.
     */
    public function setFetchMode($mode, $className = null, ...$params): bool
    {
        $this->fetchMode = $mode;

        return true;
    }

    /**
     * Binds a value to a parameter.
     *
     * @param mixed $param The parameter identifier.
     * @param mixed $value The value to bind to the parameter.
     * @param int $type Explicit data type for the parameter using the PDO::PARAM_* constants.
     * @return bool True on success or false on failure.
     */
    public function bindValue(mixed $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        $this->bindings[$param] = match ($type) {
            PDO::PARAM_STR  => (string) $value,
            PDO::PARAM_BOOL => (bool) $value,
            PDO::PARAM_INT  => (int) $value,
            PDO::PARAM_NULL => null,
            default         => $value,
        };

        return true;
    }

    /**
     * @throws FatalRequestException
     * @throws RequestException
     * @throws \JsonException
     * @throws PDOException if the query fails.
     */
    public function execute($params = []): bool
    {
        $bindings = array_values(! empty($this->bindings) ? $this->bindings : $params);

        $response = $this->pdo->d1()->databaseQuery(
            $this->query,
            $bindings,
        );

        if ($response->failed() || !$response->json('success')) {
            throw new PDOException(
                (string) $response->json('errors.0.message'),
                (int) $response->json('errors.0.code'),
            );
        }

        // Store the result responses.
        $this->responses = $response->json('result');

        // Get the last insert ID if applicable.
        $lastResponse = end($this->responses);
        reset($this->responses);
        $lastId = $lastResponse['meta']['last_row_id'] ?? null;

        if ($lastId !== null && $lastId !== 0) {
            $this->pdo->setLastInsertId(value: $lastId);
        }

        return true;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, ...$args): array
    {
        $fetchMode = $mode === PDO::FETCH_DEFAULT ? $this->fetchMode : $mode;

        return match ($fetchMode) {
            PDO::FETCH_ASSOC => $this->rowsFromResponses(),
            PDO::FETCH_OBJ   => array_map(fn($row) => (object) $row, $this->rowsFromResponses()),
            default => throw new PDOException('Unsupported fetch mode.'),
        };
    }

    /**
     * Returns the number of rows affected by the last SQL statement.
     *
     * @return int The number of rows.
     */
    public function rowCount(): int
    {
        return count($this->rowsFromResponses());
    }

    /**
     * Extracts and combines the result rows from the responses.
     *
     * @return array The combined array of result rows.
     */
    protected function rowsFromResponses(): array
    {
        $rows = [];

        foreach ($this->responses as $response) {
            if (isset($response['results']) && is_array($response['results'])) {
                $rows = array_merge($rows, $response['results']);
            }
        }

        return $rows;
    }
}
