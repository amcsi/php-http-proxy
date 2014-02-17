<?php
class Amcsi_HttpProxy_Env
{
    protected $input;
    protected $server;
    protected $requestHeaders;

    protected $url;
    protected $requestUri;
    protected $hostOrIp;

    public function __construct(
        $input,
        array $server,
        array $requestHeaders
    ) {
        $this->input = $input;
        $this->server = $server;
        $this->requestHeaders = $requestHeaders;
    }

    /**
     * getUrlObjAndFakeGet 
     * 
     * @access public
     * @return array
     */
    public function getUrlObj()
    {
        if (!$this->url) {
            $pathinfo = $this->getEnv('PATH_INFO');
            if (!$pathinfo) {
                /**
                 * PATH_INFO should be existing, so this bit of fallback
                 * code should probably actually be deleted.
                 **/

                if (strpos(
                    $reqUri = $this->getRequestUri(),
                    $scriptName = $this->getEnv('SCRIPT_NAME'))
                ) {
                    $strlen = strlen($scriptName);
                    $pathInfo = substr($reqUri, $strlen);
                } else if ($redUrl = $this->getEnv('REDIRECT_URL')) {
                    // 
                }
            }

            /**
             * so /path/to/proxy.php/fakeGetParam=fakeGetVal&scheme=http/true-url.com/d/e/index.php?lol
             * turns into true-url.com/d/e/index.php
             * part 0: fakeGetParam=fakeGetVal&scheme=http
             * part 1: true-url.com/d/e/index.php?lol
             **/
            $parts = explode('/', ltrim($pathinfo, '/'), 2);
            parse_str($parts[0], $fakeGet);

            $reqUri = $this->getEnv('REQUEST_URI');
            $urlWithoutProtocol = $parts[1];
            if (false !== strpos($reqUri, '?')) {
                $urlWithoutProtocol .= '?' . $this->getEnv('QUERY_STRING');
            }

            $scheme = isset($fakeGet['scheme']) ? $fakeGet['scheme'] : 'http';
            $url = sprintf("%s://%s", $scheme, $urlWithoutProtocol);
            $url = new Amcsi_HttpProxy_Url($url, $fakeGet);
            $this->url = $url;
        }
        return $this->url;
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
