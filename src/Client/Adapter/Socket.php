<?php

namespace Ions\Http\Client\Adapter;

use Ions\Http\Request;
use Ions\Http\Response;

/**
 * Class Socket
 * @package Ions\Http\Client\Adapter
 */
class Socket implements StreamInterface, AdapterInterface
{
    /**
     * @var array
     */
    protected static $sslCryptoTypes = [
        'ssl'   => STREAM_CRYPTO_METHOD_SSLv23_CLIENT,
        'sslv2' => STREAM_CRYPTO_METHOD_SSLv2_CLIENT,
        'sslv3' => STREAM_CRYPTO_METHOD_SSLv3_CLIENT,
        'tls'   => STREAM_CRYPTO_METHOD_TLS_CLIENT,
    ];

    /**
     * @var resource
     */
    protected $socket;

    /**
     * @var array
     */
    protected $connectedTo = [null, null];

    /**
     * @var
     */
    protected $outStream;

    /**
     * @var array
     */
    protected $config = [
        'persistent'            => false,
        'ssltransport'          => 'ssl',
        'sslcert'               => null,
        'sslpassphrase'         => null,
        'sslverifypeer'         => true,
        'sslcafile'             => null,
        'sslcapath'             => null,
        'sslallowselfsigned'    => false,
        'sslusecontext'         => false,
        'sslverifypeername'     => true,
    ];

    /**
     * @var
     */
    protected $method;

    /**
     * @var
     */
    protected $context;

