<?php

namespace Dataprovider\SDK\Client;

class CurlClient
{
    private $ch;

    public function __construct()
    {
        $this->init();
    }

    private function init(): void
    {
        $this->ch = curl_init();

        $this
            ->setOption(CURLOPT_RETURNTRANSFER, true)
            ->setOption(CURLOPT_USERAGENT, 'Dataprovider.com - SDK (PHP)')
            ->setOption(CURLOPT_TIMEOUT, 60);
    }

    public function setUrl(string $url): self
    {
        return $this->setOption(CURLOPT_URL, $url);
    }

    public function setRequestMethod(string $requestMethod): self
    {
        return $this->setOption(CURLOPT_CUSTOMREQUEST, $requestMethod);
    }

    public function setRequestHeaders(array $requestHeaders): self
    {
        $headers = [];

        foreach ($requestHeaders as $name => $values) {
            $value = implode(';', (array)$values);
            $headers[] = "$name: $value";
        }

        return $this->setOption(CURLOPT_HTTPHEADER, array_values($headers));
    }

    public function setBody(?array $body): self
    {
        if (!empty($body)) {
            return $this->setOption(CURLOPT_POSTFIELDS, json_encode($body));
        }

        return $this;
    }

    private function setOption(int $option, $value): self
    {
        curl_setopt($this->ch, $option, $value);

        return $this;
    }
    
    public function send(): array
    {
        $responseBody = curl_exec($this->ch);

        if (curl_errno($this->ch)) {
            throw new \RuntimeException('Curl error: ' . curl_errno($this->ch) . ' ' . curl_error($this->ch));
        }

        $response = [
            'body' => $responseBody,
            'statusCode' => curl_getinfo($this->ch)['http_code'] ?? 0
        ];

        curl_close($this->ch);

        return $response;
    }

    public function throwForStatus(array $response): void {
        if ($response['statusCode'] >= 400 && $response['statusCode'] < 500) {
            throw new \RuntimeException("{$response['statusCode']} Client Error: {$response['body']}", $response['statusCode']);
        } else if ($response['statusCode'] >= 500 && $response['statusCode'] < 600) {
            throw new \RuntimeException("{$response['statusCode']} Server Error: {$response['body']}", $response['statusCode']);
        }
    }
}