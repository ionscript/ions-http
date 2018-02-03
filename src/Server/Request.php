<?php

namespace Ions\Http\Server;

use Ions\Http\Header\Cookie;
use Ions\Http\Request as HttpRequest;
use Ions\Uri\Http as HttpUri;

/**
 * Class Request
 * @package Ions\Http\Server
 */
class Request extends HttpRequest
{
    /**
     * @var string|null
     */
    protected $baseUrl;

    /**
     * @var string|null
     */
    protected $basePath;

    /**
     * @var string|null
     */
    protected $requestUri;

    /**
     * @var array
     */
    protected $serverParams = [];

    /**
     * @var array
     */
    protected $envParams = [];

    /**
     * @var array
     */
    protected $cookieParams = [];

    /**
     * Request constructor.
     */
    public function __construct()
    {
        $this->setEnv($_ENV);

        if ($_GET) {
            $this->setQuery($_GET);
        }

        if ($_POST) {
            $this->setPost($_POST);
        }

        if ($_COOKIE) {
            $this->setCookie($_COOKIE);
        }

        if ($_FILES) {
            $files = $this->mapPhpFiles();
            $this->setFiles($files);
        }

        $this->setServer($_SERVER);
    }

    /**
     * @param $data
     * @return array|string
     */
    public function clean($data)
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                unset($data[$key]);

