<?php

use PHPUnit\Framework\TestCase;
use Dataprovider\SDK\Client\ApiClient;
use Dataprovider\SDK\Client\CurlClient;

class ApiClientTest extends TestCase
{
    private function injectCurlResponses(array $responses, ?Exception $statusException = null, array &$requestData = []): CurlClient
    {
        $mockMethods = ['setUrl', 'setRequestMethod', 'setBody', 'setRequestHeaders', 'send'];
        if ($statusException !== null) {
            $mockMethods[] = 'throwForStatus';
        }

        $curl = $this->getMockBuilder(CurlClient::class)
            ->onlyMethods($mockMethods)
            ->getMock();

        $curl->method('setUrl')->willReturnCallback(function ($url) use (&$requestData, $curl) {
            $requestData['urls'][] = $url;
            return $curl;
        });
        $curl->method('setRequestMethod')->willReturnCallback(function ($method) use (&$requestData, $curl) {
            $requestData['methods'][] = $method;
            return $curl;
        });
        $curl->method('setBody')->willReturnCallback(function ($body) use (&$requestData, $curl) {
            $requestData['bodies'][] = $body;
            return $curl;
        });
        $curl->method('setRequestHeaders')->willReturnCallback(function ($headers) use (&$requestData, $curl) {
            $requestData['headers'][] = $headers;
            return $curl;
        });

        $curl->expects($this->exactly(count($responses)))
            ->method('send')
            ->willReturnOnConsecutiveCalls(...$responses);

        if ($statusException !== null) {
            $curl->method('throwForStatus')->willThrowException($statusException);
        }

        return $curl;
    }

    private function clientWithCurl(CurlClient $curl): ApiClient
    {
        return new class('test', 'test', $curl) extends ApiClient {
            private CurlClient $mockCurl;
            public function __construct($u, $p, CurlClient $mockCurl)
            {
                parent::__construct($u, $p);
                $this->mockCurl = $mockCurl;
            }

            protected function getCurlClient(): CurlClient
            {
                return $this->mockCurl;
            }
        };
    }

    public function testRequestsReturnValidResponse(): void
    {
        $requestData = [];
        $curl = $this->injectCurlResponses([
            [
                'statusCode' => 200,
                'body' => json_encode(['access_token' => 'abc', 'refresh_token' => 'xyz'])
            ],
            [
                'statusCode' => 200,
                'body' => json_encode(['type' => 'get'])
            ],
            [
                'statusCode' => 200,
                'body' => json_encode(['type' => 'post'])
            ],
            [
                'statusCode' => 200,
                'body' => json_encode(['type' => 'put'])
            ]
        ], null, $requestData);

        $client = $this->clientWithCurl($curl);

        $get = $client->get('test/get');

        $this->assertEquals('get', $get->getJsonBody()['type']);
        $this->assertEquals('GET', $requestData['methods'][1]);
        $this->assertFalse(strpos($requestData['urls'][1], '?'));
        $this->assertEquals(['Content-Type' => 'application/json', 'Authorization' => 'Bearer abc'], $requestData['headers'][1]);
        $this->assertNull($requestData['bodies'][1]);

        $requestData = [];
        $post = $client->post('test/post', ['test' => 'test'], ['id' => 1]);

        $this->assertEquals('post', $post->getJsonBody()['type']);
        $this->assertEquals('POST', $requestData['methods'][0]);
        $this->assertGreaterThan(0, strpos($requestData['urls'][0], '?test=test'));
        $this->assertEquals(['id' => 1], $requestData['bodies'][0]);

        $requestData = [];
        $put = $client->put('test/put', null, ['id' => 1]);

        $this->assertEquals('put', $put->getJsonBody()['type']);
        $this->assertEquals('PUT', $requestData['methods'][0]);
        $this->assertFalse(strpos($requestData['urls'][0], '?'));
        $this->assertEquals(['id' => 1], $requestData['bodies'][0]);
    }

    public function testTokenRefresh(): void
    {
        $requestData = [];
        $curl = $this->injectCurlResponses([
            [
                'statusCode' => 200,
                'body' => json_encode(['access_token' => 'abc', 'refresh_token' => 'def'])
            ],
            [
                'statusCode' => 401,
                'body' => '{"error":{"message":"Forbidden: Invalid credentials or token.","request_id":"1234-5678"}}'
            ],
            [
                'statusCode' => 200,
                'body' => json_encode(['access_token' => 'ghi', 'refresh_token' => 'jkl'])
            ],
            [
                'statusCode' => 200,
                'body' => json_encode(['status' => 'ok'])
            ]
        ], null, $requestData);

        $client = $this->clientWithCurl($curl);
        $response = $client->post('test');

        $expectedRequestUrls = [
            'https://api.dataprovider.com/v2/auth/oauth2/token',
            'https://api.dataprovider.com/v2/test',
            'https://api.dataprovider.com/v2/auth/oauth2/token',
            'https://api.dataprovider.com/v2/test',
        ];
        $expectedRequestBodies = [
            ['grant_type' => 'password', 'username' => 'test', 'password' => 'test'],
            null,
            ['grant_type' => 'refresh_token', 'refresh_token' => 'def'],
            null
        ];
        $expectedRequestHeaders = [
            ['Content-Type' => 'application/json'],
            ['Content-Type' => 'application/json', 'Authorization' => 'Bearer abc'],
            ['Content-Type' => 'application/json'],
            ['Content-Type' => 'application/json', 'Authorization' => 'Bearer ghi'],
        ];

        $this->assertEquals('ok', $response->getJsonBody()['status']);
        $this->assertEquals($expectedRequestUrls, $requestData['urls']);
        $this->assertEquals($expectedRequestBodies, $requestData['bodies']);
        $this->assertEquals($expectedRequestHeaders, $requestData['headers']);
    }

