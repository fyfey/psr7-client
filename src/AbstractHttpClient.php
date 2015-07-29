<?php
/**
 * PSR-7 compatible HTTP client
 *
 * @author  Михаил Красильников <m.krasilnikov@yandex.ru>
 * @license http://opensource.org/licenses/MIT MIT
 */
namespace Mekras\Http\Client;

use Mekras\Http\Client\Connector\ConnectorInterface;
use Mekras\Interfaces\Http\Client\HttpClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use RuntimeException;

/**
 * Abstract HTTP client
 *
 * @since x.xx
 */
abstract class AbstractHttpClient implements HttpClientInterface
{
    /**
     * PSR-7 provider
     *
     * @var ConnectorInterface
     */
    protected $psr7;

    /**
     * Client options
     *
     * @var array
     */
    protected $options;

    /**
     * Redirect counter
     *
     * @var int
     */
    protected $redirectCounter;

    /**
     * Constructor
     *
     * Available options (see also {@link getDefaultOptions}):
     *
     * - connection_timeout : int —  connection timeout in seconds
     * - follow_redirects : bool — automatically follow HTTP redirects
     * - max_redirects : int — maximum nested redirects to follow
     * - timeout : int —  overall timeout in seconds
     *
     * @param ConnectorInterface $psr7    Connector to PSR-7 library
     * @param array              $options Client options
     *
     * @since x.xx
     */
    public function __construct(ConnectorInterface $psr7, array $options = [])
    {
        $this->psr7 = $psr7;
        $this->options = array_merge(
            $this->getDefaultOptions(),
            array_intersect_key(
                $options,
                $this->getDefaultOptions()
            )
        );
    }

    /**
     * Return available options and there default values
     *
     * @return array
     *
     * @since x.xx
     */
    public function getDefaultOptions()
    {
        return [
            'connection_timeout' => 3,
            'follow_redirects' => true,
            'max_redirects' => 10,
            'timeout' => 10
        ];
    }

    /**
     * Adds a header to the response object
     *
     * @param ResponseInterface $response
     * @param string            $name
     * @param string            $value
     *
     * @return ResponseInterface
     */
    protected function addHeaderToResponse($response, $name, $value)
    {
        if ($response->hasHeader($name)) {
            $response = $response->withAddedHeader($name, $value);
        } else {
            $response = $response->withHeader($name, $value);
        }
        return $response;
    }

    /**
     * Handles redirection if follow redirection is enabled
     *
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     *
     * @throws RuntimeException
     *
     * @return ResponseInterface
     */
    protected function followRedirect(RequestInterface $request, ResponseInterface $response)
    {
        $statusCode = $response->getStatusCode();
        if ($statusCode < 300 || $statusCode >= 400 || !$this->options['follow_redirects']) {
            return $response;
        }

        if ($this->redirectCounter >= $this->options['max_redirects']) {
            throw new RuntimeException('Redirection limit exceeded!');
        }

        if (!$response->getHeader('location')) {
            throw new RuntimeException('Location obsolete by redirection!');
        }

        $this->redirectCounter ++;

        $location = $response->getHeader('location');
        $parts = parse_url($location[0]);

        $uri = $this->psr7->createUri();

        $scheme = isset($parts['scheme']) ? $parts['scheme'] : $request->getUri()->getScheme();
        $uri = $uri->withScheme($scheme);

        if (isset($parts['user'])) {
            $user = $parts['user'];
            $pass = isset($parts['pass']) ? $parts['pass'] : null;
        } elseif (strpos($request->getUri()->getUserInfo(), ':') !== false) {
            list($user, $pass) = explode(':', $request->getUri()->getUserInfo(), 2);
        } else {
            $user = $request->getUri()->getUserInfo();
            $pass = null;
        }
        if ($user) {
            /** @var UriInterface $uri */
            $uri = $uri->withUserInfo($user, $pass);
        }

        $host = isset($parts['host']) ? $parts['host'] : $request->getUri()->getHost();
        $uri = $uri->withHost($host);

        $port = isset($parts['port']) ? $parts['port'] : $request->getUri()->getPort();
        if ($port) {
            $uri = $uri->withPort($port);
        }

        $path = isset($parts['path']) ? $parts['path'] : $request->getUri()->getPath();

        $query = isset($parts['query']) ? $parts['query'] : $request->getUri()->getQuery();

        $fragment = isset($parts['fragment'])
            ? $parts['fragment']
            : $request->getUri()->getFragment();

        $uri = $uri
            ->withPath($path)
            ->withQuery($query)
            ->withFragment($fragment);

        $request = $request->withUri($uri);
        return $this->send($request);
    }
}