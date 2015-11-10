<?php
/**
 * PSR-7 compatible HTTP client
 *
 * @author  Михаил Красильников <m.krasilnikov@yandex.ru>
 * @license http://opensource.org/licenses/MIT MIT
 */
namespace Mekras\Http\Client\Tests;

use GuzzleHttp\Psr7\Request;
use Mekras\Http\Client\Connector\GuzzleConnector;
use Mekras\Http\Client\CurlHttpClient;
use PHPUnit_Framework_TestCase as TestCase;
use Psr\Http\Message\RequestInterface;

/**
 * Tests for Mekras\Http\Client\CurlHttpClient
 *
 * @covers Mekras\Http\Client\AbstractHttpClient
 * @covers Mekras\Http\Client\CurlHttpClient
 * @covers Mekras\Http\Client\Connector\GuzzleConnector
 */
class CurlHttpClientGuzzleTest extends TestCase
{
    /**
     * Basic usage
     */
    public function testBasics()
    {
        $client = new CurlHttpClient(new GuzzleConnector());
        $request = new Request('GET', 'http://example.org/');
        $request = $request->withHeader('Accept-Encoding', 'text/html');
        $response = $client->sendRequest($request);
        static::assertEquals(200, $response->getStatusCode());
        static::assertEquals('1.1', $response->getProtocolVersion());
        static::assertEquals(['text/html'], $response->getHeader('Content-type'));
        static::assertContains('Example Domain', $response->getBody()->getContents());
    }

    /**
     * Test 404 response
     */
    public function test404()
    {
        $client = $this->getMock(CurlHttpClient::class, ['request'], [new GuzzleConnector()]);
        $client->expects(static::once())->method('request')->willReturnCallback(
            function ($options, &$raw, &$info) {
                $raw = "HTTP/1.1 404 Not Found\r\n\r\nFOO";
                $info = ['redirect_count' => 0, 'header_size' => 26];
            }
        );

        $request = new Request('GET', 'http://example.org/');
        /** @var CurlHttpClient $client */
        $response = $client->sendRequest($request);
        static::assertEquals(404, $response->getStatusCode());
        static::assertEquals('FOO', $response->getBody()->getContents());
    }

    /**
     * Test redirection
     */
    public function testRedirect()
    {
        $client = $this->getMock(CurlHttpClient::class, ['request'], [new GuzzleConnector()]);
        $client->expects(static::exactly(2))->method('request')->willReturnCallback(
            function ($options, &$raw, &$info) {
                static $it = 1;
                if (1 === $it) {
                    $raw = "HTTP/1.1 302 Found\r\nLocation: /foo#bar\r\n\r\n";
                    $info = ['redirect_count' => 0, 'header_size' => 42];
                } else {
                    \PHPUnit_Framework_Assert::assertEquals(
                        'http://example.org/foo#bar',
                        $options[CURLOPT_URL]
                    );
                    $raw = "HTTP/1.1 200 OK\r\n\r\nFOO";
                    $info = ['redirect_count' => 0, 'header_size' => 19];
                }
                $it ++;
            }
        );

        $request = new Request('GET', 'http://example.org/');
        /** @var CurlHttpClient $client */
        $response = $client->sendRequest($request);
        static::assertEquals(200, $response->getStatusCode());
        static::assertEquals('FOO', $response->getBody()->getContents());
    }

    /**
     * Test sending body
     */
    public function testBody()
    {
        $client = $this->getMock(CurlHttpClient::class, ['request'], [new GuzzleConnector()]);
        $client->expects(static::once())->method('request')->willReturnCallback(
            function ($options, &$raw, &$info) {
                static::assertArrayHasKey(CURLOPT_POSTFIELDS, $options);
                static::assertEquals('body', $options[CURLOPT_POSTFIELDS]);
            }
        );

        /** @var RequestInterface $request */
        $request = (new Request('GET', 'http://example.org/'))
            ->withBody(\GuzzleHttp\Psr7\stream_for('body'));
        /** @var CurlHttpClient $client */
        $client->sendRequest($request);
    }

    /**
     * Test cookies
     */
    public function testCookies()
    {
        $client = $this->getMock(CurlHttpClient::class, ['request'], [new GuzzleConnector()]);
        $client->expects(static::once())->method('request')->willReturnCallback(
            function ($options, &$raw, &$info) {
                static::assertArrayHasKey(CURLOPT_COOKIE, $options);
                static::assertEquals('foo=bar; bar=baz', $options[CURLOPT_COOKIE]);
            }
        );

        /** @var RequestInterface $request */
        $request = (new Request('GET', 'http://example.org/'))
            ->withHeader('Cookie', 'foo=bar')
            ->withAddedHeader('Cookie', 'bar=baz');
        /** @var CurlHttpClient $client */
        $client->sendRequest($request);
    }
}
