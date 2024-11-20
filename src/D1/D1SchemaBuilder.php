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

    /**
     * Drop all tables from the database.
     *
     * @return void
     */
    public function dropAllTables(): void
    {
        $dropTableStatements = $this->connection->select(
            $this->grammar->compileDropAllTables()
        );

        foreach ($dropTableStatements as $statement) {
            $sql = is_object($statement) ? $statement->drop_statement : $statement['drop_statement'];
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
        $dropViewStatements = $this->connection->select(
            $this->grammar->compileDropAllViews()
        );

        foreach ($dropViewStatements as $statement) {
            $sql = is_object($statement) ? $statement->drop_statement : $statement['drop_statement'];
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
