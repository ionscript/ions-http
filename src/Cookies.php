<?php

namespace Ions\Http;

use ArrayIterator;
use Ions\Http\Header\SetCookie;
use Ions\Uri\Http as HttpUri;
use Ions\Uri\UriInterface;

/**
 * Class Cookies
 * @package Ions\Http
 */
class Cookies extends Headers
{
    const COOKIE_OBJECT = 0;
    const COOKIE_STRING_ARRAY = 1;
    const COOKIE_STRING_CONCAT = 2;
    const COOKIE_STRING_CONCAT_STRICT = 3;

    /**
     * @var array
     */
    protected $cookies = [];

    /**
     * @var
     */
    protected $headers;

    /**
     * @var
     */
    protected $rawCookies;

    /**
     * @param $string
     * @return void
     * @throws \RuntimeException
     */
    public static function create($string)
    {
        throw new \RuntimeException(__CLASS__ . '::' . __FUNCTION__ . ' should not be used as a factory, use ' . __NAMESPACE__ . '\Headers::fromtString() instead.');
    }

    /**
     * @param $cookie
     * @param null $refUri
     * @throws \InvalidArgumentException
     */
    public function addCookie($cookie, $refUri = null)
    {
        if (is_string($cookie)) {
            $cookie = SetCookie::create($cookie, $refUri);
        }
        if ($cookie instanceof SetCookie) {
            $domain = $cookie->getDomain();
            $path = $cookie->getPath();
            if (!isset($this->cookies[$domain])) {
                $this->cookies[$domain] = [];
            }
            if (!isset($this->cookies[$domain][$path])) {
                $this->cookies[$domain][$path] = [];
            }
            $this->cookies[$domain][$path][$cookie->getName()] = $cookie;
            $this->rawCookies[] = $cookie;
        } else {
            throw new \InvalidArgumentException('Supplient argument is not a valid cookie string or object');
        }
    }

    /**
     * @param Response $response
     * @param $refUri
     */
    public function addCookiesFromResponse(Response $response, $refUri)
    {
        $cookieHdrs = $response->getHeaders()->get('Set-Cookie');

        if (is_array($cookieHdrs) || $cookieHdrs instanceof ArrayIterator) {
            foreach ($cookieHdrs as $cookie) {
                $this->addCookie($cookie, $refUri);
            }
        } elseif (is_string($cookieHdrs)) {
            $this->addCookie($cookieHdrs, $refUri);
        }
    }

    /**
     * @param int $retAs
     * @return array|null
     */
    public function getAllCookies($retAs = self::COOKIE_OBJECT)
    {
        $cookies = $this->_flattenCookiesArray($this->cookies, $retAs);

        return $cookies;
    }

    /**
     * @param $uri
     * @param bool $matchSessionCookies
     * @param int $retAs
     * @param null $now
     * @return array|null
     * @throws \InvalidArgumentException
     */
    public function getMatchingCookies($uri, $matchSessionCookies = true, $retAs = self::COOKIE_OBJECT, $now = null)
    {
        if (is_string($uri)) {
            $uri = new HttpUri;
        } elseif (!$uri instanceof UriInterface) {
            throw new \InvalidArgumentException('Invalid URI string or object passed');
        }

        $host = $uri->getHost();

        if (empty($host)) {
            throw new \InvalidArgumentException('Invalid URI specified; does not contain a host');
        }

        $cookies = $this->_matchDomain($host);
        $cookies = $this->_matchPath($cookies, $uri->getPath());
        $cookies = $this->_flattenCookiesArray($cookies, self::COOKIE_OBJECT);

        $ret = [];

        foreach ($cookies as $cookie) {
            if ($cookie->match($uri, $matchSessionCookies, $now)) {
                $ret[] = $cookie;
            }
        }

        $ret = $this->_flattenCookiesArray($ret, $retAs);

        return $ret;
    }

    /**
     * @param $uri
     * @param $cookieName
     * @param int $retAs
     * @return bool
     * @throws \InvalidArgumentException
     */
    public function getCookie($uri, $cookieName, $retAs = self::COOKIE_OBJECT)
    {
        if (is_string($uri)) {
            $uri = new HttpUri;
        } elseif (!$uri instanceof UriInterface) {
            throw new \InvalidArgumentException('Invalid URI specified');
        }

        $host = $uri->getHost();

        if (empty($host)) {
            throw new \InvalidArgumentException('Invalid URI specified; host missing');
        }

        $path = $uri->getPath();
        $lastSlashPos = strrpos($path, '/') ?: 0;
        $path = substr($path, 0, $lastSlashPos);

        if (!$path) {
            $path = '/';
        }

        if (isset($this->cookies[$uri->getHost()][$path][$cookieName])) {

            $cookie = $this->cookies[$uri->getHost()][$path][$cookieName];

            switch ($retAs) {
                case self::COOKIE_OBJECT:
                    return $cookie;
                case self::COOKIE_STRING_ARRAY:
                case self::COOKIE_STRING_CONCAT:
                    return $cookie->__toString();
                default:
                    throw new \InvalidArgumentException(sprintf('Invalid value passed for $retAs: %s', $retAs));
            }
        }

        return false;
    }

    /**
     * @param $ptr
     * @param int $retAs
     * @return array|null|string
     */
    protected function _flattenCookiesArray($ptr, $retAs = self::COOKIE_OBJECT)
    {
        if (is_array($ptr)) {
            $ret = ($retAs == self::COOKIE_STRING_CONCAT ? '' : []);

            foreach ($ptr as $item) {
                if ($retAs === self::COOKIE_STRING_CONCAT) {
                    $ret .= $this->_flattenCookiesArray($item, $retAs);
                } else {
                    $ret = array_merge($ret, $this->_flattenCookiesArray($item, $retAs));
                }
            }

            return $ret;
        } elseif ($ptr instanceof SetCookie) {
            switch ($retAs) {
                case self::COOKIE_STRING_ARRAY:
                    return [$ptr->__toString()];
                case self::COOKIE_STRING_CONCAT:
                    return $ptr->__toString();
                case self::COOKIE_OBJECT:
                default:
                    return [$ptr];
            }
        }

        return null;
    }

    /**
     * @param $domain
     * @return array
     */
    protected function _matchDomain($domain)
    {
        $ret = [];

        foreach (array_keys($this->cookies) as $cdom) {
            if (SetCookie::matchCookieDomain($cdom, $domain)) {
                $ret[$cdom] = $this->cookies[$cdom];
            }
        }

        return $ret;
    }

    /**
     * @param $domains
     * @param $path
     * @return array
     */
    protected function _matchPath($domains, $path)
    {
        $ret = [];

        foreach ($domains as $dom => $pathsArray) {
            foreach (array_keys($pathsArray) as $cpath) {
                if (SetCookie::matchCookiePath($cpath, $path)) {
                    if (!isset($ret[$dom])) {
                        $ret[$dom] = [];
                    }
                    $ret[$dom][$cpath] = $pathsArray[$cpath];
                }
            }
        }

        return $ret;
    }

    /**
     * @param Response $response
     * @param $refUri
     * @return static
     */
    public static function fromResponse(Response $response, $refUri)
    {
        $jar = new static();
        $jar->addCookiesFromResponse($response, $refUri);

        return $jar;
    }

    /**
     * @return bool
     */
    public function isEmpty()
    {
        return count($this) === 0;
    }

    /**
     * @return $this
     */
    public function reset()
    {
        $this->cookies = $this->rawCookies = [];

        return $this;
    }
}
