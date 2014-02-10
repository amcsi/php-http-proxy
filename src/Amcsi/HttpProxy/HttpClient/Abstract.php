<?php
abstract class Amcsi_HttpProxy_HttpClient_Abstract implements Amcsi_HttpProxy_HttpClient_Interface
{
    protected $method = 'GET';
    protected $timeout;

    public function setMethod($method)
    {
        $this->method = $method;
        return $this;
    }

    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;
    }

    public function setContent($content)
    {
        $this->content = $content;
        return $this;
    }

}
