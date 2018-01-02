<?php

namespace Ions\Http\Client;

use Client as HttpClient;
use Ions\Std\Xml\Security;

/**
 * Class Api
 * @package Ions\Http\Client
 */
class Api
{
    /**
     * @var string
     */
    protected $apiPath;

    /**
     * @var string
     */
    protected $errorMsg;

    /**
     * @var string
     */
    protected $statusCode;

    /**
     * @var bool
     */
    protected $success = false;

    /**
     * @var
     */
    protected $url;

    /**
     * @var array
     */
    protected $queryParams = [];

    /**
     * @var array
     */
    protected $headers = [];

    /**
     * @var array
     */
    protected $api = [];

    /**
     * @var
     */
    protected $httpClient;

    /**
     * Api constructor.
     * @param HttpClient|null $httpClient
     */
    public function __construct(HttpClient $httpClient = null)
    {
        $this->setHttpClient($httpClient ?: new HttpClient);
    }

    /**
     * @param $name
     * @param $params
     * @return bool|mixed
     * @throws \RuntimeException
     */
    public function __call($name, $params)
    {
        // API specifications
        if (!empty($this->api[$name])) {
            $api = $this->api[$name]($params);
        } else {
            if (!empty($this->apiPath)) {
                $fileName = $this->apiPath . '/' . $name . '.php';
                if (file_exists($fileName)) {
                    $apiFunc = function ($params) use ($fileName) {
                        return include $fileName;
                    };
                    $api = $apiFunc($params);
                }
            }
        }

        if (empty($api)) {
            throw new \RuntimeException(
                "The HTTP request specification for the API $name is empty. I cannot proceed without it."
            );
        }

        // Build HTTP request
        $client = $this->getHttpClient();
        $client->resetParameters();
        $this->errorMsg = null;
        $this->errorCode = null;
        if (isset($api['method'])) {
            $client->setMethod($api['method']);
        } else {
            $client->setMethod('GET');
        }
        if (!empty($this->queryParams)) {
            $client->setParameterGet($this->queryParams);
        }
        if (isset($api['body'])) {
            $client->setRawBody($api['body']);
        }
        $headers = [];
        if (!empty($this->headers)) {
            $headers = $this->getHeaders();
        }
        if (isset($api['header'])) {
            $headers = array_merge($headers, $api['header']);
        }
        $client->setHeaders($headers);
        $url = $this->getUrl();
        if (isset($api['url'])) {
            if (0 === strpos($api['url'], 'http')) {
                $url = $api['url'];
            } else {
                $url .= $api['url'];
            }
        }
        $client->setUri($url);
        if (isset($api['response']['format'])) {
            $formatOutput = strtolower($api['response']['format']);
        }
        $validCodes = [200];
        if (isset($api['response']['valid_codes'])) {
            $validCodes = $api['response']['valid_codes'];
        }

        // Send HTTP request
        $response         = $client->send();
        $this->statusCode = $response->getStatusCode();
        if (in_array($this->statusCode, $validCodes)) {
            $this->success = true;
            if (isset($formatOutput)) {
                if ($formatOutput === 'json') {
                    return json_decode($response->getBody(),true);
                } elseif ($formatOutput === 'xml') {
                	$xml = Security::scan($response->getBody());
                    return json_decode(json_encode((array) $xml), 1);
                }
            }
            return $response->getBody();
        }
        $this->errorMsg = $response->getBody();
        $this->success  = false;
        return false;
    }

    /**
     * @param $apiPath
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setApiPath($apiPath)
    {
        if (!is_dir($apiPath)) {
            throw new \InvalidArgumentException("Tha path $apiPath specified is not valid");
        }
        $this->apiPath = $apiPath;
        return $this;
    }

    /**
     * @return string
     */
    public function getApiPath()
    {
        return $this->apiPath;
    }

    /**
     * @param null $url
     * @return $this
     */
    public function setUrl($url = null)
    {
        $this->url = $url;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @param array|null $query
     * @return $this
     */
    public function setQueryParams(array $query = null)
    {
        $this->queryParams = $query;
        return $this;
    }

    /**
     * @return array
     */
    public function getQueryParams()
    {
        return $this->queryParams;
    }

    /**
     * @param array|null $headers
     * @return $this
     */
    public function setHeaders(array $headers = null)
    {
        $this->headers = $headers;
        return $this;
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * @param HttpClient $httpClient
     * @return $this
     */
    public function setHttpClient(HttpClient $httpClient)
    {
        $this->httpClient = $httpClient;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getHttpClient()
    {
        return $this->httpClient;
    }

    /**
     * @return string
     */
    public function getErrorMsg()
    {
        return $this->errorMsg;
    }

    /**
     * @return string
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * @return bool
     */
    public function isSuccess()
    {
        return $this->success;
    }

    /**
     * @param $name
     * @param $api
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setApi($name, $api)
    {
        if (!is_string($name)) {
            throw new \InvalidArgumentException('The name of the API must be a string');
        }
        if (!is_callable($api)) {
            throw new \InvalidArgumentException('The value of the API must be a callable');
        }
        $this->api[$name] = $api;
        return $this;
    }

    /**
     * @param $name
     * @return mixed
     */
    public function getApi($name)
    {
        return $this->api[$name];
    }

    /**
     * @return $this
     */
    public function resetLastResponse()
    {
        $this->success    = false;
        $this->errorMsg   = null;
        $this->statusCode = null;
        return $this;
    }

    /**
     * @return array
     */
    public function getResponseHeaders()
    {
        $response = $this->httpClient->getResponse();
        if (empty($response)) {
            return [];
        }

        return $response->getHeaders()->getHeaders();
    }
}
