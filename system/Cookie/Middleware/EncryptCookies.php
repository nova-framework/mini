<?php

namespace Mini\Cookie\Middleware;

use Mini\Encryption\DecryptException;
use Mini\Encryption\Encrypter;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use Closure;


class EncryptCookies
{
    /**
     * The encrypter instance.
     *
     * @var \Mini\Encryption\Encrypter
     */
    protected $encrypter;

    /**
     * The names of the cookies that should not be encrypted.
     *
     * @var array
     */
    protected $except = array();


    /**
     * Create a new CookieGuard instance.
     *
     * @param  \Mini\Encryption\Encrypter  $encrypter
     * @return void
     */
    public function __construct(Encrypter $encrypter)
    {
        $this->encrypter = $encrypter;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Mini\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $response = call_user_func($next, $this->decrypt($request));

        return $this->encrypt($response);
    }

    /**
     * Decrypt the cookies on the request.
     *
     * @param  \Symfony\Component\HttpFoundation\Request  $request
     * @return \Symfony\Component\HttpFoundation\Request
     */
    protected function decrypt(Request $request)
    {
        foreach ($request->cookies as $key => $cookie) {
            if ($this->isCookieDisabled($key)) {
                continue;
            }

            try {
                $value = $this->decryptCookie($cookie);
            }
            catch (DecryptException $e) {
                $value = null;
            }

            $request->cookies->set($key, $value);
        }

        return $request;
    }

    /**
     * Decrypt the given cookie and return the value.
     *
     * @param  string|array  $cookie
     * @return string|array
     */
    protected function decryptCookie($cookie)
    {
        if (! is_array($cookie)) {
            return $this->encrypter->decrypt($cookie);
        }

        $decrypted = array();

        foreach ($cookie as $key => $value) {
            if (! is_string($value)) {
                continue;
            }

            $decrypted[$key] = $this->encrypter->decrypt($value);
        }

        return $decrypted;
    }

    /**
     * Encrypt the cookies on an outgoing response.
     *
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function encrypt(Response $response)
    {
        $cookies = $response->headers->getCookies();

        foreach ($cookies as $cookie) {
            if ($this->isCookieDisabled($cookie->getName())) {
                continue;
            }

            $response->headers->setCookie($this->duplicate(
                $cookie, $this->encrypter->encrypt($cookie->getValue())
            ));
        }

        return $response;
    }

    /**
     * Duplicate a cookie with a new value.
     *
     * @param  \Symfony\Component\HttpFoundation\Cookie  $cookie
     * @param  mixed  $value
     * @return \Symfony\Component\HttpFoundation\Cookie
     */
    protected function duplicate(Cookie $cookie, $value)
    {
        return new Cookie(
            $cookie->getName(),
            $value,
            $cookie->getExpiresTime(),
            $cookie->getPath(),
            $cookie->getDomain(),
            $cookie->isSecure(),
            $cookie->isHttpOnly()
        );
    }

    /**
     * Determine whether encryption has been disabled for the given cookie.
     *
     * @param  string $name
     * @return bool
     */
    public function isCookieDisabled($name)
    {
        return in_array($name, $this->except);
    }
}
