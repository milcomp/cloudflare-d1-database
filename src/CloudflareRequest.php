<?php

namespace Milcomp\CFD1;

use Saloon\Http\Connector;
use Saloon\Http\Request;

abstract class CloudflareRequest extends Request
{

    public function __construct(
        protected CloudflareD1Connector $connector,
    ) {}

    protected function resolveConnector(): Connector
    {
        return $this->connector;
    }
}
