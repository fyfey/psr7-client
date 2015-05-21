<?php
/**
 * PSR7 compatible HTTP client
 *
 * @author  Михаил Красильников <m.krasilnikov@yandex.ru>
 * @license http://opensource.org/licenses/MIT MIT
 */
namespace Mekras\Http\Client;

use Exception;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Uri;
use Mekras\Interfaces\Http\Client\HttpClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

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
     * Constructor
     *
     * Available options:
     *
     * - follow_redirects : bool — automatically follow HTTP re
     * - max_redirects : int — maximum nested redirects to follow
     * - use_cookies : bool — save and send cookies
     * - decode_content : bool — see CURLOPT_ENCODING
     * - connection_timeout : int —  connection timeout in seconds
     * - timeout : int —  overall timeout in seconds
     *
     * @param array $options
     *
     * @since 1.00
     */
    public function __construct(array $options = [])
    {
        $this->options = array_merge($this->options, $options);
    }

    /**
     * Perform an HTTP request and return response
     *
     * @param RequestInterface $request
     *
     * @throws Exception if request failed (e. g. network problem)
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

        $ch = curl_init();
        curl_setopt_array($ch, $options);
        $raw = curl_exec($ch);

        if (curl_errno($ch) > 0) {
            throw new \RuntimeException(
                sprintf('Curl error: (%d) %s', curl_errno($ch), curl_error($ch))
            );
        }

        $response = new Response();

        $curlInfo = curl_getinfo($ch);
        $this->redirectCounter = $curlInfo['redirect_count'];
        $headerSize = $curlInfo['header_size'];
        $rawHeaders = substr($raw, 0, $headerSize);
        $content = substr($raw, $headerSize);

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
            if (substr(strtolower($header), 0, 4) == 'http') {
                $temp = explode(' ', $header, 3);
                $temp2 = explode('/', $temp[0], 2);
                $response = $response->withProtocolVersion($temp2[1]);
                continue;
            }
            // Extract header
            $temp = explode(':', $header, 2);
            $headerName = trim(urldecode($temp[0]));
            $headerValue = trim(urldecode($temp[1]));
            $response = $this->addHeaderToResponse($response, $headerName, $headerValue);
        }
        // Write content
        $response->getBody()->write($content);
        $response->getBody()->rewind();

        curl_close($ch);

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
     * Handles redirection if follow redirection is enabled
     *
     * @param RequestInterface  $request
     * @param ResponseInterface $response
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
            throw new \RuntimeException('Redirection limit exceeded!');
        }

        if (!$response->getHeader('location')) {
            throw new \RuntimeException('Location obsolete by redirection!');
        }

        $this->redirectCounter++;

        $location = $response->getHeader('location');
        $request = $request->withUri(new Uri($location));
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
