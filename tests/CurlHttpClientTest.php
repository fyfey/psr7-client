<?php
/**
 * PSR-7 compatible HTTP client
 *
 * @author  Михаил Красильников <m.krasilnikov@yandex.ru>
 * @license http://opensource.org/licenses/MIT MIT
 */
namespace Mekras\Http\Client\Tests;

use Mekras\Http\Client\Connector\ConnectorInterface;
use Mekras\Http\Client\CurlHttpClient;
use PHPUnit_Framework_TestCase as TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;
use ReflectionMethod;

/**
 * Tests for Mekras\Http\Client\CurlHttpClient
 *
 * @covers Mekras\Http\Client\CurlHttpClient
 */
class CurlHttpClientTest extends TestCase
{
    /**
     * Test of "ssl_verify_peer" option
     */
    public function testOptionsSslVerifyPeer()
    {
        $createCurlOptions = new ReflectionMethod(CurlHttpClient::class, 'createCurlOptions');
        $createCurlOptions->setAccessible(true);

        /** @var ConnectorInterface $connector */
        $connector = $this->getMockForAbstractClass(ConnectorInterface::class);
        $uri = $this->getMockForAbstractClass(UriInterface::class);
        $request = $this->getMockForAbstractClass(RequestInterface::class);
        $request->expects(static::any())->method('getHeaders')->willReturn([]);
        $request->expects(static::any())->method('getUri')->willReturn($uri);

        $client = new CurlHttpClient($connector);
        $options = $createCurlOptions->invoke($client, $request);
        static::assertTrue($options[CURLOPT_SSL_VERIFYPEER]);

        $client = new CurlHttpClient($connector, ['ssl_verify_peer' => false]);
        $options = $createCurlOptions->invoke($client, $request);
        static::assertFalse($options[CURLOPT_SSL_VERIFYPEER]);
    }

    /**
     * Test of "curl_options" option
     */
    public function testOptionsCurlOptions()
    {
        $createCurlOptions = new ReflectionMethod(CurlHttpClient::class, 'createCurlOptions');
        $createCurlOptions->setAccessible(true);

        /** @var ConnectorInterface $connector */
        $connector = $this->getMockForAbstractClass(ConnectorInterface::class);
        $uri = $this->getMockForAbstractClass(UriInterface::class);
        $request = $this->getMockForAbstractClass(RequestInterface::class);
        $request->expects(static::any())->method('getHeaders')->willReturn([]);
        $request->expects(static::any())->method('getUri')->willReturn($uri);

        $client = new CurlHttpClient($connector, ['curl_options' => [CURLOPT_VERBOSE => true]]);
        $options = $createCurlOptions->invoke($client, $request);
        static::assertTrue($options[CURLOPT_VERBOSE]);
    }

    /**
     * Test of choosing HTTP version
     */
    public function testHttpVersionSelection()
    {
        $createCurlOptions = new ReflectionMethod(CurlHttpClient::class, 'createCurlOptions');
        $createCurlOptions->setAccessible(true);

        /** @var ConnectorInterface $connector */
        $connector = $this->getMockForAbstractClass(ConnectorInterface::class);
        $uri = $this->getMockForAbstractClass(UriInterface::class);
        $request = $this->getMockForAbstractClass(RequestInterface::class);
        $request->expects(static::any())->method('getHeaders')->willReturn([]);
        $request->expects(static::any())->method('getUri')->willReturn($uri);
        $version = new \stdClass();
        $version->value = null;
        $request->expects(static::any())->method('getProtocolVersion')->willReturnCallback(
            function () use ($version) {
                return $version->value;
            }
        );

        $client = new CurlHttpClient($connector, ['curl_options' => [CURLOPT_VERBOSE => true]]);

        // None by default
        $options = $createCurlOptions->invoke($client, $request);
        static::assertEquals($options[CURLOPT_HTTP_VERSION], CURL_HTTP_VERSION_NONE);

        $version->value = '1.0';
        $options = $createCurlOptions->invoke($client, $request);
        static::assertEquals($options[CURLOPT_HTTP_VERSION], CURL_HTTP_VERSION_1_0);

        $version->value = '1.1';
        $options = $createCurlOptions->invoke($client, $request);
        static::assertEquals($options[CURLOPT_HTTP_VERSION], CURL_HTTP_VERSION_1_1);

        if (!defined('CURL_HTTP_VERSION_2_0')) {
            define('CURL_HTTP_VERSION_2_0', '3');
        }

        $version->value = '2.0';
        $options = $createCurlOptions->invoke($client, $request);
        static::assertEquals($options[CURLOPT_HTTP_VERSION], CURL_HTTP_VERSION_2_0);
    }
}
