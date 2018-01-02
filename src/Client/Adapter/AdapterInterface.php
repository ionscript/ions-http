<?php

namespace Ions\Http\Client\Adapter;

/**
 * Interface AdapterInterface
 * @package Ions\Http\Client\Adapter
 */
interface AdapterInterface
{
    /**
     * @param array $options
     * @return mixed
     */
    public function setOptions($options = []);

    /**
     * @param $host
     * @param int $port
     * @param bool $secure
     * @return mixed
     */
    public function connect($host, $port = 80, $secure = false);

    /**
     * @param $method
     * @param $url
     * @param string $httpVer
     * @param array $headers
     * @param string $body
     * @return mixed
     */
    public function write($method, $url, $httpVer = '1.1', $headers = [], $body = '');

    /**
     * @return mixed
     */
    public function read();

    /**
     * @return mixed
     */
    public function close();
}