                $data[$this->clean($key)] = $this->clean($value);
            }
        } else {
            $data = htmlspecialchars($data);
        }

        return $data;
    }

    /**
     * @return bool|string
     */
    public function getContent()
    {
        if (empty($this->content)) {

            $requestBody = file_get_contents('php://input');

            if (strlen($requestBody) > 0) {
                $this->content = $requestBody;
            }
        }

        return $this->content;
    }

    /**
     * @param $cookie
     * @return $this
     */
    public function setCookie(array $cookie)
    {
        $this->cookieParams = $cookie;
        $this->getHeaders()->addHeader(new Cookie($this->cookieParams));

        return $this;
    }

    /**
     * @param null $name
     * @return null|string
     */
    public function getCookie($name = null)
    {
        if ($name === null) {
            return $this->cookieParams;
        }

        return $this->cookieParams[$name];
    }

    /**
     * @param null $name
     * @return bool
     */
    public function hasCookie($name = null)
    {
        if ($name === null) {
            return (bool)$this->cookieParams;
        }

        return array_key_exists($name, $this->cookieParams);
    }

    /**
     * @param $requestUri
     * @return $this
     */
    public function setRequestUri($requestUri)
    {
        $this->requestUri = $requestUri;

        return $this;
    }

    /**
     * @return mixed|null|string
     */
    public function getRequestUri()
    {
        if ($this->requestUri === null) {
            $this->requestUri = $this->detectRequestUri();
        }

        return $this->requestUri;
    }

    /**
     * @param $baseUrl
     * @return $this
     */
    public function setBaseUrl($baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');

        return $this;
    }

    /**
     * @return null|string
     */
    public function getBaseUrl()
    {
        if ($this->baseUrl === null) {
            $this->setBaseUrl($this->detectBaseUrl());
        }

        return $this->baseUrl;
    }

    /**
     * @param $basePath
     * @return $this
     */
    public function setBasePath($basePath)
    {
        $this->basePath = rtrim($basePath, '/');
        return $this;
    }

    /**
     * @return null|string
     */
    public function getBasePath()
    {
        if ($this->basePath === null) {
            $this->setBasePath($this->detectBasePath());
        }
        return $this->basePath;
    }

    /**
     * @param array $server
     * @return $this
     */
    public function setServer(array $server)
    {
        $this->serverParams = $server;

        if (function_exists('apache_request_headers')) {
            $apacheRequestHeaders = apache_request_headers();

            if (!isset($this->serverParams['HTTP_AUTHORIZATION'])) {
                if (isset($apacheRequestHeaders['Authorization'])) {
                    $this->serverParams['HTTP_AUTHORIZATION'] = $apacheRequestHeaders['Authorization'];
                } elseif (isset($apacheRequestHeaders['authorization'])) {
                    $this->serverParams['HTTP_AUTHORIZATION'] = $apacheRequestHeaders['authorization'];
                }
            }
        }

        $headers = [];

        foreach ($server as $key => $value) {
            if ($value || (!is_array($value) && strlen($value))) {
                if (strpos($key, 'HTTP_') === 0) {
                    if (strpos($key, 'HTTP_COOKIE') === 0) {
                        continue;
                    }
                    $headers[strtr(ucwords(strtolower(strtr(substr($key, 5), '_', ' '))), ' ', '-')] = $value;
                } elseif (strpos($key, 'CONTENT_') === 0) {
                    $name = substr($key, 8);
                    $headers['Content-' . (($name === 'MD5') ? $name : ucfirst(strtolower($name)))] = $value;
                }
            }
        }

        $this->getHeaders()->addHeaders($headers);

        if (isset($this->serverParams['REQUEST_METHOD'])) {
            $this->setMethod($this->serverParams['REQUEST_METHOD']);
        }

        if (isset($this->serverParams['SERVER_PROTOCOL']) && strpos($this->serverParams['SERVER_PROTOCOL'], self::VERSION_10) !== false) {
            $this->setVersion(self::VERSION_10);
        }

        $uri = new HttpUri();

        if ((!empty($this->serverParams['HTTPS']) && strtolower($this->serverParams['HTTPS']) !== 'off') || (!empty($this->serverParams['HTTP_X_FORWARDED_PROTO']) && $this->serverParams['HTTP_X_FORWARDED_PROTO'] == 'https')) {
            $scheme = 'https';
        } else {
            $scheme = 'http';
        }

        $uri->setScheme($scheme);

        $host = null;
        $port = null;

        if ($this->getHeaders()->get('host')) {
            $host = $this->getHeaders()->get('host')->getValue();

            if (preg_match('|\:(\d+)$|', $host, $matches)) {
                $host = substr($host, 0, -1 * (strlen($matches[1]) + 1));
                $port = (int)$matches[1];
            }
        }

        if (!$host && isset($this->serverParams['SERVER_NAME'])) {
            $host = $this->serverParams['SERVER_NAME'];

            if (isset($this->serverParams['SERVER_PORT'])) {
                $port = (int)$this->serverParams['SERVER_PORT'];
            }

            if (isset($this->serverParams['SERVER_ADDR']) && preg_match('/^\[[0-9a-fA-F\:]+\]$/', $host)) {

                $host = '[' . $this->serverParams['SERVER_ADDR'] . ']';

                if ($port . ']' === substr($host, strrpos($host, ':') + 1)) {
                    $port = null;
                }
            }
        }

        $uri->setHost($host);
        $uri->setPort($port);

        $requestUri = $this->getRequestUri();

        if (($qpos = strpos($requestUri, '?')) !== false) {
            $requestUri = substr($requestUri, 0, $qpos);
        }

        $uri->setPath($requestUri);

        if (isset($this->serverParams['QUERY_STRING'])) {
            $uri->setQuery($this->serverParams['QUERY_STRING']);
        }

        $this->setUri($uri);

        return $this;
    }

    /**
     * @param null $name
     * @return null|string
     */
    public function getServer($name = null)
    {
        if ($name === null) {
            return $this->serverParams;
        }

        return $this->serverParams[$name];
    }

    /**
     * @param null $name
     * @return bool
     */
    public function hasServer($name = null)
    {
        if ($name === null) {
            return (bool)$this->serverParams;
        }

        return array_key_exists($name, $this->serverParams);
    }

    /**
     * @param array $env
     * @return $this
     */
    public function setEnv(array $env)
    {
        $this->envParams = $env;

        return $this;
    }

    /**
     * @param null $name
     * @return null|string
     */
    public function getEnv($name = null)
    {
        if ($name === null) {
            return $this->envParams;
        }

        return $this->envParams[$name];
    }

    /**
     * @param null $name
     * @return bool
     */
    public function hasEnv($name = null)
    {
        if ($name === null) {
            return (bool)$this->envParams;
        }

        return array_key_exists($name, $this->envParams);
    }

    /**
     * @return array
     */
    protected function mapPhpFiles()
    {
        $files = [];

        foreach ($_FILES as $fileName => $fileParams) {
            $files[$fileName] = [];

            foreach ($fileParams as $param => $data) {

                if (!is_array($data)) {
                    $files[$fileName][$param] = $data;
                } else {

                    foreach ($data as $i => $v) {
                        $this->mapPhpFileParam($files[$fileName], $param, $i, $v);
                    }
                }
            }
        }

        return $files;
    }

    /**
     * @param $array
     * @param $paramName
     * @param $index
     * @param $value
     */
    protected function mapPhpFileParam(&$array, $paramName, $index, $value)
    {
        if (!is_array($value)) {
            $array[$index][$paramName] = $value;
        } else {
            foreach ($value as $i => $v) {
                $this->mapPhpFileParam($array[$index], $paramName, $i, $v);
            }
        }
    }

    /**
     * @return mixed
     */
    protected function detectRequestUri()
    {
        $requestUri = null;

        $server = $this->getServer();

        $httpXRewriteUrl = isset($server['HTTP_X_REWRITE_URL']) ? $server['HTTP_X_REWRITE_URL'] : '';

        if ($httpXRewriteUrl !== null) {
            $requestUri = $httpXRewriteUrl;
        }

        $httpXOriginalUrl = isset($server['HTTP_X_ORIGINAL_URL']) ? $server['HTTP_X_ORIGINAL_URL'] : '';

        if ($httpXOriginalUrl !== null) {
            $requestUri = $httpXOriginalUrl;
        }

        $iisUrlRewritten = isset($server['IIS_WasUrlRewritten']) ? $server['IIS_WasUrlRewritten'] : '';

        $unencodedUrl = isset($server['UNENCODED_URL']) ? $server['UNENCODED_URL'] : '';

        if ('1' == $iisUrlRewritten && '' !== $unencodedUrl) {
            return $unencodedUrl;
        }

        if (!$httpXRewriteUrl) {
            $requestUri = isset($server['REQUEST_URI']) ? $server['REQUEST_URI'] : '';
        }

        if ($requestUri !== null) {
            return preg_replace('#^[^/:]+://[^/]+#', '', $requestUri);
        }

        $origPathInfo = isset($serverr['ORIG_PATH_INFO']) ? $serverr['ORIG_PATH_INFO'] : '';

        if ($origPathInfo !== null) {
            $queryString = isset($server['QUERY_STRING']) ? $server['QUERY_STRING'] : '';

            if ($queryString !== '') {
                $origPathInfo .= '?' . $queryString;
            }

            return $origPathInfo;
        }

        return '/';
    }

    /**
     * @return mixed
     */
    protected function detectBaseUrl()
    {
        $filename = $this->getServer('SCRIPT_FILENAME');
        $scriptName = $this->getServer('SCRIPT_NAME');
        $phpSelf = $this->getServer('PHP_SELF');
        $origScriptName = $this->hasServer('ORIGIN_SCRIPT_NAME') ? $this->getServer('ORIGIN_SCRIPT_NAME') : null;

        if ($scriptName !== null && basename($scriptName) === $filename) {
            $baseUrl = $scriptName;
        } elseif ($phpSelf !== null && basename($phpSelf) === $filename) {
            $baseUrl = $phpSelf;
        } elseif ($origScriptName !== null && basename($origScriptName) === $filename) {
            $baseUrl = $origScriptName;
        } else {
            $baseUrl = '/';

            $basename = basename($filename);

            if ($basename) {
                $path = ($phpSelf ? trim($phpSelf, '/') : '');
                $basePos = strpos($path, $basename) ?: 0;
                $baseUrl .= substr($path, 0, $basePos) . $basename;
            }
        }

        if (empty($baseUrl)) {
            return '';
        }

        $requestUri = $this->getRequestUri();

        if (0 === strpos($requestUri, $baseUrl)) {
            return $baseUrl;
        }

        $baseDir = str_replace('\\', '/', dirname($baseUrl));

        if (0 === strpos($requestUri, $baseDir)) {
            return $baseDir;
        }

        $truncatedRequestUri = $requestUri;

        if (false !== ($pos = strpos($requestUri, '?'))) {
            $truncatedRequestUri = substr($requestUri, 0, $pos);
        }

        $basename = basename($baseUrl);

        if (empty($basename) || false === strpos($truncatedRequestUri, $basename)) {
            return '';
        }

        if (strlen($requestUri) >= strlen($baseUrl) && (false !== ($pos = strpos($requestUri, $baseUrl)) && $pos !== 0)) {
            $baseUrl = substr($requestUri, 0, $pos + strlen($baseUrl));
        }

        return $baseUrl;
    }

    /**
     * @return mixed
     */
    protected function detectBasePath()
    {
        $baseUrl = $this->getBaseUrl();

        if ($baseUrl === '') {
            return '';
        }

        $filename = basename($this->getServer()['SCRIPT_FILENAME']);

        if (basename($baseUrl) === $filename) {
            return str_replace('\\', '/', dirname($baseUrl));
        }

        return $baseUrl;
    }
}
