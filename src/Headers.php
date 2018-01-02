<?php

namespace Ions\Http;


use Ions\Http\Header;

/**
 * Class Headers
 * @package Ions\Http
 */
class Headers
{
    const HEADERS = [
        'accept' => Header\Accept::class,
        'acceptcharset' => Header\AcceptCharset::class,
        'acceptencoding' => Header\AcceptEncoding::class,
        'acceptlanguage' => Header\AcceptLanguage::class,
        'age' => Header\Age::class,
        'allow' => Header\Allow::class,
        'cachecontrol' => Header\CacheControl::class,
        'contentsecuritypolicy' => Header\ContentSecurityPolicy::class,
        'contenttype' => Header\ContentType::class,
        'cookie' => Header\Cookie::class,
        'date' => Header\Date::class,
        'expires' => Header\Expires::class,
        'origin' => Header\Origin::class,
        'referer' => Header\Referer::class,
        'setcookie' => Header\SetCookie::class
    ];

    /**
     * @var array
     */
    protected $headers = [];

    /**
     * @param $string
     * @return static
     * @throws \RuntimeException
     */
    public static function create($string)
    {
        $headers = new static();

        $current = [];
        $emptyLine = 0;

        foreach (explode("\r\n", $string) as $line) {

            if (preg_match('/^\s*$/', $line)) {

                ++$emptyLine;

                if ($emptyLine > 2) {
                    throw new \RuntimeException('Malformed header detected');
                }

                continue;
            }

            if ($emptyLine) {
                throw new \RuntimeException('Malformed header detected');
            }

            if (preg_match('/^(?P<name>[^()><@,;:\"\\/\[\]?={} \t]+):.*$/', $line, $matches)) {

                if ($current) {

                    $key = static::createKey($current['name']);

                    if (array_key_exists($key, static::HEADERS)) {
                        $class = static::HEADERS[$key];
                        $headers->headers[$key] = $class::create($current['line']);
                    } else {
                        $headers->headers[$key] = Header\Header::create($current['line']);
                    }
                }

                $current = [
                    'name' => $matches['name'],
                    'line' => trim($line)
                ];

                continue;
            }

            if (preg_match("/^[ \t][^\r\n]*$/", $line, $matches)) {
                
                $current['line'] .= trim($line);
                
                continue;
            }

            throw new \RuntimeException(sprintf('Line "%s" does not match header format!', $line));
        }

        if ($current) {

            $key = static::createKey($current['name']);

            if (array_key_exists($key, static::HEADERS)) {
                $class = static::HEADERS[$key];
                $headers->headers[$key] = $class::create($current['line']);
            } else {
                $headers->headers[$key] = Header\Header::create($current['line']);
            }
        }

        return $headers;
    }

    /**
     * @param $headers
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function addHeaders($headers)
    {
        if (!is_array($headers)) {
            throw new \InvalidArgumentException(sprintf(
                'Expected array; received "%s"',
                (is_object($headers) ? get_class($headers) : gettype($headers))
            ));
        }

        foreach ($headers as $name => $value) {
            if (is_string($name) && is_string($value)) {
                $this->addHeader(new Header\Header($name, $value));
            } elseif ($value instanceof Header\HeaderInterface) {
                $this->addHeader($value);
            }
        }

        return $this;
    }

    /**
     * @param Header\HeaderInterface $header
     * @return $this
     */
    public function addHeader(Header\HeaderInterface $header)
    {
        $this->headers[static::createKey($header->getName())] = $header;

        return $this;
    }

    /**
     * @param Header\HeaderInterface $header
     * @return bool
     */
    public function removeHeader(Header\HeaderInterface $header)
    {
        $key = static::createKey($header->getName());

        if (array_key_exists($key, $this->headers)) {
            unset($this->headers[$key]);
            
            return true;
        }

        return false;
    }

    /**
     * @return $this
     */
    public function clearHeaders()
    {
        $this->headers = [];

        return $this;
    }

    /**
     * @param $name
     * @return bool|mixed
     */
    public function get($name)
    {
        if (!array_key_exists(static::createKey($name), $this->headers)) {
            return false;
        }

        return $this->headers[static::createKey($name)];
    }

    /**
     * @param $name
     * @return bool
     */
    public function has($name)
    {
        return array_key_exists(static::createKey($name), $this->headers);
    }

    /**
     * @return bool
     */
    public function valid()
    {
        return (bool)current($this->headers);
    }

    /**
     * @return mixed
     */
    public function current()
    {
        return current($this->headers);
    }

    /**
     * @return int
     */
    public function count()
    {
        return count($this->headers);
    }

    /**
     * @return string
     */
    public function toString()
    {
        $headers = '';

        foreach ($this->toArray() as $fieldName => $fieldValue) {

            if (is_array($fieldValue)) {

                foreach ($fieldValue as $value) {
                    $headers .= $fieldName . ': ' . $value . "\r\n";
                }

                continue;
            }

            $headers .= $fieldName . ': ' . $fieldValue . "\r\n";
        }

        return $headers;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        $headers = [];

        foreach ($this->headers as $header) {

            if ($header instanceof Header\HeaderInterface) {
                
                $name = $header->getName();

                if (!isset($headers[$name])) {
                    $headers[$name] = [];
                }

                $headers[$name][] = $header->getValue();
            }
        }

        return $headers;
    }

    /**
     * @param $name
     * @return mixed
     */
    protected static function createKey($name)
    {
        return str_replace(['-', '_', ' ', '.'], '', strtolower($name));
    }
}
