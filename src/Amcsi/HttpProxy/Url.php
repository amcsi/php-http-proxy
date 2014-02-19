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

    /**
     * newInstanceByPathFromFakeGet 
     *
     * Creates a new instance of this class by a url subpath
     * beginning from the fakeGet part.
     *
     * e.g. /opts=u&scheme=http/target-url.com/d/e/lol.php?hey
     * 
     * @param string $path 
     * @static
     * @access public
     * @return self
     */
    public static function newInstanceByPathFromFakeGet($path)
    {
        $trimmed = ltrim($path, '/');
        $parts = explode('/', $trimmed, 2);
        parse_str($parts[0], $fakeGet);
        $scheme = isset($fakeGet['scheme']) ? $fakeGet['scheme'] : 'http';
        $url = sprintf('%s://%s', $scheme, $parts[1]);

        return new self($url, $fakeGet);
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
            $string = isset($this->fakeGet['opts']) ? $this->fakeGet['opts'] : '';
            $strlen = strlen($string);
            for ($i = 0; $i < $strlen; $i++) {
                $optChars[$string[$i]] = true;
            }
            $this->optChars = $optChars;
        }
        return $this->optChars;
    }

    public function getParam($name)
    {
        return isset($this->fakeGet[$name]) ? $this->fakeGet[$name] : null;
    }

    public function getHostOrIp()
    {
        $parsedUrl = parse_url($this->url);
        return $parsedUrl['host'];
    }

    public function getHost()
    {
        return $this->getHostOrIp();
    }

    public function __toString()
    {
        return (string) $this->url;
    }

    public function getRequestUri()
    {
        if (!$this->requestUri) {
            $this->requestUri = $this->getEnv('REQUEST_URI');
        }
        return $this->requestUri;
    }

    /**
     * Creates a new url object based on this one.
     * Merges a passed fakeGet with the contents of the ones here.
     * 
     * @param mixed $urlString 
     * @param array $fakeGetToMerge 
     * @access public
     * @return void
     */
    public function newMerged($urlString, array $fakeGetToMerge)
    {
        $fakeGet = array_merge($this->fakeGet, $fakeGetToMerge);
        return new self($urlString, $fakeGet);
    }

    /**
     * assembleUrlStringWithFakeGet 
     *
     * e.g.
     *  url: http://target-url.com/d/e/lol.php?hey
     *  fake get: [
     *      'opts' => 'u'
     *  ]
     *
     *  becomes:
     *  /opts=u&scheme=http/target-url.com/d/e/lol.php?hey
     * 
     * @access public
     * @return void
     */
    public function assembleUrlStringWithFakeGet()
    {
        $fakeGet = $this->fakeGet;
        $parsedUrl = parse_url($this->url);
        $fakeGet['scheme'] = isset($parsedUrl['scheme']) ? $parsedUrl['scheme'] : 'http';
        $urlWithoutProtocol = preg_replace('@^.*://@', '', $this->url);
        $fakeGetString = http_build_query($fakeGet);
        $ret = sprintf("/%s/%s", $fakeGetString, $urlWithoutProtocol);
        return $ret;
    }
}
