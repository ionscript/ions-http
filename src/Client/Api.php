<?php

namespace Ions\Http\Client;

use Ions\Http\Client\Client;
use Ions\Std\Xml\Security;

/**
 * Class Api
 * @package Ions\Http\Client
 */
class Api
{
    protected $path;

    protected $error;

    protected $status;

    protected $success = false;

    protected $url;

    protected $query = [];

    protected $headers = [];

    protected $api = [];

    protected $client;

//    public function __construct(HttpClient $httpClient = null)
//    {
//        $this->setHttpClient($httpClient ?: new HttpClient);
//    }

    public function __call($name, $params)
    {
        // API specifications
        if (!empty($this->api[$name])) {
            $api = $this->api[$name]($params);
        } else {
            if ($this->path) {
                $file = $this->path . '/' . $name . '.php';

                if (file_exists($file)) {
                    $callback = function ($params) use ($file) {
                        return include $file;
                    };

                    $api = $callback($params);
                }
            }
        }

        if (empty($api)) {
            throw new \RuntimeException(
                "The HTTP request specification for the API $name is empty. I cannot proceed without it."
            );
        }

        // Build HTTP request
        $client = $this->getClient();
        $client->resetParameters();

        $this->error = null;
        $this->status = null;

        if (isset($api['method'])) {
            $client->setMethod($api['method']);
        } else {
            $client->setMethod('GET');
        }

        if ($this->queryParams) {
            $client->setParameterGet($this->queryParams);
        }

        if (isset($api['body'])) {
            $client->setRawBody($api['body']);
        }

        $headers = [];

        if ($this->headers) {
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

        $codes = [200];

        if (isset($api['response']['codes'])) {
            $codes = $api['response']['codes'];
        }

        // Send HTTP request
        $response = $client->send();
        $this->status = $response->getStatusCode();

        if (in_array($this->status, $codes)) {
            $this->success = true;
            if (isset($format)) {
                if ($format === 'json') {
                    return json_decode($response->getBody(), true);
                } elseif ($format === 'xml') {
                    $xml = Security::scan($response->getBody());
                    return json_decode(json_encode((array)$xml), 1);
                }
            }

            return $response->getBody();
        }

        $this->error = $response->getBody();
        $this->success = false;
        return false;
    }

    public function __isset($name)
    {
        return array_key_exists($name, $this->api);
    }

    public function setPath($path)
    {
        if (!is_dir($path)) {
            throw new \InvalidArgumentException("Tha path $path specified is not valid");
        }

        $this->path = $path;
        return $this;
    }

    public function getPath()
    {
        return $this->path;
    }

    public function setUrl($url = null)
    {
        $this->url = $url;
        return $this;
    }

    public function getUrl()
    {
        return $this->url;
    }

    public function setQuery(array $query = null)
    {
        $this->query = $query;
        return $this;
    }

    public function getQuery()
    {
        return $this->query;
    }

    public function setHeaders(array $headers = null)
    {
        $this->headers = $headers;
        return $this;
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function setClient(Client $client)
    {
        $this->client = $client;
        return $this;
    }

    public function getClient()
    {
        $this->setClient($this->client ?: new Client);
        return $this->httpClient;
    }

    public function getError()
    {
        return $this->error;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function isSuccess()
    {
        return $this->success;
    }

    public function set($name, $api)
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

    public function get($name)
    {
        return $this->api[$name];
    }

    /**
     * @return $this
     */
    public function resetResponse()
    {
        $this->success = false;
        $this->errorMsg = null;
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
