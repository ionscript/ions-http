<?php

namespace Ions\Http\Header;

use DateTime;
use Ions\Uri\Http as HttpUri;

/**
 * Class SetCookie
 * @package Ions\Http\Header
 */
class SetCookie implements HeaderInterface
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $value;

    /**
     * @var string
     */
    protected $version;

    /**
     * @var string
     */
    protected $maxAge;

    /**
     * @var string
     */
    protected $expires;

    /**
     * @var string
     */
    protected $domain;

    /**
     * @var string
     */
    protected $path;

    /**
     * @var bool
     */
    protected $secure;

    /**
     * @var bool
     */
    protected $quoteFieldValue = false;

    /**
     * @var
     */
    protected $httponly;

    /**
     * SetCookie constructor.
     * @param null $name
     * @param null $value
     * @param null $expires
     * @param null $path
     * @param null $domain
     * @param bool $secure
     * @param bool $httponly
     * @param null $maxAge
     * @param null $version
     */
    public function __construct(
        $name = null,
        $value = null,
        $expires = null,
        $path = null,
        $domain = null,
        $secure = false,
        $httponly = false,
        $maxAge = null,
        $version = null
    )
    {
        $this->type = 'Cookie';

        $this
            ->setName($name)
            ->setValue($value)
            ->setVersion($version)
            ->setMaxAge($maxAge)
            ->setDomain($domain)
            ->setExpires($expires)
            ->setPath($path)
            ->setSecure($secure)
            ->setHttpOnly($httponly);
    }

    /**
     * @param $headerLine
     * @param bool $bypassHeaderFieldName
     * @return array|mixed
     * @throws \InvalidArgumentException
     */
    public static function create($headerLine, $bypassHeaderFieldName = false)
    {
        static $setCookieProcessor = null;

        if ($setCookieProcessor === null) {
            $setCookieClass = get_called_class();

            $setCookieProcessor = function ($headerLine) use ($setCookieClass) {
                $header = new $setCookieClass();

                $keyValuePairs = preg_split('#;\s*#', $headerLine);

                foreach ($keyValuePairs as $keyValue) {
                    if (preg_match('#^(?P<headerKey>[^=]+)=\s*("?)(?P<headerValue>[^"]*)\2#', $keyValue, $matches)) {
                        $headerKey = $matches['headerKey'];

                        $headerValue = $matches['headerValue'];
                    } else {
                        $headerKey = $keyValue;

                        $headerValue = null;
                    }

                    if ($header->getName() === null) {
                        $header->setName($headerKey);

                        $header->setValue(urldecode($headerValue));

                        continue;
                    }

                    switch (str_replace(['-', '_'], '', strtolower($headerKey))) {
                        case 'expires':
                            $header->setExpires($headerValue);
                            break;
                        case 'domain':
                            $header->setDomain($headerValue);
                            break;
                        case 'path':
                            $header->setPath($headerValue);
                            break;
                        case 'secure':
                            $header->setSecure(true);
                            break;
                        case 'httponly':
                            $header->setHttponly(true);
                            break;
                        case 'version':
                            $header->setVersion((int)$headerValue);
                            break;
                        case 'maxage':
                            $header->setMaxAge((int)$headerValue);
                            break;
                        default:
                    }
                }

                return $header;
            };
        }

        list($name, $value) = Header::splitHeaderLine($headerLine);

        Header::assertValid($value);

        $name = strtolower($name) === 'set-cookie:' ? 'set-cookie' : $name;

        if (strtolower($name) !== 'set-cookie') {
            throw new \InvalidArgumentException('Invalid header line for Set-Cookie string: "' . $name . '"');
        }

        $multipleHeaders = preg_split('#(?<!Sun|Mon|Tue|Wed|Thu|Fri|Sat),\s*#', $value);

        if (count($multipleHeaders) <= 1) {
            return $setCookieProcessor(array_pop($multipleHeaders));
        } else {
            $headers = [];

            foreach ($multipleHeaders as $headerLine) {
                $headers[] = $setCookieProcessor($headerLine);
            }

            return $headers;
        }
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'Set-Cookie';
    }

    /**
     * @return string
     */
    public function getValue()
    {
        if ($this->getName() === '') {
            return '';
        }

        $value = urlencode($this->value);

        if ($this->hasQuoteValue()) {
            $value = '"' . $value . '"';
        }

        $fieldValue = $this->getName() . '=' . $value;

        $version = $this->getVersion();

        if ($version !== null) {
            $fieldValue .= '; Version=' . $version;
        }

        $maxAge = $this->getMaxAge();

        if ($maxAge !== null) {
            $fieldValue .= '; Max-Age=' . $maxAge;
        }

        $expires = $this->getExpires();

        if ($expires) {
            $fieldValue .= '; Expires=' . $expires;
        }

        $domain = $this->getDomain();

        if ($domain) {
            $fieldValue .= '; Domain=' . $domain;
        }

        $path = $this->getPath();

        if ($path) {
            $fieldValue .= '; Path=' . $path;
        }

        if ($this->isSecure()) {
            $fieldValue .= '; Secure';
        }

        if ($this->isHttponly()) {
            $fieldValue .= '; HttpOnly';
        }

        return $fieldValue;
    }

    /**
     * @param $name
     * @return $this
     */
    public function setName($name)
    {
        Header::assertValid($name);
        $this->name = $name;

        return $this;
    }

    /**
     * @param $value
     * @return $this
     */
    public function setValue($value)
    {
        $this->value = $value;

        return $this;
    }

    /**
     * @param $version
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setVersion($version)
    {
        if ($version !== null && !is_int($version)) {
            throw new \InvalidArgumentException('Invalid Version number specified');
        }

        $this->version = $version;

        return $this;
    }

    /**
     * @return string
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * @param $maxAge
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setMaxAge($maxAge)
    {
        if ($maxAge !== null && (!is_int($maxAge) || ($maxAge < 0))) {
            throw new \InvalidArgumentException('Invalid Max-Age number specified');
        }

        $this->maxAge = $maxAge;

        return $this;
    }

    /**
     * @return string
     */
    public function getMaxAge()
    {
        return $this->maxAge;
    }

    /**
     * @param $expires
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setExpires($expires)
    {
        if ($expires === null) {
            $this->expires = null;

            return $this;
        }

        if ($expires instanceof DateTime) {
            $expires = $expires->format(DateTime::COOKIE);
        }

        $tsExpires = $expires;

        if (is_string($expires)) {
            $tsExpires = strtotime($expires);

            if (!is_int($tsExpires) && PHP_INT_SIZE === 4) {
                $dateTime = new DateTime($expires);

                if ($dateTime->format('Y') > 2038) {
                    $tsExpires = PHP_INT_MAX;
                }
            }
        }

        if (!is_int($tsExpires) || $tsExpires < 0) {
            throw new \InvalidArgumentException('Invalid expires time specified');
        }

        $this->expires = $tsExpires;

        return $this;
    }

    /**
     * @param bool $inSeconds
     * @return null|string
     */
    public function getExpires($inSeconds = false)
    {
        if ($this->expires === null) {
            return null;
        }

        if ($inSeconds) {
            return $this->expires;
        }

        return gmdate('D, d-M-Y H:i:s', $this->expires) . ' GMT';
    }

    /**
     * @param $domain
     * @return $this
     */
    public function setDomain($domain)
    {
        Header::assertValid($domain);
        $this->domain = $domain;

        return $this;
    }

    /**
     * @return string
     */
    public function getDomain()
    {
        return $this->domain;
    }

    /**
     * @param $path
     * @return $this
     */
    public function setPath($path)
    {
        Header::assertValid($path);
        $this->path = $path;

        return $this;
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @param $secure
     * @return $this
     */
    public function setSecure($secure)
    {
        if (null !== $secure) {
            $secure = (bool)$secure;
        }

        $this->secure = $secure;

        return $this;
    }

    /**
     * @param $quotedValue
     * @return $this
     */
    public function setQuoteFieldValue($quotedValue)
    {
        $this->quoteFieldValue = (bool)$quotedValue;

        return $this;
    }

    /**
     * @return bool
     */
    public function isSecure()
    {
        return $this->secure;
    }

    /**
     * @param $httponly
     * @return $this
     */
    public function setHttponly($httponly)
    {
        if (null !== $httponly) {
            $httponly = (bool)$httponly;
        }

        $this->httponly = $httponly;

        return $this;
    }

    /**
     * @return mixed
     */
    public function isHttponly()
    {
        return $this->httponly;
    }

    /**
     * @param null $now
     * @return bool
     */
    public function isExpired($now = null)
    {
        if ($now === null) {
            $now = time();
        }

        return is_int($this->expires) && $this->expires < $now;
    }

    /**
     * @return bool
     */
    public function isSessionCookie()
    {
        return ($this->expires === null);
    }

    /**
     * @return bool
     */
    public function hasQuoteValue()
    {
        return $this->quoteFieldValue;
    }

    /**
     * @param $requestDomain
     * @param $path
     * @param bool $isSecure
     * @return bool
     */
    public function isValidForRequest($requestDomain, $path, $isSecure = false)
    {
        if ($this->getDomain() && (strrpos($requestDomain, $this->getDomain()) === false)) {
            return false;
        }

        if ($this->getPath() && (strpos($path, $this->getPath()) !== 0)) {
            return false;
        }

        if ($this->secure && $this->isSecure() !== $isSecure) {
            return false;
        }

        return true;
    }

    /**
     * @param $uri
     * @param bool $matchSessionCookies
     * @param null $now
     * @return bool
     * @throws \InvalidArgumentException
     */
    public function match($uri, $matchSessionCookies = true, $now = null)
    {
        if (is_string($uri)) {
            $uri = new HttpUri($uri);
        }

        if (!($uri->isValid() && ($uri->getScheme() === 'http' || $uri->getScheme() === 'https'))) {
            throw new \InvalidArgumentException('Passed URI is not a valid HTTP or HTTPS URI');
        }

        if ($this->secure && $uri->getScheme() !== 'https') {
            return false;
        }

        if ($this->isExpired($now)) {
            return false;
        }

        if ($this->isSessionCookie() && !$matchSessionCookies) {
            return false;
        }

        if (!self::matchCookieDomain($this->getDomain(), $uri->getHost())) {
            return false;
        }

        if (!self::matchCookiePath($this->getPath(), $uri->getPath())) {
            return false;
        }

        return true;
    }

    /**
     * @param $cookieDomain
     * @param $host
     * @return bool
     */
    public static function matchCookieDomain($cookieDomain, $host)
    {
        $cookieDomain = strtolower($cookieDomain);

        $host = strtolower($host);

        return $cookieDomain === $host || preg_match('/' . preg_quote($cookieDomain, null) . '$/', $host);
    }

    /**
     * @param $cookiePath
     * @param $path
     * @return bool
     */
    public static function matchCookiePath($cookiePath, $path)
    {
        return (strpos($path, $cookiePath) === 0);
    }

    /**
     * @return string
     */
    public function toString()
    {
        return 'Set-Cookie: ' . $this->getValue();
    }
}
