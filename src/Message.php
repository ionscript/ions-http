<?php

namespace Ions\Http;

use Ions\Std\Message as StdMessage;

/**
 * Class Message
 * @package Ions\Http
 */
abstract class Message extends StdMessage
{
    const VERSION_10 = '1.0';
    const VERSION_11 = '1.1';

    /**
     * @var string
     */
    protected $version = self::VERSION_11;

    /**
     * @var
     */
    protected $headers;

    /**
     * @param $version
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setVersion($version)
    {
        if ($version !== self::VERSION_10 && $version !== self::VERSION_11) {
            throw new \InvalidArgumentException(
                'Not valid or not supported HTTP version: ' . $version
            );
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
     * @param Headers $headers
     * @return $this
     */
    public function setHeaders(Headers $headers)
    {
        $this->headers = $headers;
        return $this;
    }

    /**
     * @return Headers $headers
     */
    public function getHeaders()
    {
        if ($this->headers === null || is_string($this->headers)) {
            $this->headers = is_string($this->headers) ? Headers::create($this->headers) : new Headers();
        }

        return $this->headers;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->toString();
    }
}
