<?php
/**
 * PSR-7 compatible HTTP client
 *
 * @author  Михаил Красильников <m.krasilnikov@yandex.ru>
 * @license http://opensource.org/licenses/MIT MIT
 */
namespace Mekras\Http\Client\Helper;

use Psr\Http\Message\RequestInterface;

/**
 * Authentication helper for requests
 *
 * @since 1.01
 */
class AuthHelper
{
    /**
     * Add Basic HTTP authentication to request
     *
     * @param RequestInterface $request
     * @param string           $username
     * @param string           $password
     *
     * @return RequestInterface
     *
     * @since 1.01
     */
    public function basic(RequestInterface $request, $username, $password)
    {
        return $request
            ->withHeader('Authorization', 'Basic: ' . base64_encode("$username:$password"));
    }
}
