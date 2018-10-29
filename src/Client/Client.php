<?php

namespace Ions\Http\Client;

use ArrayIterator;
use Ions\Http\Client\Adapter\AdapterInterface;
use Ions\Http\Client\Adapter\Curl;
use Ions\Http\Client\Adapter\Socket;
use Ions\Http\Client\Adapter\StreamInterface;
use Ions\Http\Header\Cookie;
use Ions\Http\Header\SetCookie;
use Ions\Http\Headers;
use Ions\Http\Request;
use Ions\Std\RequestInterface;
use Ions\Http\Response;
use Ions\Std\ResponseInterface;
use Ions\Uri\Uri;
use Ions\Uri\UriInterface;

/**
 * Class Client
 * @package Ions\Http\Client
 */
class Client
{
    const AUTH_BASIC = 'basic';
    const AUTH_DIGEST = 'digest';

    const ENC_URLENCODED = 'application/x-www-form-urlencoded';
    const ENC_FORMDATA = 'multipart/form-data';

    const DIGEST_REALM = 'realm';
    const DIGEST_QOP = 'qop';
    const DIGEST_NONCE = 'nonce';
    const DIGEST_OPAQUE = 'opaque';
    const DIGEST_NC = 'nc';
    const DIGEST_CNONCE = 'cnonce';

    /**
     * @var
     */
    protected $response;

    /**
     * @var
     */
    protected $request;

    /**
     * @var
     */
    protected $adapter;

    /**
     * @var array
     */
    protected $auth = [];

    /**
     * @var string
     */
    protected $streamName;

    /**
     * @var array
     */
    protected $cookies = [];

    /**
     * @var string
     */
    protected $encType = '';

    /**
     * @var null
     */
    protected $lastRawRequest;

    /**
     * @var null
     */
    protected $lastRawResponse;

    /**
     * @var int
     */
    protected $redirectCounter = 0;

    /**
     * @var array
     */
    protected $config = [
        'maxredirects' => 5,
        'strictredirects' => false,
        'useragent' => Client::class,
        'timeout' => 10,
        'connecttimeout' => null,
        'adapter' => Socket::class,
        'httpversion' => Request::VERSION_11,
        'storeresponse' => true,
        'keepalive' => false,
        'outputstream' => false,
        'encodecookies' => true,
        'argseparator' => null,
        'rfc3986strict' => false,
        'sslcafile' => null,
        'sslcapath' => null,
    ];

    /**
     * @var null
     */
    protected static $fileInfoDb;

    /**
     * Client constructor.
     * @param null $uri
     * @param null $options
     */
    public function __construct($uri = null, $options = null)
    {
        if ($uri !== null) {
            $this->setUri($uri);
        }
        if ($options !== null) {
            $this->setOptions($options);
        }
    }

    /**
     * @param array $options
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setOptions($options = [])
    {
        if (!is_array($options)) {
            throw new \InvalidArgumentException('Config parameter is not valid');
        }

        foreach ($options as $k => $v) {
            $this->config[str_replace(['-', '_', ' ', '.'], '', strtolower($k))] = $v;
        }

        if ($this->adapter instanceof AdapterInterface) {
            $this->adapter->setOptions($options);
        }

        return $this;
    }

    /**
     * @param $adapter
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setAdapter($adapter)
    {
        if (is_string($adapter)) {
            if (!class_exists($adapter)) {
                throw new \InvalidArgumentException(
                    'Unable to locate adapter class "' . $adapter . '"'
                );
            }
            $adapter = new $adapter;
        }

        if (!$adapter instanceof AdapterInterface) {
            throw new \InvalidArgumentException('Passed adapter is not a HTTP connection adapter');
        }

        $this->adapter = $adapter;
        $config = $this->config;
        unset($config['adapter']);
        $this->adapter->setOptions($config);
        return $this;
    }

    /**
     * @return mixed
     */
    public function getAdapter()
    {
        if (!$this->adapter) {
            $this->setAdapter($this->config['adapter']);
        }

        return $this->adapter;
    }

    /**
     * @param Request $request
     * @return $this
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;
        return $this;
    }

    /**
     * @return Request
     */
    public function getRequest()
    {
        if (empty($this->request)) {
            $this->request = new Request;
        }
        return $this->request;
    }

