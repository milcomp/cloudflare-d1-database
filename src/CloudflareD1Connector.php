<?php

namespace Milcomp\CFD1;

use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Response;

class CloudflareD1Connector extends CloudflareConnector
{
    public function __construct(
        public ?string $database = null,
        protected ?string $token = null,
        public ?string $accountId = null,
        public string $apiUrl = 'https://api.cloudflare.com/client/v4',
    ) {
        parent::__construct($token, $accountId, $apiUrl);
    }

    /**
     * @throws FatalRequestException
     * @throws RequestException
     */
    public function databaseQuery(string $query, array $params): Response
    {
        return $this->send(
            new D1\Requests\D1QueryRequest($this, $this->database, $query, $params),
        );
    }
}
