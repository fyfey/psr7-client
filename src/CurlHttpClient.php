<?php
/**
 * PSR7 compatible HTTP client
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
use Zend\Diactoros\Stream;

/**
 * cURL-based HTTP client
 *
 * @author Kemist <kemist1980@gmail.com>
 * @author Михаил Красильников <m.krasilnikov@yandex.ru>
 *
 * @api
 * @since  1.00
 */
class CurlHttpClient implements HttpClientInterface
{
    /**
     * Client options
     *
     * @var array
     */
    private $options = [
        'follow_redirects' => true,
        'max_redirects' => 10,
        'use_cookies' => true,
        'decode_content' => true,
        'connection_timeout' => 30,
        'timeout' => 60
    ];

    /**
     * Redirect counter
     *
     * @var int
     */
    private $redirectCounter = 0;

    /**
     * PSR-7 provider
     *
     * @var ConnectorInterface
     */
    private $psr7;

    /**
     * Constructor
     *
     * Available options:
     *
     * - follow_redirects : bool — automatically follow HTTP redirects
     * - max_redirects : int — maximum nested redirects to follow
     * - use_cookies : bool — save and send cookies
     * - decode_content : bool — see CURLOPT_ENCODING
     * - connection_timeout : int —  connection timeout in seconds
     * - timeout : int —  overall timeout in seconds
     *
     * @param ConnectorInterface $psr7    Connector to PSR-7 library
     * @param array              $options cURL options
     *
     * @since x.xx new argument — $emptyResponse
     * @since 1.00
     */
    public function __construct(ConnectorInterface $psr7, array $options = [])
    {
        $this->psr7 = $psr7;
        $this->options = array_merge($this->options, $options);
    }

    /**
     * Perform an HTTP request and return response
     *
     * @param RequestInterface $request
     *
     * @throws RuntimeException if request failed (e. g. network problem)
     *
     * @return ResponseInterface
     *
     * @since 1.00
     */
    public function send(RequestInterface $request)
    {
        $options = $this->createCurlOptions($request);

        $body = (string) $request->getBody();
        if ('' !== $body) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }

        $this->request($options, $raw, $info);

        $response = $this->psr7->createResponse();

        $this->redirectCounter += $info['redirect_count'];
        $headerSize = $info['header_size'];
        $rawHeaders = substr($raw, 0, $headerSize);

        // Parse headers
        $allHeaders = explode("\r\n\r\n", $rawHeaders);
        $lastHeaders = trim(array_pop($allHeaders));
        while (count($allHeaders) > 0 && '' == $lastHeaders) {
            $lastHeaders = trim(array_pop($allHeaders));
        }
        $headerLines = explode("\r\n", $lastHeaders);
        foreach ($headerLines as $header) {
            $header = trim($header);
            if ('' == $header) {
                continue;
            }
            // Status line
            if (substr(strtolower($header), 0, 5) == 'http/') {
                $parts = explode(' ', $header, 3);
                $response = $response
                    ->withStatus($parts[1])
                    ->withProtocolVersion($parts[0]);
                continue;
            }
            // Extract header
            $parts = explode(':', $header, 2);
            $headerName = trim(urldecode($parts[0]));
            $headerValue = trim(urldecode($parts[1]));
            $response = $this->addHeaderToResponse($response, $headerName, $headerValue);
        }

        $content = (string) substr($raw, $headerSize);
        $stream = $this->psr7->createStreamFromString($content);
        $response = $response->withBody($stream);

        $response = $this->followRedirect($request, $response);

