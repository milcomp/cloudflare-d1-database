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
        // Disable foreign key constraints
        $this->connection->statement('PRAGMA foreign_keys = OFF');

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

        // Drop all tables
        $dropTableStatements = $this->connection->select(
            $this->grammar->compileDropAllTables()
        );

        foreach ($dropTableStatements as $statement) {
            $sql = is_object($statement) ? $statement->tables : $statement['tables'];
            $this->connection->statement($sql);
        }

        $this->connection->statement('PRAGMA foreign_keys = ON');
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
