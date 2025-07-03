<?php

namespace Dataprovider\SDK\Client;

use Dataprovider\SDK\Response\ApiResponse;
use InvalidArgumentException;

/**
 * Class ApiClient
 *
 * Client for interacting with the Dataprovider.com API.
 *
 * This client handles all communication with the Dataprovider.com platform, including
 * authentication, request preparation, and response handling. It abstracts away the
 * low-level HTTP details and provides a simple interface for executing authorized API calls.
 *
 * Use this client to send authenticated requests.
 *
 * ## Example usage:
 * <pre>
 * $client = new ApiClient('username', 'password');
 * $response = $client->post(
 *     '/v2/datasets/1/statistics',
 *     ['size' => 100],
 *     ['fields' => ['hostname']]
 * );
 * </pre>
 */
class ApiClient
{
    const HOST = 'https://api.dataprovider.com/v2';
    const AUTH_PATH = '/auth/oauth2/token';

    private string $username;
    private string $password;
    private ?string $accessToken = null;
    private ?string $refreshToken = null;

    public function __construct(string $username, string $password)
    {
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * Performs a GET request to the Dataprovider.com API.
     *
     * @param string     $path   The API endpoint path (relative, not full URL).
     * @param array|null $params Optional query parameters.
     * @param array|null $body   Optional request body.
     *
     * @return ApiResponse The API response.
     */
    public function get(string $path, ?array $params = null, ?array $body = null): ApiResponse
    {
        return $this->doRequest($path, 'GET', $params, $body);
    }

    /**
     * Performs a POST request to the Dataprovider.com API.
     *
     * @param string     $path   The API endpoint path (relative, not full URL).
     * @param array|null $params Optional query parameters.
     * @param array|null $body   Optional request body.
     *
     * @return ApiResponse The API response.
     */
    public function post(string $path, ?array $params = null, ?array $body = null): ApiResponse
    {
        return $this->doRequest($path, 'POST', $params, $body);
    }

    /**
     * Performs a PUT request to the Dataprovider.com API.
     *
     * @param string     $path   The API endpoint path (relative, not full URL).
     * @param array|null $params Optional query parameters.
     * @param array|null $body   Optional request body.
     *
     * @return ApiResponse The API response.
     */
    public function put(string $path, ?array $params = null, ?array $body = null): ApiResponse
    {
        return $this->doRequest($path, 'PUT', $params, $body);
    }

    /**
     * @throws \RuntimeException
     */
    private function doRequest(string $path, string $method, ?array $params, ?array $body): ApiResponse
    {
        if (empty($path)) {
            throw new InvalidArgumentException('Path cannot be empty.');
        } else if (strpos($path, 'http') === 0) {
            throw new InvalidArgumentException('Path cannot contain a full url, please remove the host.');
        }

        $trimmedPath = ltrim($path, '/');
        $queryParams = http_build_query($params ?? []);
        $url = rtrim(self::HOST . "/$trimmedPath?$queryParams", '?');
        $headers = ['Content-Type' => 'application/json'];

        if ($this->accessToken === null && $path !== self::AUTH_PATH) {
            $this->authenticate();
            $headers['Authorization'] = "Bearer $this->accessToken";
        }

        $curlClient = $this->getCurlClient();
        $response = $curlClient
            ->setUrl($url)
            ->setRequestMethod($method)
            ->setBody($body)
            ->setRequestHeaders($headers)
            ->send();

        if ($response['statusCode'] === 401 && $this->accessToken !== null) {
            $this->accessToken = null;
            return $this->doRequest($path, $method, $params, $body);
        }

        $curlClient->throwForStatus($response);

        return new ApiResponse($response['body'], $response['statusCode']);
    }

    private function authenticate(): ApiResponse
    {
        try {
            if ($this->refreshToken === null) {
                $response = $this->getAccessTokenByCredentials();
            } else {
                $response = $this->getAccessTokenByRefreshToken();
            }
        } catch (\RuntimeException $e) {
            if ($this->refreshToken !== null) {
                // Expired refresh token, try again with credentials
                $this->refreshToken = null;
                return $this->authenticate();
            }
            throw $e;
        }

        $body = $response->getJsonBody();
        $this->accessToken = $body['access_token'];
        $this->refreshToken = $body['refresh_token'];

        return $response;
    }

    private function getAccessTokenByCredentials(): ApiResponse
    {
        return $this->post(self::AUTH_PATH, null, [
            'grant_type' => 'password',
            'username' => $this->username,
            'password' => $this->password
        ]);
    }

    private function getAccessTokenByRefreshToken(): ApiResponse
    {
        return $this->post(self::AUTH_PATH, null, [
            'grant_type' => 'refresh_token',
            'refresh_token' => $this->refreshToken
        ]);
    }

    protected function getCurlClient(): CurlClient
    {
        return new CurlClient();
    }
}