    /**
     * Socket constructor.
     */
    public function __construct()
    {
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
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @param $context
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setStreamContext($context)
    {
        if (is_resource($context) && get_resource_type($context) === 'stream-context') {
            $this->context = $context;
        } elseif (is_array($context)) {
            $this->context = stream_context_create($context);
        } else {
            // Invalid parameter
            throw new \InvalidArgumentException(
                'Expecting either a stream context resource or array, got ' . gettype($context)
            );
        }

        return $this;
    }

    /**
     * @return resource
     */
    public function getStreamContext()
    {
        if (! $this->context) {
            $this->context = stream_context_create();
        }

        return $this->context;
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
        // If we are connected to the wrong host, disconnect first
        $connectedHost = (strpos($this->connectedTo[0], '://'))
            ? substr($this->connectedTo[0], (strpos($this->connectedTo[0], '://') + 3), strlen($this->connectedTo[0]))
            : $this->connectedTo[0];

        if ($connectedHost != $host || $this->connectedTo[1] != $port) {
            if (is_resource($this->socket)) {
                $this->close();
            }
        }

        // Now, if we are not connected, connect
        if (! is_resource($this->socket) || ! $this->config['keepalive']) {
            $context = $this->getStreamContext();

            if ($secure || $this->config['sslusecontext']) {
                if ($this->config['sslverifypeer'] !== null) {
                    if (! stream_context_set_option($context, 'ssl', 'verify_peer', $this->config['sslverifypeer'])) {
                        throw new \RuntimeException('Unable to set sslverifypeer option');
                    }
                }

                if ($this->config['sslcafile']) {
                    if (! stream_context_set_option($context, 'ssl', 'cafile', $this->config['sslcafile'])) {
                        throw new \RuntimeException('Unable to set sslcafile option');
                    }
                }

                if ($this->config['sslcapath']) {
                    if (! stream_context_set_option($context, 'ssl', 'capath', $this->config['sslcapath'])) {
                        throw new \RuntimeException('Unable to set sslcapath option');
                    }
                }

                if ($this->config['sslallowselfsigned'] !== null) {
                    if (! stream_context_set_option(
                        $context,
                        'ssl',
                        'allow_self_signed',
                        $this->config['sslallowselfsigned']
                    )) {
                        throw new \RuntimeException('Unable to set sslallowselfsigned option');
                    }
                }

                if ($this->config['sslcert'] !== null) {
                    if (! stream_context_set_option($context, 'ssl', 'local_cert', $this->config['sslcert'])) {
                        throw new \RuntimeException('Unable to set sslcert option');
                    }
                }

                if ($this->config['sslpassphrase'] !== null) {
                    if (! stream_context_set_option($context, 'ssl', 'passphrase', $this->config['sslpassphrase'])) {
                        throw new \RuntimeException('Unable to set sslpassphrase option');
                    }
                }

                if ($this->config['sslverifypeername'] !== null) {
                    if (! stream_context_set_option(
                        $context,
                        'ssl',
                        'verify_peer_name',
                        $this->config['sslverifypeername']
                    )) {
                        throw new \RuntimeException('Unable to set sslverifypeername option');
                    }
                }
            }

            $flags = STREAM_CLIENT_CONNECT;
            if ($this->config['persistent']) {
                $flags |= STREAM_CLIENT_PERSISTENT;
            }

            if (isset($this->config['connecttimeout'])) {
                $connectTimeout = $this->config['connecttimeout'];
            } else {
                $connectTimeout = $this->config['timeout'];
            }

            $this->socket = stream_socket_client(
                $host . ':' . $port,
                $errno,
                $errstr,
                (int) $connectTimeout,
                $flags,
                $context
            );

            if (! $this->socket) {
                $this->close();
                throw new \RuntimeException(
                    sprintf(
                        'Unable to connect to %s:%d',
                        $host,
                        $port
                    )
                );
            }

            // Set the stream timeout
            if (! stream_set_timeout($this->socket, (int) $this->config['timeout'])) {
                throw new \RuntimeException('Unable to set the connection timeout');
            }

            if ($secure || $this->config['sslusecontext']) {
                if ($this->config['ssltransport'] && isset(static::$sslCryptoTypes[$this->config['ssltransport']])) {
                    $sslCryptoMethod = static::$sslCryptoTypes[$this->config['ssltransport']];
                } else {
                    $sslCryptoMethod = STREAM_CRYPTO_METHOD_SSLv3_CLIENT;
                }


                $test  = stream_socket_enable_crypto($this->socket, true, $sslCryptoMethod);

                if (! $test) {
                    // Error handling is kind of difficult when it comes to SSL
                    $errorString = '';
                    if (extension_loaded('openssl')) {
                        while (($sslError = openssl_error_string()) != false) {
                            $errorString .= "; SSL error: $sslError";
                        }
                    }
                    $this->close();

                    if ((! $errorString) && $this->config['sslverifypeer']) {
                        // There's good chance our error is due to sslcapath not being properly set
                        if (! ($this->config['sslcafile'] || $this->config['sslcapath'])) {
                            $errorString = 'make sure the "sslcafile" or "sslcapath" option are properly set for the '
                                . 'environment.';
                        } elseif ($this->config['sslcafile'] && ! is_file($this->config['sslcafile'])) {
                            $errorString = 'make sure the "sslcafile" option points to a valid SSL certificate file';
                        } elseif ($this->config['sslcapath'] && ! is_dir($this->config['sslcapath'])) {
                            $errorString = 'make sure the "sslcapath" option points to a valid SSL certificate '
                                . 'directory';
                        }
                    }

                    if ($errorString) {
                        $errorString = ": $errorString";
                    }

                    throw new \RuntimeException(sprintf(
                        'Unable to enable crypto on TCP connection %s%s',
                        $host,
                        $errorString
                    ));
                }

                $host = $this->config['ssltransport'] . "://" . $host;
            } else {
                $host = 'tcp://' . $host;
            }

            // Update connectedTo
            $this->connectedTo = [$host, $port];
        }
    }

    /**
     * @param $method
     * @param $uri
     * @param string $httpVer
     * @param array $headers
     * @param string $body
     * @return string
     * @throws \RuntimeException
     */
    public function write($method, $uri, $httpVer = '1.1', $headers = [], $body = '')
    {
        // Make sure we're properly connected
        if (! $this->socket) {
            throw new \RuntimeException('Trying to write but we are not connected');
        }

        $host = $uri->getHost();
        $host = (strtolower($uri->getScheme()) === 'https' ? $this->config['ssltransport'] : 'tcp') . '://' . $host;
        if ($this->connectedTo[0] != $host || $this->connectedTo[1] != $uri->getPort()) {
            throw new \RuntimeException('Trying to write but we are connected to the wrong host');
        }

        // Save request method for later
        $this->method = $method;

        // Build request headers
        $path = $uri->getPath();
        if ($uri->getQuery()) {
            $path .= '?' . $uri->getQuery();
        }
        $request = "{$method} {$path} HTTP/{$httpVer}\r\n";
        foreach ($headers as $k => $v) {
            if (is_string($k)) {
                $v = ucfirst($k) . ": $v";
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
        if (false === $test) {
            throw new \RuntimeException('Error writing request to server');
        }

        if (is_resource($body)) {
            if (stream_copy_to_stream($body, $this->socket) === 0) {
                throw new \RuntimeException('Error writing request to server');
            }
        }

        return $request;
    }

    /**
     * @return mixed|string
     * @throws \RuntimeException
     */
    public function read()
    {
        // First, read headers only
        $response = '';
        $gotStatus = false;

        while (($line = fgets($this->socket)) !== false) {
            $gotStatus = $gotStatus || (strpos($line, 'HTTP') !== false);
            if ($gotStatus) {
                $response .= $line;
                if (rtrim($line) === '') {
                    break;
                }
            }
        }

        $this->_checkSocketReadTimeout();

        $responseObj = Response::create($response);

        $statusCode = $responseObj->getStatusCode();

        // Handle 100 and 101 responses internally by restarting the read again
        if ($statusCode == 100 || $statusCode == 101) {
            return $this->read();
        }

        $headers = $responseObj->getHeaders();

        if ($statusCode === 304 || $statusCode === 204 ||
            $this->method === Request::METHOD_HEAD) {
            $connection = $headers->get('connection');
            if ($connection && $connection->getValue() === 'close') {
                $this->close();
            }
            return $response;
        }

        $transferEncoding = $headers->get('Transfer-Encoding');
        $contentLength = $headers->get('Content-Length');
        if ($transferEncoding !== false) {
            if (strtolower($transferEncoding->getValue()) === 'chunked') {
                do {
                    $line  = fgets($this->socket);
                    $this->_checkSocketReadTimeout();

                    $chunk = $line;

                    // Figure out the next chunk size
                    $chunksize = trim($line);
                    if (! ctype_xdigit($chunksize)) {
                        $this->close();
                        throw new \RuntimeException('Invalid chunk size "' .
                            $chunksize . '" unable to read chunked body');
                    }

                    // Convert the hexadecimal value to plain integer
                    $chunksize = hexdec($chunksize);

                    // Read next chunk
                    $readTo = ftell($this->socket) + $chunksize;

                    do {
                        $currentPos = ftell($this->socket);
                        if ($currentPos >= $readTo) {
                            break;
                        }

                        if ($this->outStream) {
                            if (stream_copy_to_stream($this->socket, $this->outStream, $readTo - $currentPos) == 0) {
                                $this->_checkSocketReadTimeout();
                                break;
                            }
                        } else {
                            $line = fread($this->socket, $readTo - $currentPos);
                            if ($line === false || strlen($line) === 0) {
                                $this->_checkSocketReadTimeout();
                                break;
                            }
                            $chunk .= $line;
                        }
                    } while (! feof($this->socket));

                    $chunk .= fgets($this->socket);

                    $this->_checkSocketReadTimeout();

                    if (! $this->outStream) {
                        $response .= $chunk;
                    }
                } while ($chunksize > 0);
            } else {
                $this->close();
                throw new \RuntimeException('Cannot handle "' .
                    $transferEncoding->getValue() . '" transfer encoding');
            }

            // We automatically decode chunked-messages when writing to a stream
            // this means we have to disallow the Zend\Http\Response to do it again
            if ($this->outStream) {
                $response = str_ireplace("Transfer-Encoding: chunked\r\n", '', $response);
            }
        // Else, if we got the content-length header, read this number of bytes
        } elseif ($contentLength !== false) {
            // If we got more than one Content-Length header (see ZF-9404) use
            // the last value sent
            if (is_array($contentLength)) {
                $contentLength = $contentLength[count($contentLength) - 1];
            }
            $contentLength = $contentLength->getValue();

            $currentPos = ftell($this->socket);

            for ($readTo = $currentPos + $contentLength;
                 $readTo > $currentPos;
                 $currentPos = ftell($this->socket)) {
                if ($this->outStream) {
                    if (stream_copy_to_stream($this->socket, $this->outStream, $readTo - $currentPos) == 0) {
                        $this->_checkSocketReadTimeout();
                        break;
                    }
                } else {
                    $chunk = fread($this->socket, $readTo - $currentPos);
                    if ($chunk === false || strlen($chunk) === 0) {
                        $this->_checkSocketReadTimeout();
                        break;
                    }

                    $response .= $chunk;
                }

                // Break if the connection ended prematurely
                if (feof($this->socket)) {
                    break;
                }
            }

        // Fallback: just read the response until EOF
        } else {
            do {
                if ($this->outStream) {
                    if (stream_copy_to_stream($this->socket, $this->outStream) == 0) {
                        $this->_checkSocketReadTimeout();
                        break;
                    }
                } else {
                    $buff = fread($this->socket, 8192);
                    if ($buff === false || strlen($buff) === 0) {
                        $this->_checkSocketReadTimeout();
                        break;
                    } else {
                        $response .= $buff;
                    }
                }
            } while (feof($this->socket) === false);

            $this->close();
        }

        $connection = $headers->get('connection');
        if ($connection && $connection->getValue() === 'close') {
            $this->close();
        }

        return $response;
    }

    /**
     * @return void
     */
    public function close()
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }
        $this->socket = null;
        $this->connectedTo = [null, null];
    }

    /**
     * @return void
     * @throws \RuntimeException
     */
    protected function _checkSocketReadTimeout()
    {
        // @codingStandardsIgnoreEnd
        if ($this->socket) {
            $info = stream_get_meta_data($this->socket);
            $timedout = $info['timed_out'];
            if ($timedout) {
                $this->close();
                throw new \RuntimeException(
                    "Read timed out after {$this->config['timeout']} seconds"
                );
            }
        }
    }

    /**
     * @param $stream
     * @return $this
     */
    public function setOutputStream($stream)
    {
        $this->outStream = $stream;
        return $this;
    }

    /**
     * @return void
     */
    public function __destruct()
    {
        if (! $this->config['persistent']) {
            if ($this->socket) {
                $this->close();
            }
        }
    }
}
