<?php

namespace Milcomp\CFD1\D1;

use Illuminate\Database\Schema\Grammars\SQLiteGrammar;
use Illuminate\Support\Str;

class D1SchemaGrammar extends SQLiteGrammar
{
    public function compileTableExists($table): string
    {
        return Str::of(parent::compileTableExists($table))
            ->__toString();
    }

    public function compileDropAllTables(): string
    {
        return Str::of(parent::compileDropAllTables())
            ->__toString();
    }

    public function compileDropAllViews(): string
    {
        return Str::of(parent::compileDropAllViews())
            ->__toString();
    }

    public function compileSqlCreateStatement($name, $type = 'table'): string
    {
        return Str::of(parent::compileSqlCreateStatement($name, $type))
            ->__toString();
    }

    public function compileViews(): string
    {
        return Str::of(parent::compileViews())
            ->__toString();
    }

    public function compileTables($withSize = false): string
    {
        return Str::of(parent::compileTables($withSize))
            ->__toString();
    }
}
