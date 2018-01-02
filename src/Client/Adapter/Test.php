<?php

namespace Ions\Http\Client\Adapter;

use Ions\Http\Response;

/**
 * Class Test
 * @package Ions\Http\Client\Adapter
 */
class Test implements AdapterInterface
{
    /**
     * @var array
     */
    protected $config = [];
    /**
     * @var array
     */
    protected $responses = ["HTTP/1.1 400 Bad Request\r\n\r\n"];
    /**
     * @var int
     */
    protected $responseIndex = 0;
    /**
     * @var bool
     */
    protected $nextRequestWillFail = false;

    /**
     * Test constructor.
     */
    public function __construct()
    {
    }

    /**
     * @param $flag
     * @return $this
     */
    public function setNextRequestWillFail($flag)
    {
        $this->nextRequestWillFail = (bool) $flag;

        return $this;
    }

    /**
     * @param array $options
     * @return void
     * @throws \InvalidArgumentException
     */
    public function setOptions($options = [])
    {
        if (! is_array($options)) {
            throw new \InvalidArgumentException(
                'Array expected, got ' . gettype($options)
            );
        }

        foreach ($options as $k => $v) {
            $this->config[strtolower($k)] = $v;
        }
    }


    /**
     * @param $host
     * @param int $port
     * @param bool $secure
     * @return void
     * @throws \RuntimeException
     */
    public function connect($host, $port = 80, $secure = false)
    {
        if ($this->nextRequestWillFail) {
            $this->nextRequestWillFail = false;
            throw new \RuntimeException('Request failed');
        }
    }

    /**
     * @param $method
     * @param $uri
     * @param string $httpVer
     * @param array $headers
     * @param string $body
     * @return string
     */
    public function write($method, $uri, $httpVer = '1.1', $headers = [], $body = '')
    {
        // Build request headers
        $path = $uri->getPath();

        if (empty($path)) {
            $path = '/';
        }

        if ($uri->hasQuery()) {
            $path .= '?' . $uri->getQuery();
        }

        $request = "{$method} {$path} HTTP/{$httpVer}\r\n";

        foreach ($headers as $k => $v) {
            if (is_string($k)) {
                $v = ucfirst($k) . ": $v";
            }
            $request .= "$v\r\n";
        }

        $request .= "\r\n" . $body;

        return $request;
    }

    /**
     * @return mixed
     */
    public function read()
    {
        if ($this->responseIndex >= count($this->responses)) {
            $this->responseIndex = 0;
        }
        return $this->responses[$this->responseIndex++];
    }

    /**
     * @return void
     */
    public function close()
    {
    }

    /**
     * @param $response
     */
    public function setResponse($response)
    {
        if ($response instanceof Response) {
            $response = $response->toString();
        }

        $this->responses = (array) $response;
        $this->responseIndex = 0;
    }

    /**
     * @param $response
     */
    public function addResponse($response)
    {
        if ($response instanceof Response) {
            $response = $response->toString();
        }

        $this->responses[] = $response;
    }

    /**
     * @param $index
     * @throws \OutOfRangeException
     */
    public function setResponseIndex($index)
    {
        if ($index < 0 || $index >= count($this->responses)) {
            throw new \OutOfRangeException(
                'Index out of range of response buffer size'
            );
        }
        $this->responseIndex = $index;
    }
}
