<?php

namespace Milcomp\CFD1\D1;

use Illuminate\Database\Schema\Grammars\SQLiteGrammar;
use Illuminate\Support\Str;

class D1SchemaGrammar extends SQLiteGrammar
{
    /**
     * Compile the query to check if a table exists.
     *
     * @param string $table
     * @return string
     */
    public function compileTableExists($table): string
    {
        return Str::of(parent::compileTableExists($table))
            ->replace('sqlite_master', 'sqlite_schema')
            ->__toString();
    }

    /**
     * Compile the query to drop all tables.
     *
     * @return string
     */
    public function compileDropAllTables(): string
    {
        return "SELECT 'DROP TABLE IF EXISTS \"' || name || '\";' AS tables FROM sqlite_schema WHERE type = 'table' AND name NOT LIKE 'sqlite_%'";
    }

    /**
     * Compile the query to drop all views.
     *
     * @return string
     */
    public function compileDropAllViews(): string
    {
        return "SELECT 'DROP VIEW IF EXISTS \"' || name || '\";' AS views FROM sqlite_schema WHERE type = 'view' AND name NOT LIKE 'sqlite_%'";
    }

    /**
     * Compile the query to drop all triggers.
     *
     * @return string
     */
    public function compileDropAllTriggers(): string
    {
        return "SELECT 'DROP TRIGGER IF EXISTS \"' || name || '\";' AS triggers FROM sqlite_schema WHERE type = 'trigger'";
    }

    /**
     * Compile the SQL to create a statement.
     *
     * @param string $name
     * @param string $type
     * @return string
     */
    public function compileSqlCreateStatement($name, $type = 'table'): string
    {
        return Str::of(parent::compileSqlCreateStatement($name, $type))
            ->replace('sqlite_master', 'sqlite_schema')
            ->__toString();
    }

    /**
     * Compile the query to retrieve views.
     *
     * @return string
     */
    public function compileViews(): string
    {
        return "SELECT name FROM sqlite_schema WHERE type = 'view' AND name NOT LIKE 'sqlite_%'";
    }

    /**
     * Compile the query to retrieve tables.
     *
     * @param bool $withSize
     * @return string
     */
    public function compileTables($withSize = false): string
    {
        $query = "SELECT name FROM sqlite_schema WHERE type = 'table' AND name NOT LIKE 'sqlite_%'";

        if ($withSize) {
            $query .= " ORDER BY (SELECT SUM(pgsize) FROM dbstat WHERE name = sqlite_schema.name) DESC";
        }

        return $query;
    }
}
