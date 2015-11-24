# PSR-7 HTTP client library

[PSR-7](http://www.php-fig.org/psr/psr-7/) compatible HTTP client library.

[![Latest Stable Version](https://poser.pugx.org/mekras/psr7-client/v/stable.png)](https://packagist.org/packages/mekras/psr7-client)
[![License](https://poser.pugx.org/mekras/psr7-client/license.png)](https://packagist.org/packages/mekras/psr7-client)
[![Build Status](https://travis-ci.org/mekras/psr7-client.svg?branch=master)](https://travis-ci.org/mekras/psr7-client)
[![Coverage Status](https://coveralls.io/repos/mekras/psr7-client/badge.png?branch=master)](https://coveralls.io/r/mekras/psr7-client?branch=master)

Simple cURL based PSR-7 compatible HTTP client library.

## Attention!

This package will be replaced with [php-http/curl-client](https://github.com/php-http/curl-client).

### Migrating to Httplug

1. Add [php-http/httplug](https://packagist.org/packages/php-http/httplug) to your project
   requirements.
2. Replace `Mekras\Http\Client\CurlHttpClient` and `Mekras\Interfaces\Http\Client\HttpClientInterface`
   with `Http\Client\HttpClient` in argument type hints
3. Replace [send()](https://github.com/mekras/interfaces/blob/v3.3/lib/Http/Client/HttpClientInterface.php#L37)
   method calls with [sendRequest](https://github.com/php-http/httplug/blob/v1.0.0-alpha2/src/HttpClient.php#L26).

`Mekras\Http\Client\CurlHttpClient` supports both `Mekras\Interfaces\Http\Client\HttpClientInterface`
and `Http\Client\HttpClient` interfaces, so migration can be done gradually.  

After dropping last `Mekras\Interfaces\Http\Client\HttpClientInterface` usage:
 
4. Add any of [php-http/client-implementation](https://packagist.org/providers/php-http/client-implementation)
   to your project and configure it.
5. Replace instances of `Mekras\Http\Client\CurlHttpClient` with instances of chosen
   `php-http/client-implementation`.
5. Remove `mekras/psr7-client` requirement from your `composer.json`.

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

## Options

Options can be set via second argument in constructor. Available options are:

* `connection_timeout` (int) —  connection timeout in seconds;
* `curl_options` (array) —  custom cURL options;
* `decode_content` (bool) — see CURLOPT_ENCODING;
* `follow_redirects` (bool) — automatically follow HTTP redirects;
* `max_redirects` (int) — maximum nested redirects to follow;
* `ssl_verify_peer` (bool) — verify peer when using SSL
* `timeout` (int) —  overall timeout in seconds.
* `use_cookies` (bool) — save and send cookies;

```php
use Mekras\Http\Client\Connector\GuzzleConnector;
use Mekras\Http\Client\CurlHttpClient;

$client = new CurlHttpClient(
    new GuzzleConnector(),
    [
        'timeout' => 60,
        'curl_options' => [
            CURLOPT_CAPATH => '/path/to/ca'
        ]
    ]
);
```
