<?php

namespace Ions\Http\Header;

use Ions\Http\Request;

/**
 * Class Allow
 * @package Ions\Http\Header
 */
class Allow implements HeaderInterface
{
    /**
     * @var array
     */
    protected $methods = [
        Request::METHOD_OPTIONS => false,
        Request::METHOD_GET => true,
        Request::METHOD_HEAD => false,
        Request::METHOD_POST => true,
        Request::METHOD_PUT => false,
        Request::METHOD_DELETE => false,
        Request::METHOD_TRACE => false,
        Request::METHOD_CONNECT => false,
        Request::METHOD_PATCH => false
    ];

    /**
     * @param $headerLine
     * @return static
     * @throws \InvalidArgumentException
     */
    public static function create($headerLine)
    {
        list($name, $value) = Header::splitHeaderLine($headerLine);

        if (strtolower($name) !== 'allow') {
            throw new \InvalidArgumentException('Invalid header line for Allow string: "' . $name . '"');
        }

        $header = new static();

        $header->disallowMethods(array_keys($header->getAllMethods()));

        $header->allowMethods(explode(',', $value));

        return $header;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'Allow';
    }

    /**
     * @return string
     */
    public function getValue()
    {
        return implode(', ', array_keys($this->methods, true, true));
    }

    /**
     * @return array
     */
    public function getAllMethods()
    {
        return $this->methods;
    }

    /**
     * @return array
     */
    public function getAllowedMethods()
    {
        return array_keys($this->methods, true, true);
    }

    /**
     * @param $allowedMethods
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function allowMethods($allowedMethods)
    {
        foreach ((array)$allowedMethods as $method) {
            $method = trim(strtoupper($method));

            if (preg_match('/\s/', $method)) {
                throw new \InvalidArgumentException(sprintf('Unable to whitelist method; "%s" is not a valid method', $method));
            }

            $this->methods[$method] = true;
        }

        return $this;
    }

    /**
     * @param $disallowedMethods
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function disallowMethods($disallowedMethods)
    {
        foreach ((array)$disallowedMethods as $method) {
            $method = trim(strtoupper($method));
            if (preg_match('/\s/', $method)) {
                throw new \InvalidArgumentException(sprintf('Unable to blacklist method; "%s" is not a valid method', $method));
            }
            $this->methods[$method] = false;
        }
        return $this;
    }

    /**
     * @param $disallowedMethods
     * @return Allow
     */
    public function denyMethods($disallowedMethods)
    {
        return $this->disallowMethods($disallowedMethods);
    }

    /**
     * @param $method
     * @return mixed
     */
    public function isAllowedMethod($method)
    {
        $method = trim(strtoupper($method));

        if (!isset($this->methods[$method])) {
            $this->methods[$method] = false;
        }
        return $this->methods[$method];
    }

    /**
     * @return string
     */
    public function toString()
    {
        return 'Allow: ' . $this->getValue();
    }
}
