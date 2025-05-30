<?php

namespace CambioReal\Http;

use CambioReal\Config;

/**
 * HTTP client class, wrapper for curl_* functions.
 *
 * @author Deivide Vian <dvdvian@gmail.com>
 */
class Request
{
    /**
     * The request HTTP method.
     * @var string
     */
    protected $method;

    /**
     * The allowed HTTP methods.
     * @var array
     */
    protected $allowedMethods = ['POST', 'GET'];

    /**
     * The HTTP action (URI).
     * @var string
     */
    protected $action;

    /**
     * The request parameters.
     * @var array
     */
    protected $params;

    /**
     * Flag to call json_decode on response.
     * @var bool
     */
    protected $decodeResponse = false;

    /**
     * Set the request parameters.
     * @param array $params The request parameters
     * @return \CambioReal\Http\Request
     */
    public function setParams($params)
    {
        $this->params = $params;

        return $this;
    }

    /**
     * Set the request HTTP method.
     * @param string $method The request HTTP method
     * @return \CambioReal\Http\Request
     * @throws \InvalidArgumentException
     */
    public function setMethod($method)
    {
        if (! in_array(strtoupper($method), $this->allowedMethods)) {
            throw new \InvalidArgumentException("The HTTP Request doesn't accept $method requests.");
        }

        $this->method = $method;

        return $this;
    }

    /**
     * Set the request target URI.
     * @param string $action The target URI
     * @return \CambioReal\Http\Request
     */
    public function setAction($action)
    {
        $this->action = Config::getURL().$action;

        return $this;
    }

    /**
     * Set the decodeResponse flag depending on the response type (JSON or HTML).
     * @param string $responseType The response type (JSON or HTML)
     * @return \CambioReal\Http\Request
     */
    public function setResponseType($responseType)
    {
        if (strtoupper($responseType) == 'JSON') {
            $this->decodeResponse = true;
        }

        return $this;
    }

    /**
     * Sends the HTTP request.
     * @return mixed
     */
    public function send()
    {
        if (! ini_get('allow_url_fopen')) {
            throw new \RuntimeException('allow_url_fopen must be enabled to use PHP streams.');
        }

        $uri = $this->action;

        if (isset($this->params['token'])) {
            $uri .= '/'.$this->params['token'];
            unset($this->params['token']);
        }

        $contentType = $this->method === 'GET'
            ? 'application/x-www-form-urlencoded'
            : 'application/json';

        $contextOptions = [
            'http' => [
                'ignore_errors' => true,
                'method' => $this->method,
                'header' => "Content-Type: $contentType \r\n".
                    "Accept: application/json \r\n".
                    'User-Agent: CAMBIOREAL PHP Library '.\CambioReal\CambioReal::VERSION."\r\n".
                    'X-APP-ID: '.Config::getAppId()."\r\n".
                    'X-APP-SECRET: '.Config::getAppSecret(),
            ],
        ];

        if ($this->method !== 'GET') {
            $contextOptions['http']['content'] = json_encode($this->params);
        }

        $context = stream_context_create($contextOptions);

        $response = file_get_contents($uri, false, $context);

        $httpCode = 500;

        if (isset($http_response_header)) {
            $httpCode = $this->getHttpCode($http_response_header);
        }

        if ($httpCode === 200 && $response && strlen($response)) {
            return $this->decodeResponse
                ? json_decode($response)
                : $response;
        }

        throw new \RuntimeException("Bad HTTP request: $httpCode - $response");
    }

    protected function getHttpCode($http_response_header)
    {
        $statusLine = $http_response_header[0]; // Ex: HTTP/1.1 200 OK
        preg_match('{HTTP\/\S*\s(\d{3})}', $statusLine, $match);

        return (int) $match[1] ?? 500;
    }
}
