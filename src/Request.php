<?php

namespace Ions\Http;

use Ions\Std\RequestInterface;
use Ions\Uri\Http as HttpUri;
use Ions\Uri\UriInterface;

/**
 * Class Request
 * @package Ions\Http
 */
class Request extends Message implements RequestInterface
{
    const METHOD_OPTIONS = 'OPTIONS';
    const METHOD_GET = 'GET';
    const METHOD_HEAD = 'HEAD';
    const METHOD_POST = 'POST';
    const METHOD_PUT = 'PUT';
    const METHOD_DELETE = 'DELETE';
    const METHOD_TRACE = 'TRACE';
    const METHOD_CONNECT = 'CONNECT';
    const METHOD_PATCH = 'PATCH';

    /**
     * @var string
     */
    protected $method = self::METHOD_GET;

    /**
     * @var null|string|HttpUri
     */
    protected $uri;

    /**
     * @var array
     */
    protected $queryParams = [];

    /**
     * @var array
     */
    protected $postParams = [];

    /**
     * @var array
     */
    protected $fileParams = [];

    /**
     * @param $string
     * @return static
     * @throws \InvalidArgumentException|\RuntimeException
     */
    public static function create($string)
    {
        $request = new static();

        $lines = explode("\r\n", $string);

        $matches = null;

        $methods = implode('|', [
            self::METHOD_OPTIONS,
            self::METHOD_GET,
            self::METHOD_HEAD,
            self::METHOD_POST,
            self::METHOD_PUT,
            self::METHOD_DELETE,
            self::METHOD_TRACE,
            self::METHOD_CONNECT,
            self::METHOD_PATCH,
            ]);

        $regex = '#^(?P<method>' . $methods . ')\s(?P<uri>[^ ]*)(?:\sHTTP\/(?P<version>\d+\.\d+)){0,1}#';

        $firstLine = array_shift($lines);

        if (!preg_match($regex, $firstLine, $matches)) {
            throw new \InvalidArgumentException('A valid request line was not found in the provided string');
        }

        $request->setMethod($matches['method']);

        $request->setUri($matches['uri']);

        $parsedUri = parse_url($matches['uri']);

        if (array_key_exists('query', $parsedUri)) {
            $parsedQuery = [];
            parse_str($parsedUri['query'], $parsedQuery);
            $request->setQuery($parsedQuery);
        }

        if (isset($matches['version'])) {
            $request->setVersion($matches['version']);
        }

        if (count($lines) === 0) {
            return $request;
        }

        $isHeader = true;

        $headers = $rawBody = [];

        while ($lines) {

            $nextLine = array_shift($lines);

            if ($nextLine === '') {
                $isHeader = false;

                continue;
            }

            if ($isHeader) {
                if (preg_match("/[\r\n]/", $nextLine)) {
                    throw new \RuntimeException('CRLF injection detected');
                }

                $headers[] = $nextLine;
                continue;
            }

            if (empty($rawBody) && preg_match('/^[a-z0-9!#$%&\'*+.^_`|~-]+:$/i', $nextLine)) {
                throw new \RuntimeException('CRLF injection detected');
            }

            $rawBody[] = $nextLine;
        }

        if ($headers) {
            $request->headers = implode("\r\n", $headers);
        }

        if ($rawBody) {
            $request->setContent(implode("\r\n", $rawBody));
        }

        return $request;
    }

    /**
     * @param $method
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setMethod($method)
    {
        $method = strtoupper($method);

        if (!defined('static::METHOD_' . $method)) {
            throw new \InvalidArgumentException('Invalid HTTP method passed');
        }

        $this->method = $method;

        return $this;
    }

    /**
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
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

        } elseif (!($uri instanceof UriInterface)) {
            throw new \InvalidArgumentException('URI must be an instance of Ions\Uri\Http or a string');
        }

        $this->uri = $uri;

        return $this;
    }

    /**
     * @return HttpUri
     */
    public function getUri()
    {
        if ($this->uri === null || is_string($this->uri)) {
            $this->uri = new HttpUri($this->uri);
        }

        return $this->uri;
    }

    /**
     * @return string
     */
    public function getUriString()
    {
        if ($this->uri instanceof HttpUri) {
            return $this->uri->toString();
        }

        return $this->uri;
    }

