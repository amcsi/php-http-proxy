<?php
class Amcsi_HttpProxy_Url
{
    protected $url;
    protected $fakeGet;

    protected $optChars;

    /**
     * __construct 
     * 
     * @param string $urlString  URL string (target true url)
     * @param array $fakeGet     Fake GET params
     * @access public
     * @return void
     */
    public function __construct($urlString, array $fakeGet)
    {
        $this->url = $urlString;
        $this->fakeGet = $fakeGet;
    }

    public function isOptSet($optChar)
    {
        $optChars = $this->getOptChars();
        return !empty($optChars[$optChar]);
    }

    public function getOptChars()
    {
        if (!$this->optChars) {
            $optChars = array();
            $string = isset($fakeGet['opts']) ? $fakeGet['opts'] : '';
            $strlen = strlen($string);
            for ($i = 0; $i < $strlen; $i++) {
                $optChars[$string[$i]] = true;
            }
            $this->optChars = $optChars;
        }
        return $this->optChars;
    }

    /**
     * With this method, it should be able to be determined that
     * based off the REQUEST_URI and a target url, we can find
     * out what parts of urls on the page to be rewritten to
     * what when using RewriteRule style proxying
     * 
     * @param Amcsi_HttpProxy_Env $env 
     * @access public
     * @return array
     */
    public function getRewriteDetails(Amcsi_HttpProxy_Env $env)
    {
        $reqUri = $env->getRequestUri();
        $host = $env->getHostOrIp();
        return $this->getRewriteDetailsByReqUriAndHost($reqUri, $host);
    }

    public function getRewriteDetailsByReqUriAndHost($reqUri, $host)
    {
        $strpos = strpos($this->url, $reqUri);
        $parsedReqUri = parse_url($reqUri);
        $parsedUrl = parse_url($this->url);

        $mutualPartsReversed = array();
        $urlParts = explode('/', $this->url);
        $reqUriParts = explode('/', $reqUri);
        while ($urlParts && $reqUriParts) {
            $part = array_pop($urlParts);
            if ($part === end($reqUriParts)) {
                $mutualPartsReversed[] = array_pop($reqUriParts);
            } else {
                /**
                 * Put the popped parts back
                 **/
                $urlParts[] = $part;
                break;
            }
        }
        $urlRemainingParts = join('/', $urlParts);
        $reqUriRemainingParts = join('/', $reqUriParts);
        $targetHost = $this->getHost();
        $trueHostPathQuery = "$urlRemainingParts/";
        $trueHostPathQuery = preg_replace('@^https?://@', '', $trueHostPathQuery);
        $ret = array();
        $ret['proxyPath']               = "$reqUriRemainingParts/";
        $ret['proxyHostPath']           = sprintf('%s%s/', $host, $reqUriRemainingParts);
        $ret['proxyProtocolHostPath']   = sprintf('http://%s%s/', $host, $reqUriRemainingParts);
        $ret['trueHostPathQuery']       = $trueHostPathQuery;
        $ret['trueScheme']              = $parsedUrl['scheme'];
        return $ret;
    }

    public function getParam($name)
    {
        return isset($this->fakeGet[$name]) ? $this->fakeGet[$name] : null;
    }

    public function getHost()
    {
        $parsedUrl = parse_url($this->url);
        return $parsedUrl['host'];
    }

    public function __toString()
    {
        return (string) $this->url;
    }

    public function isApacheRewriteStyle()
    {
        return $this->isApacheRewriteStyle;
    }

    public function getRequestUri()
    {
        if (!$this->requestUri) {
            $this->requestUri = $this->getEnv('REQUEST_URI');
        }
        return $this->requestUri;
    }
}
