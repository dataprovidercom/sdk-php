<?php

namespace Dataprovider\SDK\Response;

class ApiResponse
{
    private ?string $body;
    private ?int $statusCode;

    public function __construct(?string $body, ?int $statusCode)
    {
        $this->body = $body;
        $this->statusCode = $statusCode;
    }

    /**
     * Returns the raw response body.
     *
     * @return string|null The raw response body.
     */
    public function getBody(): ?string
    {
        return $this->body;
    }

    /**
     * Returns the decoded response body.
     *
     * @param bool $associative
     * @return mixed
     */
    public function getJsonBody(bool $associative = true): mixed
    {
        return json_decode($this->body, $associative);
    }

    /**
     * Returns the HTTP status code of the response.
     *
     * @return int|null The status code.
     */
    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }
}