<?php
/**
 * PSR7 compatible HTTP client
 *
 * @author  Михаил Красильников <m.krasilnikov@yandex.ru>
 * @license http://opensource.org/licenses/MIT MIT
 */
namespace Mekras\Http\Client\Connector;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use RuntimeException;
use Zend\Diactoros\Response;
use Zend\Diactoros\Stream;
use Zend\Diactoros\Uri;

/**
 * Connector for zendframework/zend-diactoros
 *
 * @since 3.00
 * @link https://github.com/zendframework/zend-diactoros
 */
class DiactorosConnector implements ConnectorInterface
{
    /**
     * Create new empty response
     *
     * @return ResponseInterface
     *
     * @since 3.00
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
     * @since 3.00
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
     * @throws RuntimeException
     *
     * @return StreamInterface
     *
     * @since 3.00
     */
    public function createStreamFromString($content)
    {
        $stream = new Stream('php://memory', 'wb+');
        $stream->write($content);
        $stream->rewind();
        return $stream;
    }
}
