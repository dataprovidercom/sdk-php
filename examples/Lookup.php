<?php
require __DIR__ . '/../vendor/autoload.php';

use Dataprovider\SDK\Client\ApiClient;

$client = new ApiClient('username', 'password');

try {
    $response = $client->post(
        '/search-engine/hostnames/www.dataprovider.com',
        null,
        ['fields' => ['hostname', 'response']]
    );
} catch (RuntimeException $e) {
    if ($e->getCode() === 404) {
        echo 'Hostname not found.' . PHP_EOL;
    } else if ($e->getCode() === 429) {
        echo 'Rate limit reached. Try again later.' . PHP_EOL;
    } else {
        echo "An error occurred ({$e->getCode()}): {$e->getMessage()}" . PHP_EOL;
    }
    return;
}

echo $response->getBody() . PHP_EOL;