        return $response;
    }

    /**
     * Sets request cookies based on response
     *
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     *
     * @return RequestInterface
     */
    public function setRequestCookies(RequestInterface $request, ResponseInterface $response)
    {
        if (!$this->options['use_cookies']) {
            return $request;
        }
        $cookies = $response->getHeader('set-cookie');
        if (!$cookies) {
            return $request;
        }
        $cookieHeaders = [];
        foreach ($cookies as $cookie) {
            $temp = explode(';', $cookie);
            $keyAndValue = array_shift($temp);
            list($key, $value) = explode('=', $keyAndValue);
            $key = trim($key);
            $value = trim($value);
            if (isset($cookieHeaders[$key])) {
                unset($cookieHeaders[$key]);
            }
            foreach ($temp as $item) {
                $item = trim($item);

                $itemArray = explode('=', $item);
                $param = strtolower(trim($itemArray[0]));
                $itemValue = isset($itemArray[1]) ? trim($itemArray[1]) : null;
                switch ($param) {
                    case 'expires':
                        $exp = new \DateTime($itemValue);
                        if ($exp->format('U') < time()) {
                            continue 3;
                        }
                        break;
                    case 'domain':

                        if ($itemValue != $request->getUri()->getHost() &&
                            !(substr($itemValue, 0, 1) == '.'
                                && strstr($request->getUri()->getHost(), $itemValue))
                        ) {
                            continue 3;
                        }
                        break;
                    case 'path':
                        if ($itemValue != '/' && $itemValue != $request->getUri()->getPath()) {
                            continue 3;
                        }
                        break;
                    case 'secure':
                        if ($request->getUri()->getScheme() != 'https') {
                            continue 3;
                        }
                        break;
                }
            }
            $cookieHeaders[$key] = $key . '=' . $value;
        }

        if (count($cookieHeaders) > 0) {
            $request = $request->withHeader('cookie', implode('; ', $cookieHeaders));
        }
        return $request;
    }

    /**
     * Perform request via cURL
     *
     * @param array  $options cURL options
     * @param string $raw     raw response
     * @param array  $info    cURL response info
     *
     * @throws RuntimeException
     *
     * @return void
     *
     * @since x.xx
     */
    protected function request($options, &$raw, &$info)
    {
        $ch = curl_init();
        curl_setopt_array($ch, $options);
        $raw = curl_exec($ch);

        if (curl_errno($ch) > 0) {
            throw new RuntimeException(
                sprintf('Curl error: (%d) %s', curl_errno($ch), curl_error($ch))
            );
        }
        $info = curl_getinfo($ch);
        curl_close($ch);
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
    private function followRedirect(RequestInterface $request, ResponseInterface $response)
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

    /**
     * Generates curl options
     *
     * @param RequestInterface $request
     *
     * @return array
     */
    private function createCurlOptions(RequestInterface $request)
    {
        $options = array_key_exists('curl_options', $this->options)
            ? $this->options['curl_options']
            : [];

        $options[CURLOPT_HEADER] = true;
        $options[CURLOPT_RETURNTRANSFER] = true;

        $options[CURLOPT_HTTP_VERSION] = $request->getProtocolVersion();
        $options[CURLOPT_URL] = (string) $request->getUri();
        $options[CURLOPT_CONNECTTIMEOUT] = $this->options['connection_timeout'];
        $options[CURLOPT_TIMEOUT] = $this->options['timeout'];
        $options[CURLOPT_FOLLOWLOCATION] = $this->options['follow_redirects'];
        $options[CURLOPT_MAXREDIRS] = $this->options['max_redirects'];

        if ($this->options['decode_content'] && $request->hasHeader('accept-encoding')) {
            $options[CURLOPT_ENCODING] = $request->getHeader('accept-encoding');
        }
        if ($this->options['use_cookies'] && $request->hasHeader('cookie')) {
            $options[CURLOPT_COOKIE] = $request->getHeader('cookie');
        }

        switch ($request->getMethod()) {
            case 'GET':
                $options[CURLOPT_HTTPGET] = true;
                break;
            case 'HEAD':
                $options[CURLOPT_NOBODY] = true;
                break;
            case 'POST':
            case 'CONNECT':
            case 'DELETE':
            case 'PATCH':
            case 'PUT':
            case 'TRACE':
                $options[CURLOPT_CUSTOMREQUEST] = $request->getMethod();
                break;
        }

        $headers = array_keys($request->getHeaders());
        foreach ($headers as $name) {
            $values = $request->getHeader($name);
            foreach ($values as $value) {
                $options[CURLOPT_HTTPHEADER][] = $name . ': ' . $value;
            }
        }

        if ($request->getUri()->getUserInfo()) {
            $options[CURLOPT_USERPWD] = $request->getUri()->getUserInfo();
        }

        return $options;
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
    private function addHeaderToResponse($response, $name, $value)
    {
        if ($response->hasHeader($name)) {
            $response = $response->withAddedHeader($name, $value);
        } else {
            $response = $response->withHeader($name, $value);
        }
        return $response;
    }
}