    /**
     * @param $query
     * @return $this
     */
    public function setQuery($query)
    {
        $this->queryParams = $query;

        return $this;
    }

    /**
     * @param null $name
     * @param null $default
     * @return array|mixed
     */
    public function getQuery($name = null, $default = null)
    {
        if ($name === null) {
            return $this->queryParams;
        }

        if($default !== null) {
            $this->queryParams[$name] = $default;
        }

        return $this->queryParams[$name];
    }

    /**
     * @param null $name
     * @return bool
     */
    public function hasQuery($name = null)
    {
        if ($name === null) {
            return (bool)$this->queryParams;
        }

        return array_key_exists($name, $this->queryParams);
    }

    /**
     * @param $post
     * @return $this
     */
    public function setPost($post)
    {
        $this->postParams = $post;

        return $this;
    }

    /**
     * @param null $name
     * @param null $default
     * @return array|mixed
     */
    public function getPost($name = null, $default = null)
    {
        if ($name === null) {
            return $this->postParams;
        }

        if($default !== null) {
            $this->postParams[$name] = $default;
        }

        return $this->postParams[$name];
    }

    /**
     * @param null $name
     * @return bool
     */
    public function hasPost($name = null)
    {
        if ($name === null) {
            return (bool)$this->postParams;
        }

        return array_key_exists($name, $this->postParams);
    }

    /**
     * @return bool|mixed
     */
    public function getCookie()
    {
        return $this->getHeaders()->get('Cookie');
    }

    /**
     * @param array $files
     * @return $this
     */
    public function setFiles(array $files)
    {
        $this->fileParams = $files;

        return $this;
    }

    /**
     * @param null $name
     * @param null $default
     * @return array|mixed
     */
    public function getFiles($name = null, $default = null)
    {
        if ($name === null) {
            return $this->fileParams;
        }

        if($default !== null) {
            $this->postParams[$name] = $default;
        }

        return $this->fileParams[$name];
    }

    /**
     * @param null $name
     * @return bool
     */
    public function hasFiles($name = null)
    {
        if ($name === null) {
            return (bool)$this->fileParams;
        }

        return array_key_exists($name, $this->fileParams);
    }

    /**
     * @return bool
     */
    public function isOptions()
    {
        return ($this->method === self::METHOD_OPTIONS);
    }

    /**
     * @return bool
     */
    public function isGet()
    {
        return ($this->method === self::METHOD_GET);
    }

    /**
     * @return bool
     */
    public function isHead()
    {
        return ($this->method === self::METHOD_HEAD);
    }

    /**
     * @return bool
     */
    public function isPost()
    {
        return ($this->method === self::METHOD_POST);
    }

    /**
     * @return bool
     */
    public function isPut()
    {
        return ($this->method === self::METHOD_PUT);
    }

    /**
     * @return bool
     */
    public function isDelete()
    {
        return ($this->method === self::METHOD_DELETE);
    }

    /**
     * @return bool
     */
    public function isTrace()
    {
        return ($this->method === self::METHOD_TRACE);
    }

    /**
     * @return bool
     */
    public function isConnect()
    {
        return ($this->method === self::METHOD_CONNECT);
    }

    /**
     * @return bool
     */
    public function isPatch()
    {
        return ($this->method === self::METHOD_PATCH);
    }

    /**
     * @return bool
     */
    public function isXmlHttpRequest()
    {
        $header = $this->getHeaders()->get('X_REQUESTED_WITH');

        return false !== $header && $header->getValue() === 'XMLHttpRequest';
    }

    /**
     * @return bool
     */
    public function isFlashRequest()
    {
        $header = $this->getHeaders()->get('USER_AGENT');

        return false !== $header && false !== strpos($header->getValue(), ' flash');
    }

    /**
     * @return string
     */
    public function renderRequestLine()
    {
        return $this->method . ' ' . (string)$this->uri . ' HTTP/' . $this->version;
    }

    /**
     * @return string
     */
    public function toString()
    {
        $str = $this->renderRequestLine() . "\r\n";
        $str .= $this->getHeaders()->toString();
        $str .= "\r\n";
        $str .= $this->getContent();

        return $str;
    }
}