    /**
     * @param Response $response
     * @return $this
     */
    public function setResponse(Response $response)
    {
        $this->response = $response;
        return $this;
    }

    /**
     * @return Response
     */
    public function getResponse()
    {
        if (empty($this->response)) {
            $this->response = new Response;
        }
        return $this->response;
    }

    /**
     * @return null
     */
    public function getLastRawRequest()
    {
        return $this->lastRawRequest;
    }

    /**
     * @return null
     */
    public function getLastRawResponse()
    {
        return $this->lastRawResponse;
    }

    /**
     * @return int
     */
    public function getRedirectionCount()
    {
        return $this->redirectCounter;
    }

    /**
     * @param $uri
     * @return $this
     */
    public function setUri($uri)
    {
        if (!empty($uri)) {
            $lastHost = $this->getRequest()->getUri()->getHost();
            $this->getRequest()->setUri($uri);

            $nextHost = $this->getRequest()->getUri()->getHost();
            if (!preg_match('/' . preg_quote($lastHost, '/') . '$/i', $nextHost)) {
                $this->clearAuth();
            }

            if ($this->getUri()->getUser() && $this->getUri()->getPassword()) {
                $this->setAuth($this->getUri()->getUser(), $this->getUri()->getPassword());
            }

            if (!$this->getUri()->getPort()) {
                $this->getUri()->setPort(($this->getUri()->getScheme() === 'https' ? 443 : 80));
            }
        }
        return $this;
    }

    /**
     * @return \Ions\Uri\Http
     */
    public function getUri()
    {
        return $this->getRequest()->getUri();
    }

