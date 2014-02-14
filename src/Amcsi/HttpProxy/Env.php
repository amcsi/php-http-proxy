<?php
class Amcsi_HttpProxy_Env
{
    protected $get;
    protected $post;
    protected $input;
    protected $server;
    protected $requestHeaders;

    protected $url;
    protected $requestUri;
    protected $hostOrIp;

    public function __construct(
        array $get,
        array $post,
        $input,
        array $server,
        array $requestHeaders
    ) {
        $this->get = $get;
        $this->post = $post;
        $this->input = $input;
        $this->server = $server;
        $this->requestHeaders = $requestHeaders;
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

    /**
     * getUrlObj 
     * 
     * @access public
     * @return Amcsi_HttpProxy_Url
     */
    public function getUrlObj($apacheRewriteStyle)
    {
        $url = $this->getParam('_url');
        if (!$url) {
            $url = $this->getParam('url');
        }
        if ($apacheRewriteStyle) {
            // pass the query params found in REQUEST_URI to the target url.

            // this is a hack, as I do not know how to rewrite a url an
            // apache in a way that it is URL escaped.
            // anyone who does know: please contribute the info
            $parts = explode('?', $this->getRequestUri(), 2);
            if (isset($parts[1])) {
                $url .= '?' . $parts[1];
            }
        }
        $url = new Amcsi_HttpProxy_Url($url, $apacheRewriteStyle);
        return $url;
    }

    public function getInput()
    {
        return $this->input;
    }

    public function getRequestHeaders()
    {
        return $this->requestHeaders;
    }

    public function getEnv($name)
    {
        return isset($this->server[$name]) ? $this->server[$name] : null;
    }

    public function isHttps()
    {
        return 'on' == $this->getEnv('HTTPS') ||
            'true' == $this->getEnv('HTTP_SSL_CONNECTION');
    }
}
