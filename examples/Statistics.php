<?php
require __DIR__ . '/../vendor/autoload.php';

use Dataprovider\SDK\Client\ApiClient;

$client = new ApiClient('username', 'password');

try {
    $myDatasetId = 1; // Change to your dataset id
    $response = $client->post(
        "/datasets/$myDatasetId/statistics",
        ['size' => 100],
        ['fields' => ['hostname']]
    );
} catch (RuntimeException $e) {
    if ($e->getCode() === 404) {
        echo 'Dataset not found.' . PHP_EOL;
    } else if ($e->getCode() === 429) {
        echo 'Rate limit reached. Try again later.' . PHP_EOL;
    } else {
        echo "An error occurred ({$e->getCode()}): {$e->getMessage()}" . PHP_EOL;
    }
    return;
}

echo $response->getBody() . PHP_EOL;
