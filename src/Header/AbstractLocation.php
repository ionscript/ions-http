<?php

namespace Ions\Http\Header;

use Ions\Uri\Http as HttpUri;

/**
 * Class AbstractLocation
 * @package Ions\Http\Header
 */
abstract class AbstractLocation implements HeaderInterface
{
    /**
     * @var
     */
    protected $uri;

    /**
     * @param $headerLine
     * @return static
     * @throws \InvalidArgumentException
     */
    public static function create($headerLine)
    {
        $locationHeader = new static();

        list($name, $uri) = Header::splitHeaderLine($headerLine);

        if (strtolower($name) !== strtolower($locationHeader->getName())) {
            throw new \InvalidArgumentException('Invalid header line for "' . $locationHeader->getName() . '" header string');
        }

        Header::assertValid($uri);

        $locationHeader->setUri(trim($uri));

        return $locationHeader;
    }

    /**
     * @param $uri
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setUri($uri)
    {
        if (is_string($uri)) {
            try {
                $uri = new HttpUri($uri);
            } catch (\InvalidArgumentException $e) {
                throw new \InvalidArgumentException(sprintf('Invalid URI passed as string (%s)', (string)$uri), $e->getCode(), $e);
            }
        } elseif (!($uri instanceof HttpUri)) {
            throw new \InvalidArgumentException('URI must be an instance of Ions\Uri\Http or a string');
        }

        $this->uri = $uri;

        return $this;
    }

    /**
     * @return string
     */
    public function getUri()
    {
        if ($this->uri instanceof HttpUri) {
            return $this->uri->toString();
        }

        return $this->uri;
    }

    /**
     * @return HttpUri
     */
    public function uri()
    {
        if ($this->uri === null || is_string($this->uri)) {
            $this->uri = new HttpUri($this->uri);
        }

        return $this->uri;
    }

    /**
     * @return string
     */
    public function getValue()
    {
        return $this->getUri();
    }

    /**
     * @return string
     */
    public function toString()
    {
        return $this->getName() . ': ' . $this->getUri();
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->toString();
    }
}