    public function testAuthRefreshFailureFallsBackToCredentials(): void
    {
        $requestData = [];
        $curl = $this->injectCurlResponses([
            [
                'statusCode' => 200,
                'body' => json_encode(['access_token' => 'abc', 'refresh_token' => 'def'])
            ],
            [
                'statusCode' => 401,
                'body' => '{"error":{"message":"Forbidden: Invalid credentials or token.","request_id":"1234-5678"}}'
            ],
            [
                'statusCode' => 401,
                'body' => '{"error":{"message":"Forbidden: Invalid credentials or token.","request_id":"1234-5678"}}'
            ],
            [
                'statusCode' => 200,
                'body' => json_encode(['access_token' => 'ghi', 'refresh_token' => 'jkl'])
            ],
            [
                'statusCode' => 200,
                'body' => json_encode(['status' => 'ok'])
            ]
        ], null, $requestData);

        $client = $this->clientWithCurl($curl);
        $response = $client->post('test');

        $expectedRequestUrls = [
            'https://api.dataprovider.com/v2/auth/oauth2/token',
            'https://api.dataprovider.com/v2/test',
            'https://api.dataprovider.com/v2/auth/oauth2/token',
            'https://api.dataprovider.com/v2/auth/oauth2/token',
            'https://api.dataprovider.com/v2/test',
        ];
        $expectedRequestBodies = [
            ['grant_type' => 'password', 'username' => 'test', 'password' => 'test'],
            null,
            ['grant_type' => 'refresh_token', 'refresh_token' => 'def'],
            ['grant_type' => 'password', 'username' => 'test', 'password' => 'test'],
            null
        ];
        $expectedRequestHeaders = [
            ['Content-Type' => 'application/json'],
            ['Content-Type' => 'application/json', 'Authorization' => 'Bearer abc'],
            ['Content-Type' => 'application/json'],
            ['Content-Type' => 'application/json'],
            ['Content-Type' => 'application/json', 'Authorization' => 'Bearer ghi'],
        ];

        $this->assertEquals('ok', $response->getJsonBody()['status']);
        $this->assertEquals($expectedRequestUrls, $requestData['urls']);
        $this->assertEquals($expectedRequestBodies, $requestData['bodies']);
        $this->assertEquals($expectedRequestHeaders, $requestData['headers']);
    }

    public function testAuthRefreshFailureFallsBackToCredentialsFail(): void
    {
        $requestData = [];
        $curl = $this->injectCurlResponses([
            [
                'statusCode' => 200,
                'body' => json_encode(['access_token' => 'abc', 'refresh_token' => 'def'])
            ],
            [
                'statusCode' => 401,
                'body' => '{"error":{"message":"Forbidden: Invalid credentials or token.","request_id":"1234-5678"}}'
            ],
            [
                'statusCode' => 401,
                'body' => '{"error":{"message":"Forbidden: Invalid credentials or token.","request_id":"1234-5678"}}'
            ],
            [
                'statusCode' => 401,
                'body' => '{"error":{"message":"Forbidden: Invalid credentials or token.","request_id":"1234-5678"}}'
            ]
        ], null, $requestData);

        $client = $this->clientWithCurl($curl);

        $exception = null;
        try {
            $response = $client->post('test');
        } catch (\RuntimeException $e) {
            $exception = $e;
        }

        $expectedRequestUrls = [
            'https://api.dataprovider.com/v2/auth/oauth2/token',
            'https://api.dataprovider.com/v2/test',
            'https://api.dataprovider.com/v2/auth/oauth2/token',
            'https://api.dataprovider.com/v2/auth/oauth2/token',
        ];
        $expectedRequestBodies = [
            ['grant_type' => 'password', 'username' => 'test', 'password' => 'test'],
            null,
            ['grant_type' => 'refresh_token', 'refresh_token' => 'def'],
            ['grant_type' => 'password', 'username' => 'test', 'password' => 'test'],
        ];
        $expectedRequestHeaders = [
            ['Content-Type' => 'application/json'],
            ['Content-Type' => 'application/json', 'Authorization' => 'Bearer abc'],
            ['Content-Type' => 'application/json'],
            ['Content-Type' => 'application/json'],
        ];

        $this->assertNull($response);
        $this->assertEquals($expectedRequestUrls, $requestData['urls']);
        $this->assertEquals($expectedRequestBodies, $requestData['bodies']);
        $this->assertEquals($expectedRequestHeaders, $requestData['headers']);
        $this->assertEquals('401 Client Error: {"error":{"message":"Forbidden: Invalid credentials or token.","request_id":"1234-5678"}}', $exception->getMessage());
        $this->assertEquals(401, $exception->getCode());
    }

    public function testClientThrowsOnEmptyPath(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Path cannot be empty.');
        $client = new ApiClient('test', 'test');
        $client->post('');
    }

    public function testClientThrowsOnFullUrl(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Path cannot contain a full url, please remove the host.');
        $client = new ApiClient('test', 'test');
        $client->get('https://api.dataprovider.com/v2/test');
    }

    public function testThrowForClientErrorStatus(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('400 Client Error: Bad Request');

        $curl = $this->injectCurlResponses([['statusCode' => 400, 'body' => 'Bad Request']], new RuntimeException('400 Client Error: Bad Request'));

        $client = $this->clientWithCurl($curl);
        $client->get('test/error');
    }
}