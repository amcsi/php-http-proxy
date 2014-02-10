<?php
class Amcsi_HttpProxy_Request
{
    protected $url;
    protected $method = 'GET';
    protected $headers = array();
    protected $content;

    protected $timeout;

    public function setUrl($url)
    {
        $this->url = $url;
        return $this;
    }

    public function setMethod($method)
    {
        $this->method = $method;
        return $this;
    }

    public function setHeaders(array $headers)
    {
        $this->headers = $headers;
        return $this;
    }

    public function addHeader($header)
    {
        $this->headers[] = $header;
        return $this;
    }

    public function setContentAndLength($content)
    {
        $this->content = $content;
        $this->addHeader('Content-Length: ' . strlen($content));
        return $this;
    }

    public function setTimeoutMs($timeoutMs)
    {
        $this->timeout = $timeoutMs / 1000;
        return $this;
    }

    /**
     * doRequest 
     * 
     * @access public
     * @return Amcsi_HttpProxy_Response
     */
    public function doRequest()
    {
        $client = new Amcsi_HttpProxy_HttpClient_FileGetContents;
        $client->setMethod($this->method);
        $client->setTimeout($this->timeout);
        $client->setContent($this->content);
        $response = $client->doRequest($this->url, $this->headers);
        return $response;
    }
}