    /**
     * @param $method
     * @return $this
     */
    public function setMethod($method)
    {
        $method = $this->getRequest()->setMethod($method)->getMethod();

        if (empty($this->encType)
            && in_array(
                $method,
                [
                    Request::METHOD_POST,
                    Request::METHOD_PUT,
                    Request::METHOD_DELETE,
                    Request::METHOD_PATCH,
                    Request::METHOD_OPTIONS,
                ],
                true
            )
        ) {
            $this->setEncType(self::ENC_URLENCODED);
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getMethod()
    {
        return $this->getRequest()->getMethod();
    }

    /**
     * @param $argSeparator
     * @return $this
     */
    public function setArgSeparator($argSeparator)
    {
        $this->setOptions(["argseparator" => $argSeparator]);
        return $this;
    }

    /**
     * @return mixed|string
     */
    public function getArgSeparator()
    {
        $argSeparator = $this->config['argseparator'];
        if (empty($argSeparator)) {
            $argSeparator = ini_get('arg_separator.output');
            $this->setArgSeparator($argSeparator);
        }
        return $argSeparator;
    }

    /**
     * @param $encType
     * @param null $boundary
     * @return $this
     */
    public function setEncType($encType, $boundary = null)
    {
        if (null === $encType || empty($encType)) {
            $this->encType = null;
            return $this;
        }

        if (!empty($boundary)) {
            $encType .= sprintf('; boundary=%s', $boundary);
        }

        $this->encType = $encType;
        return $this;
    }

    /**
     * @return string
     */
    public function getEncType()
    {
        return $this->encType;
    }

    /**
     * @param $body
     * @return $this
     */
    public function setRawBody($body)
    {
        $this->getRequest()->setContent($body);
        return $this;
    }

    /**
     * @param array $post
     * @return $this
     */
    public function setParameterPost(array $post)
    {
        $this->getRequest()->getPost()->fromArray($post);
        return $this;
    }

    /**
     * @param array $query
     * @return $this
     */
    public function setParameterGet(array $query)
    {
        $this->getRequest()->getQuery()->fromArray($query);
        return $this;
    }

    /**
     * @param bool $clearCookies
     * @return $this
     */
    public function resetParameters($clearCookies = false)
    {
        $clearAuth = true;
        if (func_num_args() > 1) {
            $clearAuth = func_get_arg(1);
        }

        $uri = $this->getUri();

        $this->streamName = null;
        $this->encType = null;
        $this->request = null;
        $this->response = null;
        $this->lastRawRequest = null;
        $this->lastRawResponse = null;

        $this->setUri($uri);

        if ($clearCookies) {
            $this->clearCookies();
        }

        if ($clearAuth) {
            $this->clearAuth();
        }

        return $this;
    }

    /**
     * @return array
     */
    public function getCookies()
    {
        return $this->cookies;
    }

    /**
     * @param $cookie
     * @return bool|string
     */
    protected function getCookieId($cookie)
    {
        if (($cookie instanceof SetCookie) || ($cookie instanceof Cookie)) {
            return $cookie->getName() . $cookie->getDomain() . $cookie->getPath();
        }
        return false;
    }

    /**
     * @param $cookie
     * @param null $value
     * @param null $expire
     * @param null $path
     * @param null $domain
     * @param bool $secure
     * @param bool $httponly
     * @param null $maxAge
     * @param null $version
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function addCookie(
        $cookie,
        $value = null,
        $expire = null,
        $path = null,
        $domain = null,
        $secure = false,
        $httponly = true,
        $maxAge = null,
        $version = null
    )
    {
        if (is_array($cookie) || $cookie instanceof ArrayIterator) {
            foreach ($cookie as $setCookie) {
                if ($setCookie instanceof SetCookie) {
                    $this->cookies[$this->getCookieId($setCookie)] = $setCookie;
                } else {
                    throw new \InvalidArgumentException('The cookie parameter is not a valid Set-Cookie type');
                }
            }
        } elseif (is_string($cookie) && $value !== null) {
            $setCookie = new SetCookie(
                $cookie,
                $value,
                $expire,
                $path,
                $domain,
                $secure,
                $httponly,
                $maxAge,
                $version
            );
            $this->cookies[$this->getCookieId($setCookie)] = $setCookie;
        } elseif ($cookie instanceof SetCookie) {
            $this->cookies[$this->getCookieId($cookie)] = $cookie;
        } else {
            throw new \InvalidArgumentException('Invalid parameter type passed as Cookie');
        }
        return $this;
    }

    /**
     * @param $cookies
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setCookies($cookies)
    {
        if (is_array($cookies)) {
            $this->clearCookies();
            foreach ($cookies as $name => $value) {
                $this->addCookie($name, $value);
            }
        } else {
            throw new \InvalidArgumentException('Invalid cookies passed as parameter, it must be an array');
        }
        return $this;
    }

    /**
     * @return void
     */
    public function clearCookies()
    {
        $this->cookies = [];
    }

    /**
     * @param $headers
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setHeaders($headers)
    {
        if (is_array($headers)) {
            $newHeaders = new Headers();
            $newHeaders->addHeaders($headers);
            $this->getRequest()->setHeaders($newHeaders);
        } elseif ($headers instanceof Headers) {
            $this->getRequest()->setHeaders($headers);
        } else {
            throw new \InvalidArgumentException('Invalid parameter headers passed');
        }
        return $this;
    }

    /**
     * @param $name
     * @return bool
     */
    public function hasHeader($name)
    {
        $headers = $this->getRequest()->getHeaders();

        if ($headers instanceof Headers) {
            return $headers->has($name);
        }

        return false;
    }

    /**
     * @param $name
     * @return bool
     */
    public function getHeader($name)
    {
        $headers = $this->getRequest()->getHeaders();

        if ($headers instanceof Headers) {
            if ($headers->has($name)) {
                return $headers->get($name)->getValue();
            }
        }
        return false;
    }

    /**
     * @param bool $streamfile
     * @return $this
     */
    public function setStream($streamfile = true)
    {
        $this->setOptions(["outputstream" => $streamfile]);
        return $this;
    }

    /**
     * @return mixed|string
     */
    public function getStream()
    {
        if (null !== $this->streamName) {
            return $this->streamName;
        }

        return $this->config['outputstream'];
    }

    /**
     * @return bool|resource
     * @throws \RuntimeException
     */
    protected function openTempStream()
    {
        $this->streamName = $this->config['outputstream'];

        if (!is_string($this->streamName)) {
            $this->streamName = tempnam(
                isset($this->config['streamtmpdir']) ? $this->config['streamtmpdir'] : sys_get_temp_dir(),
                Client::class
            );
        }

        $fp = fopen($this->streamName, 'w+b');

        if (false === $fp) {
            if ($this->adapter instanceof AdapterInterface) {
                $this->adapter->close();
            }
            throw new \RuntimeException("Could not open temp file {$this->streamName}");
        }

        return $fp;
    }

    /**
     * @param $user
     * @param $password
     * @param string $type
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setAuth($user, $password, $type = self::AUTH_BASIC)
    {
        if (!defined('static::AUTH_' . strtoupper($type))) {
            throw new \InvalidArgumentException("Invalid or not supported authentication type: '$type'");
        }

        if (empty($user)) {
            throw new \InvalidArgumentException('The username cannot be empty');
        }

        $this->auth = [
            'user' => $user,
            'password' => $password,
            'type' => $type
        ];

        return $this;
    }

    /**
     * @return void
     */
    public function clearAuth()
    {
        $this->auth = [];
    }

    /**
     * @param $user
     * @param $password
     * @param string $type
     * @param array $digest
     * @param null $entityBody
     * @return bool|string
     * @throws \InvalidArgumentException
     */
    protected function calcAuthDigest($user, $password, $type = self::AUTH_BASIC, $digest = [], $entityBody = null)
    {
        if (!defined('self::AUTH_' . strtoupper($type))) {
            throw new \InvalidArgumentException("Invalid or not supported authentication type: '$type'");
        }
        $response = false;
        switch (strtolower($type)) {
            case self::AUTH_BASIC:
                // In basic authentication, the user name cannot contain ":"
                if (strpos($user, ':') !== false) {
                    throw new \InvalidArgumentException(
                        "The user name cannot contain ':' in Basic HTTP authentication"
                    );
                }
                $response = base64_encode($user . ':' . $password);
                break;
            case self::AUTH_DIGEST:
                if (empty($digest)) {
                    throw new \InvalidArgumentException('The digest cannot be empty');
                }
                foreach ($digest as $key => $value) {
                    if (!defined('self::DIGEST_' . strtoupper($key))) {
                        throw new \InvalidArgumentException(
                            "Invalid or not supported digest authentication parameter: '$key'"
                        );
                    }
                }
                $ha1 = md5($user . ':' . $digest['realm'] . ':' . $password);
                if (empty($digest['qop']) || strtolower($digest['qop']) === 'auth') {
                    $ha2 = md5($this->getMethod() . ':' . $this->getUri()->getPath());
                } elseif (strtolower($digest['qop']) === 'auth-int') {
                    if (empty($entityBody)) {
                        throw new \InvalidArgumentException(
                            'I cannot use the auth-int digest authentication without the entity body'
                        );
                    }
                    $ha2 = md5($this->getMethod() . ':' . $this->getUri()->getPath() . ':' . md5($entityBody));
                }
                if (empty($digest['qop'])) {
                    $response = md5($ha1 . ':' . $digest['nonce'] . ':' . $ha2);
                } else {
                    $response = md5($ha1 . ':' . $digest['nonce'] . ':' . $digest['nc']
                        . ':' . $digest['cnonce'] . ':' . $digest['qoc'] . ':' . $ha2);
                }
                break;
        }
        return $response;
    }

    /**
     * @param RequestInterface $request
     * @param ResponseInterface|null $response
     * @return ResponseInterface|static
     */
    public function dispatch(RequestInterface $request, ResponseInterface $response = null)
    {
        $response = $this->send($request);
        return $response;
    }

    /**
     * @param Request|null $request
     * @return static
     * @throws \RuntimeException
     */
    public function send(Request $request = null)
    {
        if ($request !== null) {
            $this->setRequest($request);
        }

        $this->redirectCounter = 0;

        $adapter = $this->getAdapter();

        do {
            // uri
            $uri = $this->getUri();

            // query
            $query = $this->getRequest()->getQuery();

            if (!empty($query)) {
                $queryArray = $query->toArray();

                if (!empty($queryArray)) {
                    $newUri = $uri->toString();
                    $queryString = http_build_query($queryArray, null, $this->getArgSeparator());

                    if ($this->config['rfc3986strict']) {
                        $queryString = str_replace('+', '%20', $queryString);
                    }

                    if (strpos($newUri, '?') !== false) {
                        $newUri .= $this->getArgSeparator() . $queryString;
                    } else {
                        $newUri .= '?' . $queryString;
                    }

                    $uri = new Uri($newUri);
                }
            }

            if (!$uri->getPort()) {
                $uri->setPort($uri->getScheme() === 'https' ? 443 : 80);
            }

            // method
            $method = $this->getRequest()->getMethod();

            // this is so the correct Encoding Type is set
            $this->setMethod($method);

            // body
            $body = $this->prepareBody();

            // headers
            $headers = $this->prepareHeaders($body, $uri);

            $secure = $uri->getScheme() === 'https';

            // cookies
            $cookie = $this->prepareCookies($uri->getHost(), $uri->getPath(), $secure);
            if ($cookie->getValue()) {
                $headers['Cookie'] = $cookie->getValue();
            }

            if (is_resource($body) && !($adapter instanceof StreamInterface)) {
                throw new \RuntimeException('Adapter does not support streaming');
            }

            $response = $this->doRequest($uri, $method, $secure, $headers, $body);

            if (!$response) {
                throw new \RuntimeException('Unable to read response, or response is empty');
            }

            if ($this->config['storeresponse']) {
                $this->lastRawResponse = $response;
            } else {
                $this->lastRawResponse = null;
            }

            if ($this->config['outputstream']) {
                $stream = $this->getStream();
                if (!is_resource($stream) && is_string($stream)) {
                    $stream = fopen($stream, 'r');
                }
                $streamMetaData = stream_get_meta_data($stream);
                if ($streamMetaData['seekable']) {
                    rewind($stream);
                }

                $adapter->setOutputStream(null);
                $response = Stream::fromStream($response, $stream);
                $response->setStreamName($this->streamName);
                if (!is_string($this->config['outputstream'])) {
                    $response->setCleanup(true);
                }
            } else {
                $response = Response::create($response);
            }

            $setCookies = $response->getCookie();
            if (!empty($setCookies)) {
                $this->addCookie($setCookies);
            }

            if ($response->isRedirect() && ($response->getHeaders()->has('Location'))) {
                $location = trim($response->getHeaders()->get('Location')->getValue());

                if ($response->getStatusCode() === 303 ||
                    ((!$this->config['strictredirects']) && ($response->getStatusCode() == 302 ||
                            $response->getStatusCode() == 301))
                ) {
                    $this->resetParameters(false, false);
                    $this->setMethod(Request::METHOD_GET);
                }

                if (($scheme = substr($location, 0, 6)) &&
                    ($scheme === 'http:/' || $scheme === 'https:')
                ) {
                    $this->setUri($location);
                } else {
                    if (strpos($location, '?') !== false) {
                        list($location, $query) = explode('?', $location, 2);
                    } else {
                        $query = '';
                    }
                    $this->getUri()->setQuery($query);

                    if (strpos($location, '/') === 0) {
                        $this->getUri()->setPath($location);
                    } else {
                        $path = $this->getUri()->getPath();
                        $path = rtrim(substr($path, 0, strrpos($path, '/')), '/');
                        $this->getUri()->setPath($path . '/' . $location);
                    }
                }
                ++$this->redirectCounter;
            } else {
                break;
            }
        } while ($this->redirectCounter <= $this->config['maxredirects']);

        $this->response = $response;
        return $response;
    }

    /**
     * @return $this
     */
    public function reset()
    {
        $this->resetParameters();
        $this->clearAuth();
        $this->clearCookies();

        return $this;
    }

    /**
     * @param $filename
     * @param $formname
     * @param null $data
     * @param null $ctype
     * @return $this
     * @throws \RuntimeException
     */
    public function setFileUpload($filename, $formname, $data = null, $ctype = null)
    {
        if ($data === null) {
            $data = file_get_contents($filename);
            if ($data === false) {
                throw new \RuntimeException("Unable to read file '{$filename}' for upload");
            }
            if (!$ctype) {
                $ctype = $this->detectFileMimeType($filename);
            }
        }

        $this->getRequest()->getFiles()->set($filename, [
            'formname' => $formname,
            'filename' => basename($filename),
            'ctype' => $ctype,
            'data' => $data
        ]);

        return $this;
    }

    /**
     * @param $filename
     * @return bool
     */
    public function removeFileUpload($filename)
    {
        $file = $this->getRequest()->getFiles()->get($filename);
        if (!empty($file)) {
            $this->getRequest()->getFiles()->set($filename, null);
            return true;
        }
        return false;
    }

    /**
     * @param $domain
     * @param $path
     * @param $secure
     * @return static
     */
    protected function prepareCookies($domain, $path, $secure)
    {
        $validCookies = [];

        if (!empty($this->cookies)) {
            foreach ($this->cookies as $id => $cookie) {
                if ($cookie->isExpired()) {
                    unset($this->cookies[$id]);
                    continue;
                }

                if ($cookie->isValidForRequest($domain, $path, $secure)) {
                    // OAM hack some domains try to set the cookie multiple times
                    $validCookies[$cookie->getName()] = $cookie;
                }
            }
        }

        $cookies = Cookie::fromSetCookieArray($validCookies);
        $cookies->setEncodeValue($this->config['encodecookies']);

        return $cookies;
    }

    /**
     * @param $body
     * @param $uri
     * @return array
     * @throws \RuntimeException
     */
    protected function prepareHeaders($body, $uri)
    {
        $headers = [];

        if ($this->config['httpversion'] === Request::VERSION_11) {
            $host = $uri->getHost();
            if (!(($uri->getScheme() === 'http' && $uri->getPort() === 80) ||
                ($uri->getScheme() === 'https' && $uri->getPort() === 443))
            ) {
                $host .= ':' . $uri->getPort();
            }

            $headers['Host'] = $host;
        }

        if (!$this->getRequest()->getHeaders()->has('Connection')) {
            if (!$this->config['keepalive']) {
                $headers['Connection'] = 'close';
            }
        }

        if (!$this->getRequest()->getHeaders()->has('Accept-Encoding')) {
            if (function_exists('gzinflate')) {
                $headers['Accept-Encoding'] = 'gzip, deflate';
            } else {
                $headers['Accept-Encoding'] = 'identity';
            }
        }

        if (!$this->getRequest()->getHeaders()->has('User-Agent') && isset($this->config['useragent'])) {
            $headers['User-Agent'] = $this->config['useragent'];
        }

        if (!empty($this->auth)) {
            switch ($this->auth['type']) {
                case self::AUTH_BASIC:
                    $auth = $this->calcAuthDigest($this->auth['user'], $this->auth['password'], $this->auth['type']);
                    if ($auth !== false) {
                        $headers['Authorization'] = 'Basic ' . $auth;
                    }
                    break;
                case self::AUTH_DIGEST:
                    if (!$this->adapter instanceof Curl) {
                        throw new \RuntimeException(
                            'The digest authentication is only available for curl adapters ' . Curl::class
                        );
                    }

                    $this->adapter->setCurlOption(CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
                    $this->adapter->setCurlOption(CURLOPT_USERPWD, $this->auth['user'] . ':' . $this->auth['password']);
            }
        }

        // Content-type
        $encType = $this->getEncType();
        if (!empty($encType)) {
            $headers['Content-Type'] = $encType;
        }

        if (!empty($body)) {
            if (is_resource($body)) {
                $fstat = fstat($body);
                $headers['Content-Length'] = $fstat['size'];
            } else {
                $headers['Content-Length'] = strlen($body);
            }
        }

        $requestHeaders = $this->getRequest()->getHeaders()->getHeaders();
        foreach ($requestHeaders as $requestHeaderElement) {
            $headers[$requestHeaderElement->getName()] = $requestHeaderElement->getValue();
        }
        return $headers;
    }

    /**
     * @return string
     * @throws \RuntimeException
     */
    protected function prepareBody()
    {
        // According to RFC2616, a TRACE request should not have a body.
        if ($this->getRequest()->isTrace()) {
            return '';
        }

        $rawBody = $this->getRequest()->getContent();
        if (!empty($rawBody)) {
            return $rawBody;
        }

        $body = '';
        $totalFiles = 0;

        if (!$this->getRequest()->getHeaders()->has('Content-Type')) {
            $totalFiles = count($this->getRequest()->getFiles()->toArray());
            if ($totalFiles > 0) {
                $this->setEncType(self::ENC_FORMDATA);
            }
        } else {
            $this->setEncType($this->getHeader('Content-Type'));
        }

        if (count($this->getRequest()->getPost()->toArray()) > 0 || $totalFiles > 0) {
            if (stripos($this->getEncType(), self::ENC_FORMDATA) === 0) {
                $boundary = '---IONSHTTPCLIENT-' . md5(microtime());
                $this->setEncType(self::ENC_FORMDATA, $boundary);

                $params = self::flattenParametersArray($this->getRequest()->getPost()->toArray());
                foreach ($params as $pp) {
                    $body .= $this->encodeFormData($boundary, $pp[0], $pp[1]);
                }

                foreach ($this->getRequest()->getFiles()->toArray() as $file) {
                    $fhead = ['Content-Type' => $file['ctype']];
                    $body .= $this->encodeFormData(
                        $boundary,
                        $file['formname'],
                        $file['data'],
                        $file['filename'],
                        $fhead
                    );
                }
                $body .= "--{$boundary}--\r\n";
            } elseif (stripos($this->getEncType(), self::ENC_URLENCODED) === 0) {
                $body = http_build_query($this->getRequest()->getPost()->toArray(), null, '&');
            } else {
                throw new \RuntimeException(
                    "Cannot handle content type '{$this->encType}' automatically"
                );
            }
        }

        return $body;
    }

    /**
     * @param $file
     * @return mixed|null|string
     */
    protected function detectFileMimeType($file)
    {
        $type = null;

        if (function_exists('finfo_open')) {
            if (static::$fileInfoDb === null) {
                static::$fileInfoDb = finfo_open(FILEINFO_MIME);
            }

            if (static::$fileInfoDb) {
                $type = finfo_file(static::$fileInfoDb, $file);
            }
        } elseif (function_exists('mime_content_type')) {
            $type = mime_content_type($file);
        }

        // Fallback to the default application/octet-stream
        if (!$type) {
            $type = 'application/octet-stream';
        }

        return $type;
    }

    /**
     * @param $boundary
     * @param $name
     * @param $value
     * @param null $filename
     * @param array $headers
     * @return string
     */
    public function encodeFormData($boundary, $name, $value, $filename = null, $headers = [])
    {
        $ret = "--{$boundary}\r\n" .
            'Content-Disposition: form-data; name="' . $name . '"';

        if ($filename) {
            $ret .= '; filename="' . $filename . '"';
        }
        $ret .= "\r\n";

        foreach ($headers as $hname => $hvalue) {
            $ret .= "{$hname}: {$hvalue}\r\n";
        }
        $ret .= "\r\n";
        $ret .= "{$value}\r\n";

        return $ret;
    }

    /**
     * @param $parray
     * @param null $prefix
     * @return array
     */
    protected function flattenParametersArray($parray, $prefix = null)
    {
        if (!is_array($parray)) {
            return $parray;
        }

        $parameters = [];

        foreach ($parray as $name => $value) {
            if ($prefix) {
                if (is_int($name)) {
                    $key = $prefix . '[]';
                } else {
                    $key = $prefix . "[$name]";
                }
            } else {
                $key = $name;
            }

            if (is_array($value)) {
                $parameters = array_merge($parameters, $this->flattenParametersArray($value, $key));
            } else {
                $parameters[] = [$key, $value];
            }
        }

        return $parameters;
    }

    /**
     * @param UriInterface $uri
     * @param $method
     * @param bool $secure
     * @param array $headers
     * @param string $body
     * @return mixed
     * @throws \RuntimeException
     */
    protected function doRequest(UriInterface $uri, $method, $secure = false, $headers = [], $body = '')
    {
        // Open the connection, send the request and read the response
        $this->adapter->connect($uri->getHost(), $uri->getPort(), $secure);

        if ($this->config['outputstream']) {
            if ($this->adapter instanceof StreamInterface) {
                $stream = $this->openTempStream();
                $this->adapter->setOutputStream($stream);
            } else {
                throw new \RuntimeException('Adapter does not support streaming');
            }
        }
        // HTTP connection
        $this->lastRawRequest = $this->adapter->write(
            $method,
            $uri,
            $this->config['httpversion'],
            $headers,
            $body
        );

        return $this->adapter->read();
    }

    /**
     * @param $user
     * @param $password
     * @param string $type
     * @return string
     * @throws \InvalidArgumentException
     */
    public static function encodeAuthHeader($user, $password, $type = self::AUTH_BASIC)
    {
        switch ($type) {
            case self::AUTH_BASIC:
                if (strpos($user, ':') !== false) {
                    throw new \InvalidArgumentException(
                        "The user name cannot contain ':' in 'Basic' HTTP authentication"
                    );
                }
                return 'Basic ' . base64_encode($user . ':' . $password);
            default:
                throw new \InvalidArgumentException(
                    "Not a supported HTTP authentication type: '$type'"
                );
        }
    }
}
