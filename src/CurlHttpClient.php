<?php
/**
 * PSR-7 compatible HTTP client
 *
 * @author  Михаил Красильников <m.krasilnikov@yandex.ru>
 * @license http://opensource.org/licenses/MIT MIT
 */
namespace Mekras\Http\Client;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * cURL-based HTTP client
 *
 * Additional constructor options (see also {@link AbstractHttpClient::__construct}):
 *
 * - curl_options : array — {@link http://php.net/manual/ru/function.curl-setopt.php cURL options}
 * - decode_content : bool — see CURLOPT_ENCODING
 * - use_cookies : bool — save and send cookies
 *
 * @author Kemist <kemist1980@gmail.com>
 * @author Михаил Красильников <m.krasilnikov@yandex.ru>
 *
 * @api
 * @since  1.00
 */
class CurlHttpClient extends AbstractHttpClient
{
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
        /** @var ResponseInterface $response */
        $response = $response->withBody($stream);

        $response = $this->followRedirect($request, $response);

        return $response;
    }

    /**
     * Return available options and there default values
     *
     * @return array
     *
     * @since 3.02
     */
    public function getDefaultOptions()
    {
        return array_merge(
            parent::getDefaultOptions(),
            [
                'curl_options' => [],
                'decode_content' => true,
                'use_cookies' => true
            ]
        );
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
                                && strpos($request->getUri()->getHost(), $itemValue) !== false)
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
     * @since 3.00
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
     * Generates cURL options
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
        $options[CURLOPT_FOLLOWLOCATION] = $this->options['follow_redirects'];
        $options[CURLOPT_MAXREDIRS] = $this->options['max_redirects'];
        $options[CURLOPT_SSL_VERIFYPEER] = $this->options['ssl_verify_peer'];
        $options[CURLOPT_TIMEOUT] = $this->options['timeout'];

        if ($this->options['decode_content'] && $request->hasHeader('accept-encoding')) {
            $options[CURLOPT_ENCODING] = $request->getHeaderLine('accept-encoding');
        }
        if ($this->options['use_cookies'] && $request->hasHeader('cookie')) {
            $options[CURLOPT_COOKIE] = implode('; ', $request->getHeader('cookie'));
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
}
