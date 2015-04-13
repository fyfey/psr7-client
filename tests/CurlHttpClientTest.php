<?php
/**
 * PSR7 compatible HTTP client
 *
 * @author  Михаил Красильников <m.krasilnikov@yandex.ru>
 * @license http://opensource.org/licenses/MIT MIT
 */
namespace Mekras\Http\Client\Tests;

use GuzzleHttp\Psr7\Request;
use Mekras\Http\Client\CurlHttpClient;
use PHPUnit_Framework_TestCase as TestCase;

/**
 * Tests for Mekras\Http\Client\CurlHttpClient
 *
 * @covers Mekras\Http\Client\CurlHttpClient
 */
class CurlHttpClientTest extends TestCase
{
    /**
     * Basic usage
     */
    public function testBasics()
    {
        $client = new CurlHttpClient();
        $request = new Request('GET', 'http://example.org/');
        $response = $client->send($request);
        static::assertEquals(200, $response->getStatusCode());
        static::assertEquals('text/html', $response->getHeader('Content-type'));
        static::assertContains('Example Domain', $response->getBody()->getContents());
    }

    /**
     * Test deprecated method "get"
     */
    public function testGet()
    {
        $client = new CurlHttpClient();
        $response = $client->get('http://example.org/');
        static::assertEquals(200, $response->getStatusCode());
        static::assertContains('Example Domain', $response->getBody()->getContents());
    }

    /**
     * Test deprecated method "post"
     */
    public function testPost()
    {
        $client = new CurlHttpClient();
        $response = $client->post('http://example.org/', null, ['foo' => 'bar']);
        static::assertEquals(200, $response->getStatusCode());
    }
}
