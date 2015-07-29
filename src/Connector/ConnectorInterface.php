<?php
/**
 * PSR-7 compatible HTTP client
 *
 * @author  Михаил Красильников <m.krasilnikov@yandex.ru>
 * @license http://opensource.org/licenses/MIT MIT
 */
namespace Mekras\Http\Client\Connector;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

/**
 * Interface for connectors to PSR-7 libraries
 *
 * @since 3.00
 */
interface ConnectorInterface
{
    /**
     * Create new empty response
     *
     * @return ResponseInterface
     *
     * @since 3.00
     */
    public function createResponse();

    /**
     * Create new URI
     *
     * @param string $uri optional URI data as string
     *
     * @return UriInterface
     *
     * @since 3.00
     */
    public function createUri($uri = '');

    /**
     * Creates stream from string data
     *
     * @param string $content
     *
     * @return StreamInterface
     *
     * @since 3.00
     */
    public function createStreamFromString($content);
}
