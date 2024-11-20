<?php

namespace Milcomp\CFD1\D1;

use Illuminate\Database\SQLiteConnection;
use Milcomp\CFD1\CloudflareD1Connector;
use Milcomp\CFD1\D1\Pdo\D1Pdo;

class D1Connection extends SQLiteConnection
{
    public function __construct(
        protected CloudflareD1Connector $connector,
        protected $config = [],
    ) {
        parent::__construct(
            new D1Pdo('sqlite::memory:', $this->connector),
            $config['database'] ?? '',
            $config['prefix'] ?? '',
            $config,
        );
    }

    public function getDriverTitle(): string
    {
        return 'D1';
    }

    protected function getDefaultSchemaGrammar()
    {
        ($grammar = new D1SchemaGrammar())->setConnection($this);

        return $this->withTablePrefix($grammar);
    }

    protected function getDefaultQueryGrammar()
    {
        ($grammar = new D1SchemaGrammar())->setConnection($this);

        return $this->withTablePrefix(new $grammar);
    }

    public function getSchemaGrammar()
    {
        return $this->withTablePrefix(new D1SchemaGrammar());
    }

    public function getSchemaBuilder(): D1SchemaBuilder
    {
        return new D1SchemaBuilder($this);
    }

    public function d1(): CloudflareD1Connector
    {
        return $this->connector;
    }
}
