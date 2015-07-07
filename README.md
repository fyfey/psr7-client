# PSR7 HTTP client library

PSR7 compatible HTTP client library.

[![Latest Stable Version](https://poser.pugx.org/mekras/psr7-client/v/stable.png)](https://packagist.org/packages/mekras/psr7-client)
[![License](https://poser.pugx.org/mekras/psr7-client/license.png)](https://packagist.org/packages/mekras/psr7-client)
[![Build Status](https://travis-ci.org/mekras/psr7-client.svg?branch=master)](https://travis-ci.org/mekras/psr7-client)
[![Coverage Status](https://coveralls.io/repos/mekras/psr7-client/badge.png?branch=master)](https://coveralls.io/r/mekras/psr7-client?branch=master)

Simple cURL based PSR7 compatible HTTP client library.

## Supported libraries

* [guzzlehttp/psr7](https://github.com/guzzle/psr7)
* [zendframework/zend-diactoros](https://github.com/zendframework/zend-diactoros)
* other by implementing [ConnectorInterface](src/Connector/ConnectorInterface.php)

## Usage

```php
use GuzzleHttp\Psr7\Request;
use Mekras\Http\Client\Connector\GuzzleConnector;
use Mekras\Http\Client\CurlHttpClient;

$client = new CurlHttpClient(new GuzzleConnector());
$request = new Request('GET', 'http://example.org/');
$response = $client->send($request);
echo $response->getBody()->getContents());
```
