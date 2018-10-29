<?php

namespace Ions\Http\Client;

use Ions\Http\Client as HttpClient;
use Ions\Uri;

class RestClient
{
    protected $data = [];

    protected $uri = null;

    protected $httpClient;

    public function __construct($uri = null)
    {
        if (!empty($uri)) {
            $this->setUri($uri);
        }
    }

    public function setHttpClient(HttpClient $client)
    {
        $this->httpClient = $client;
        return $this;
    }

    public function getHttpClient()
    {
        if (null === $this->httpClient) {
            $this->setHttpClient(new HttpClient());
        }

        return $this->httpClient;
    }

    public function setUri($uri)
    {
        if ($uri instanceof Uri\Uri) {
            $this->uri = $uri;
        } else {
            $this->uri = Uri\UriFactory::factory($uri);
        }

        return $this;
    }

    public function getUri()
    {
        return $this->uri;
    }

    protected function prepareRest($path)
    {
        // Get the URI object and configure it
        if (!$this->uri instanceof Uri\Uri) {
            throw new Exception\UnexpectedValueException('URI object must be set before performing call');
        }

        $uri = $this->uri->toString();

        if ($path[0] != '/' && $uri[strlen($uri)-1] != '/') {
            $path = '/' . $path;
        }

        $this->uri->setPath($path);

        $client = $this->getHttpClient();
        $client->resetParameters();
        $client->setUri($this->uri);
    }

    public function restGet($path, array $query = null)
    {
        $this->prepareRest($path);
        $client = $this->getHttpClient();
        if (is_array($query)) {
            $client->setParameterGet($query);
        }
        return $client->setMethod('GET')->send();
    }

    protected function performPost($method, $data = null)
    {
        $client = $this->getHttpClient();
        $client->setMethod($method);

        $request = $client->getRequest();
        if (is_string($data)) {
            $request->setContent($data);
        } elseif (is_array($data) || is_object($data)) {
            $request->getPost()->fromArray((array) $data);
        }
        return $client->send($request);
    }

    public function restPost($path, $data = null)
    {
        $this->prepareRest($path);
        return $this->performPost('POST', $data);
    }

    public function restPut($path, $data = null)
    {
        $this->prepareRest($path);
        return $this->performPost('PUT', $data);
    }

    public function restDelete($path)
    {
        $this->prepareRest($path);
        return $this->getHttpClient()->setMethod('DELETE')->send();
    }

    public function __call($method, $args)
    {
        $methods = ['post', 'get', 'delete', 'put'];

        if (in_array(strtolower($method), $methods)) {
            if (!isset($args[0])) {
                $args[0] = $this->uri->getPath();
            }
            $this->data['rest'] = 1;
            $data               = array_slice($args, 1) + $this->data;
            $response           = $this->{'rest' . $method}($args[0], $data);
            $this->data         = []; //Initializes for next Rest method.
            return new Result($response->getBody());
        }

			if (count($args) == 1) {
				 if (!isset($this->data['method'])) {
					  $this->data['method'] = $method;
					  $this->data['arg1']   = $args[0];
				 }
				 $this->data[$method]  = $args[0];
			} else {
				 $this->data['method'] = $method;
				 if (count($args) > 0) {
					  foreach ($args as $key => $arg) {
							$key = 'arg' . $key;
							$this->data[$key] = $arg;
					  }
				 }
			}

			return $this;
    }
}
