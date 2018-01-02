<?php

namespace Ions\Http\Client\Adapter;

use Ions\Http\Client\Client;

/**
 * Class Proxy
 * @package Ions\Http\Client\Adapter
 */
class Proxy extends Socket
{
    /**
     * @var array
     */
    protected $config = [
        'ssltransport'       => 'ssl',
        'sslcert'            => null,
        'sslpassphrase'      => null,
        'sslverifypeer'      => true,
        'sslcapath'          => null,
        'sslallowselfsigned' => false,
        'sslusecontext'      => false,
        'proxy_host'         => '',
        'proxy_port'         => 8080,
        'proxy_user'         => '',
        'proxy_pass'         => '',
        'proxy_auth'         => Client::AUTH_BASIC,
        'persistent'         => false
    ];

    /**
     * @var bool
     */
    protected $negotiated = false;

    /**
     * @param array $options
     * @return void
     */
    public function setOptions($options = [])
    {
        //enforcing that the proxy keys are set in the form proxy_*
        foreach ($options as $k => $v) {
            if (preg_match("/^proxy[a-z]+/", $k)) {
                $options['proxy_' . substr($k, 5, strlen($k))] = $v;
                unset($options[$k]);
            }
        }

        parent::setOptions($options);
    }

    /**
     * @param $host
     * @param int $port
     * @param bool $secure
     * @return void
     */
    public function connect($host, $port = 80, $secure = false)
    {
        // If no proxy is set, fall back to Socket adapter
        if (! $this->config['proxy_host']) {
            parent::connect($host, $port, $secure);
            return;
        }

        /* Url might require stream context even if proxy connection doesn't */
        if ($secure) {
            $this->config['sslusecontext'] = true;
        }

        // Connect (a non-secure connection) to the proxy server
        parent::connect(
            $this->config['proxy_host'],
            $this->config['proxy_port'],
            false
        );
    }

    /**
     * @param $method
     * @param $uri
     * @param string $httpVer
     * @param array $headers
     * @param string $body
     * @return mixed|string
     * @throws \RuntimeException
     */
    public function write($method, $uri, $httpVer = '1.1', $headers = [], $body = '')
    {
        // If no proxy is set, fall back to default Socket adapter
        if (! $this->config['proxy_host']) {
            return parent::write($method, $uri, $httpVer, $headers, $body);
        }

        // Make sure we're properly connected
        if (! $this->socket) {
            throw new \RuntimeException('Trying to write but we are not connected');
        }

        $host = $this->config['proxy_host'];
        $port = $this->config['proxy_port'];

        if ($this->connectedTo[0] != "tcp://$host" || $this->connectedTo[1] != $port) {
            throw new \RuntimeException(
                'Trying to write but we are connected to the wrong proxy server'
            );
        }

        // Add Proxy-Authorization header
        if ($this->config['proxy_user'] && ! isset($headers['proxy-authorization'])) {
            $headers['proxy-authorization'] = Client::encodeAuthHeader(
                $this->config['proxy_user'],
                $this->config['proxy_pass'],
                $this->config['proxy_auth']
            );
        }

        // if we are proxying HTTPS, preform CONNECT handshake with the proxy
        if ($uri->getScheme() == 'https' && (! $this->negotiated)) {
            $this->connectHandshake($uri->getHost(), $uri->getPort(), $httpVer, $headers);
            $this->negotiated = true;
        }

        // Save request method for later
        $this->method = $method;

        // Build request headers
        if ($this->negotiated) {
            $path = $uri->getPath();
            if ($uri->getQuery()) {
                $path .= '?' . $uri->getQuery();
            }
            $request = "$method $path HTTP/$httpVer\r\n";
        } else {
            $request = "$method $uri HTTP/$httpVer\r\n";
        }

        // Add all headers to the request string
        foreach ($headers as $k => $v) {
            if (is_string($k)) {
                $v = "$k: $v";
            }
            $request .= "$v\r\n";
        }

        if (is_resource($body)) {
            $request .= "\r\n";
        } else {
            // Add the request body
            $request .= "\r\n" . $body;
        }

        // Send the request
        $test  = fwrite($this->socket, $request);

        if (! $test) {
            throw new \RuntimeException('Error writing request to proxy server');
        }

        if (is_resource($body)) {
            if (stream_copy_to_stream($body, $this->socket) == 0) {
                throw new \RuntimeException('Error writing request to server');
            }
        }

        return $request;
    }

    /**
     * @param $host
     * @param int $port
     * @param string $httpVer
     * @param array $headers
     * @throws \RuntimeException
     */
    protected function connectHandshake($host, $port = 443, $httpVer = '1.1', array &$headers = [])
    {
        $request = "CONNECT $host:$port HTTP/$httpVer\r\n" .
                   "Host: " . $host . "\r\n";

        // Add the user-agent header
        if (isset($this->config['useragent'])) {
            $request .= "User-agent: " . $this->config['useragent'] . "\r\n";
        }

        // If the proxy-authorization header is set, send it to proxy but remove
        // it from headers sent to target host
        if (isset($headers['proxy-authorization'])) {
            $request .= "Proxy-authorization: " . $headers['proxy-authorization'] . "\r\n";
            unset($headers['proxy-authorization']);
        }

        $request .= "\r\n";

        // Send the request
        $test  = fwrite($this->socket, $request);

        if (! $test) {
            throw new \RuntimeException('Error writing request to proxy server');
        }

        // Read response headers only
        $response = '';
        $gotStatus = false;

        while ($line = fgets($this->socket)) {
            $gotStatus = $gotStatus || (strpos($line, 'HTTP') !== false);
            if ($gotStatus) {
                $response .= $line;
                if (! rtrim($line)) {
                    break;
                }
            }
        }

        // Check that the response from the proxy is 200
        if (Response::fromString($response)->getStatusCode() != 200) {
            throw new \RuntimeException(
                'Unable to connect to HTTPS proxy. Server response: ' . $response
            );
        }

        $modes = [
            STREAM_CRYPTO_METHOD_TLS_CLIENT,
            STREAM_CRYPTO_METHOD_SSLv3_CLIENT,
            STREAM_CRYPTO_METHOD_SSLv23_CLIENT,
            STREAM_CRYPTO_METHOD_SSLv2_CLIENT
        ];

        $success = false;
        foreach ($modes as $mode) {
            $success = stream_socket_enable_crypto($this->socket, true, $mode);
            if ($success) {
                break;
            }
        }

        if (! $success) {
            throw new \RuntimeException('Unable to connect to HTTPS server through proxy: could not negotiate secure connection.');
        }
    }

    /**
     * @return void
     */
    public function close()
    {
        parent::close();
        $this->negotiated = false;
    }

    /**
     * @return void
     */
    public function __destruct()
    {
        if ($this->socket) {
            $this->close();
        }
    }
}
