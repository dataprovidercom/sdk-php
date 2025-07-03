# Dataprovider.com PHP SDK

A lightweight SDK for interacting with the [Dataprovider.com](https://www.dataprovider.com) API in PHP.\
This client provides a convenient way to authenticate, build requests, and handle responses for GET, POST, and PUT operations.

## 🚀 Installation

Use [Composer](https://getcomposer.org) to install the SDK:

```bash
composer require dataprovider/sdk
```

## ✅ Requirements

- PHP >= 8.3
- PHP cURL extension enabled
- Composer for dependency management

## 🔧 Usage
```php
require __DIR__ . '/vendor/autoload.php';

use Dataprovider\SDK\Client\ApiClient;

$apiClient = new ApiClient("username", "password");

try {
    $response = $apiClient->get('/datasets/list');
} catch (RuntimeException $e) {
    echo "An error occurred ({$e->getCode()}): {$e->getMessage()}" . PHP_EOL;
    return;
}

// Access the response data as text
print_r($response->getBody());

// Access the response data as array ($associative=true) or object ($associative=false)
print_r($response->getJsonBody(true));
```

See [examples](examples) for more.