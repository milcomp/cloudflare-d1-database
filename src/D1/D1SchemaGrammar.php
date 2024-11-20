<?php

namespace Milcomp\CFD1\D1;

use Illuminate\Database\Schema\Grammars\SQLiteGrammar;
use Illuminate\Support\Str;

class D1SchemaGrammar extends SQLiteGrammar
{
    public function compileTableExists($table): string
    {
        return Str::of(parent::compileTableExists($table))
            ->replace('sqlite_master', 'sqlite_schema')
            ->__toString();
    }

    public function compileDropAllTables(): string
    {
        return Str::of(parent::compileDropAllTables())
            ->replace('sqlite_master', 'sqlite_schema')
            ->__toString();
    }

    public function compileDropAllViews(): string
    {
        return Str::of(parent::compileDropAllViews())
            ->replace('sqlite_master', 'sqlite_schema')
            ->__toString();
    }

    public function compileSqlCreateStatement($name, $type = 'table'): string
    {
        return Str::of(parent::compileSqlCreateStatement($name, $type))
            ->replace('sqlite_master', 'sqlite_schema')
            ->__toString();
    }

    public function compileViews(): string
    {
        return Str::of(parent::compileViews())
            ->replace('sqlite_master', 'sqlite_schema')
            ->__toString();
    }

    public function compileTables($withSize = false): string
    {
        return Str::of(parent::compileTables($withSize))
            ->replace('sqlite_master', 'sqlite_schema')
            ->__toString();
    }
}
