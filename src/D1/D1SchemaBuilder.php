<?php

namespace Milcomp\CFD1\D1;

use Illuminate\Database\Schema\SQLiteBuilder;

class D1SchemaBuilder extends SQLiteBuilder
{
    /**
     * Create a database in the schema.
     *
     * @param  string  $name
     * @return bool
     */
    public function createDatabase($name): bool
    {
        // Cloudflare D1 is a serverless database service.
        // Database creation is managed by Cloudflare, so we'll override this method.
        return true;
    }

    /**
     * Get all tables and their foreign key dependencies.
     *
     * @return array
     */
    protected function getTableDependencies(): array
    {
        $tables = $this->connection->select(
            "SELECT name FROM sqlite_schema WHERE type = 'table' AND name NOT LIKE 'sqlite_%' AND name NOT LIKE '_cf%'"
        );

        $dependencies = [];

        foreach ($tables as $tableObj) {
            $table = is_object($tableObj) ? $tableObj->name : $tableObj['name'];
            $foreignKeys = $this->connection->select(
                "PRAGMA foreign_key_list({$table})"
            );

            $dependencies[$table] = array_map(function ($fk) {
                return $fk['table'];
            }, $foreignKeys);
        }

        return $dependencies;
    }


    /**
     * Perform a topological sort on the table dependencies.
     *
     * @param array $dependencies
     * @return array
     */
    protected function topologicalSort(array $dependencies): array
    {
        $sorted = [];
        $visited = [];

        $visit = function ($table) use (&$visit, &$dependencies, &$sorted, &$visited) {
            if (isset($visited[$table])) {
                return;
            }

            $visited[$table] = true;

            if (isset($dependencies[$table])) {
                foreach ($dependencies[$table] as $dep) {
                    $visit($dep);
                }
            }

            $sorted[] = $table;
        };

        foreach (array_keys($dependencies) as $table) {
            $visit($table);
        }

        return array_reverse($sorted);
    }

    /**
     * Drop a database from the schema if it exists.
     *
     * @param  string  $name
     * @return bool
     */
    public function dropDatabaseIfExists($name): bool
    {
        // Dropping databases is managed by Cloudflare D1, so we'll override this method.
        return true;
    }

    public function dropAllTables(): void
    {
        // Drop all triggers
        $dropTriggerStatements = $this->connection->select(
            $this->grammar->compileDropAllTriggers()
        );

        foreach ($dropTriggerStatements as $statement) {
            $sql = is_object($statement) ? $statement->triggers : $statement['triggers'];
            $this->connection->statement($sql);
        }

        // Drop all views
        $dropViewStatements = $this->connection->select(
            $this->grammar->compileDropAllViews()
        );

        foreach ($dropViewStatements as $statement) {
            $sql = is_object($statement) ? $statement->views : $statement['views'];
            $this->connection->statement($sql);
        }

        // Get table dependencies
        $dependencies = $this->getTableDependencies();

        // Get sorted table list
        $tablesToDrop = $this->topologicalSort($dependencies);

        // Drop tables in order
        foreach ($tablesToDrop as $table) {
            $sql = "DROP TABLE IF EXISTS \"{$table}\";";
            $this->connection->statement($sql);
        }
    }

    /**
     * Drop all views from the database.
     *
     * @return void
     */
    public function dropAllViews(): void
    {
        // Drop all views
        $dropViewStatements = $this->connection->select(
            $this->grammar->compileDropAllViews()
        );

        foreach ($dropViewStatements as $statement) {
            $sql = is_object($statement) ? $statement->views : $statement['views'];
            $this->connection->statement($sql);
        }
    }

    /**
     * Set the busy timeout.
     *
     * @param  int  $milliseconds
     * @return bool
     */
    public function setBusyTimeout($milliseconds): bool
    {
        // Not applicable in Cloudflare D1; override to disable.
        return true;
    }

    /**
     * Set the journal mode.
     *
     * @param  string  $mode
     * @return bool
     */
    public function setJournalMode($mode): bool
    {
        // Not applicable in Cloudflare D1; override to disable.
        return true;
    }

    /**
     * Set the synchronous mode.
     *
     * @param  int  $mode
     * @return bool
     */
    public function setSynchronous($mode): bool
    {
        // Not applicable in Cloudflare D1; override to disable.
        return true;
    }

    /**
     * Empty the database file.
     *
     * @return void
     */
    public function refreshDatabaseFile()
    {
        // Not applicable in Cloudflare D1; override to disable.
        return;
    }
}
