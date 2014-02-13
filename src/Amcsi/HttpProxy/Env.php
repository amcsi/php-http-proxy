<?php
class Amcsi_HttpProxy_Env
{
    protected $get;
    protected $post;
    protected $input;
    protected $server;

    protected $hostOrIp;

    public function __construct(array $get, array $post, $input, array $server)
    {
        $this->get = $get;
        $this->post = $post;
        $this->input = $input;
        $this->server = $server;
    }

    public function getParam($name)
    {
        return isset($this->get[$name]) ? $this->get[$name] : null;
    }

    public function getRequestUri()
    {
        if (!$this->requestUri) {
            $server = $this->server;
            $this->requestUri = isset($server['REQUEST_URI']) ?
                $server['REQUEST_URI'] :
                null
            ;
        }
        return $this->requestUri;
    }

    public function getHostOrIp()
    {
        if (!$this->hostOrIp) {
            $server = $this->server;
            $this->hostOrIp = isset($server['HTTP_HOST']) ?
                $server['HTTP_HOST'] :
                null
            ;
            if (!$this->hostOrIp) {
                $this->hostOrIp = isset($server['SERVER_ADDR']) ?
                    $server['SERVER_ADDR'] :
                    null
                ;
            }
        }
        return $this->hostOrIp;
    }
}
