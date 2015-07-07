<?php
/**
 * PSR7 compatible HTTP client
 *
 * @author  Михаил Красильников <m.krasilnikov@yandex.ru>
 * @license http://opensource.org/licenses/MIT MIT
 */
namespace Mekras\Http\Client\Connector;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

/**
 * Connector for guzzlehttp/psr7
 *
 * @since x.xx
 * @link https://github.com/guzzle/psr7
 */
class GuzzleConnector implements ConnectorInterface
{
    /**
     * Create new empty response
     *
     * @return ResponseInterface
     *
     * @since x.xx
     */
    public function createResponse()
    {
        return new Response();
    }

    /**
     * Create new URI
     *
     * @param string $uri optional URI data as string
     *
     * @return UriInterface
     *
     * @since x.xx
     */
    public function createUri($uri = '')
    {
        return new Uri($uri);
    }

    /**
     * Creates stream from string data
     *
     * @param string $content
     *
     * @return StreamInterface
     *
     * @since x.xx
     */
    public function createStreamFromString($content)
    {
        return \GuzzleHttp\Psr7\stream_for($content);
    }
}
