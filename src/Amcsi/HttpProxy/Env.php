<?php
class Amcsi_HttpProxy_Env
{
    protected $input;
    protected $server;
    protected $requestHeaders;

    protected $url;
    protected $requestUri;
    protected $hostOrIp;
    protected $pathInfo;

    public function __construct(
        $input,
        array $server,
        array $requestHeaders
    ) {
        $this->input = $input;
        $this->server = $server;
        $this->requestHeaders = $requestHeaders;


    }

    public function getPathInfo()
    {
        if (!$this->pathInfo) {
            $pathInfo = $this->getEnv('PATH_INFO');
            if (false !== strpos($pathInfo, '?')) {
                $msg = "PATH_INFO cannot have a (?) mark in it. Currently it is: $pathInfo";
                throw new LogicException($msg);
            }
            $this->pathInfo = $pathInfo;
        }
        return $this->pathInfo;
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

            /**
             * We can't just use PATH_INFO unfortunately as it has url entities
             * decoded. We must use REQUEST_URI that keeps url encoding and
             * figure out a new, fake PATH_INFO that does contain url encoded characters.
             **/
            $reqUri = $this->getRequestUri();
            if (0 === strpos(
                $reqUri,
                $scriptName = $this->getEnv('SCRIPT_NAME'))
            ) {
                $strlen = strlen($scriptName);
                $pathInfoWithGet = substr($reqUri, $strlen);
                $parts = explode('?', $pathInfoWithGet, 2);
                # PATH_INFO, but keeping the URL encoded stuff!
                $pathInfo = $parts[0];
                $qs = isset($parts[1]) ? $parts[1] : null;
            } else if ($redUrl = $this->getEnv('REDIRECT_URL')) {
                $parts = explode('?', $reqUri, 2);
                $reqUriWithoutQuery = $parts[0];
                $qs = isset($parts[1]) ? $parts[1] : null;

                $reqUriPathParts = explode('/', $reqUriWithoutQuery);

                $realPathInfo = $this->getPathInfo();
                $realPathInfoPathParts = explode('/', $realPathInfo); 

                # redirect url parts
                $redUrlPathParts = explode('/', $redUrl);

                $rebuildPathInfoParts = array();

                while (
                    $realPathInfoPathParts &&
                    end($realPathInfoPathParts) === end($redUrlPathParts)
                ) {
                    array_pop($realPathInfoPathParts);
                    array_pop($redUrlPathParts);

                    // now take the part off the REQUEST_URI parts as it still has
                    // the url encoding entact.
                    $part = array_pop($reqUriPathParts);

                    array_unshift($rebuildPathInfoParts, $part);
                }

                $pathInfo = sprintf(
                    '%s/%s',
                    join('/', $realPathInfoPathParts),
                    join('/', $rebuildPathInfoParts)
                );
            } else {
                var_dump($reqUri, $scriptName, $this->server);
                throw new LogicException("Cannot determine path info");
            }

            /**
             * so /path/to/proxy.php/fakeGetParam=fakeGetVal&scheme=http/true-url.com/d/e/index.php?lol
             * turns into true-url.com/d/e/index.php
             * part 0: fakeGetParam=fakeGetVal&scheme=http
             * part 1: true-url.com/d/e/index.php?lol
             **/
            $parts = explode('/', ltrim($pathInfo, '/'), 2);
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

    public function isApacheRewriteStyle()
    {
        return !!$this->getEnv('REDIRECT_URL');
    }

    /**
     * Should be used only for non-apache-rewrite-style.
     * 
     * @access public
     * @return void
     */
    public function getBaseUrl()
    {
        $scheme = $this->getScheme();
        
        $scriptName = $this->getScriptName();

        $sourceBaseUrl = sprintf(
            "%s://%s%s",
            $scheme,
            $this->getHostOrIp(),
            $scriptName
        );
        return $sourceBaseUrl;
    }

    /**
     * Should be used only for non-apache-rewrite-style.
     * 
     * @access public
     * @return void
     */
    public function getScriptName()
    {
        $scriptName = $this->getEnv('SCRIPT_NAME');
        if (!$scriptName) {
            $reqUri = $this->getRequestUri();
            list($reqUriWithoutQuery) = explode('?', $reqUri, 2);
            $pathInfo = $this->getPathInfo();
            $useReqUri = $reqUriWithoutQuery;

            // subtract the pathinfo part from the request uri (without query)
            // to get the script name.
            $scriptName = str_replace($pathInfo, '', $useReqUri, $count);
        }
        return $scriptName;
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

    public function getScheme()
    {
        return $this->isHttps() ? 'https' : 'http';
    }

}
