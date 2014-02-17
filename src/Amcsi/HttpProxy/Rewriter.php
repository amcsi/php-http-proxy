<?php
class Amcsi_HttpProxy_Rewriter
{
    protected $url;
    protected $env;

    protected $proxyUrl;

    public function __construct(
        Amcsi_HttpProxy_Env $env,
        Amcsi_HttpProxy_Url $url = null
    ) {
        $this->env = $env;
        if (!$url) {
            $url = $env->getUrlObj();
        }
        $this->url = $url;
    }

    /**
     * Returns the base url needed for proxifying urls.
     * 
     * @access public
     * @return string
     */
    public function getProxyUrl() {
        if (!$this->proxyUrl) {
            $schema = $this->env->isHttps() ?  'https://' : 'http://';
            $host = $this->env->getHostOrIp();
            $pathWithGet = $this->env->getEnv('REQUEST_URI');
            $split = explode('?', $pathWithGet);
            $path = $split[0];
            $get = array ();
            $get['pass'] = $this->env->getParam('pass');
            $get['opts'] = $this->env->getParam('opts');
            if ($this->env->getParam('force_soap_content_type')) {
                $get['force_soap_content_type'] = 1;
            }
            $path .= '?' . http_build_query($get);
            $proxyUrl = sprintf('%s%s%s', $schema, $host, $path);
            $this->proxyUrl = $proxyUrl;
        }
        return $this->proxyUrl;
    }

    /**
     * Replaces all urls in HTML tags and attributes in given text to
     * a format that would allow proxying from this script.
     * 
     * @param string $text 
     * @access public
     * @return string
     */
    public function proxify($text) {
        $pattern = '@(["\'>])(https?://[^"\'<>]+)(["\'<])@';
        $callback = array ($this, '_proxifyReplaceCallback');
        $replaced = preg_replace_callback($pattern, $callback,
            $text);
        return $replaced;
    }

    /**
     * Callback for preg_replace_callback() for proxyizing urls.
     * The url is always in $match[2] with surrounding strings
     * (such as quotation marks) that come before the url are in
     * $match[1] (prepended) and $match[3]. They will resurround
     * the result url in the end.
     *
     * @param string $match 
     * @access protected
     * @return string
     */
    protected function _proxifyReplaceCallback($match) {
        static $ignoreUrlParts = array ();
        static $ignoreExtensions = array ();
        if (!$ignoreUrlParts) {
        }
        $toReplace = $match[2];
        $replacement = null;
        $pathinfo = pathinfo($match[2]);
        /**
         * Do not proxify images
         */
        $ext = isset($pathinfo['extension']) ? $pathinfo['extension'] : null;
        if (in_array(strtolower($ext), $ignoreExtensions)) {
            return $match[0];
        }
        foreach ($ignoreUrlParts as $iup) {
            if (false !== strpos($toReplace, $iup)) {
                return $match[0];
            }
        }
        if (!$replacement) {
            $replacement = $this->replaceUrl($match[2]);
        }
        $ret = $match[1] . $replacement . $match[3];
        return $ret;
    }

    /**
     * Proxify a URL 
     * 
     * @param mixed $toReplace 
     * @access protected
     * @return void
     */
    protected function replaceUrl($toReplace)
    {
        if ($this->isApacheRewriteStyle()) {
            $reqUri = $this->getRequestUri();
            $host = $this->env->getHostOrIp();
            $rewriteDetails = $this->url->getRewriteDetails($reqUri, $host);
            $replaceThese = array(
                "http://$rewriteDetails[trueHostPathQuery]",
                "https://$rewriteDetails[trueHostPathQuery]",
            );
            $replacement = str_replace(
                $replaceThese,
                $rewriteDetails['proxyProtocolHostPath'],
                $toReplace
            );
        }
        else {
            $toReplace = html_entity_decode($toReplace, ENT_QUOTES, 'utf-8');
            $proxyUrl = $this->getProxyUrl();
            $replacement = $proxyUrl . '&url=' . rawurlencode($toReplace);
            // since we are working with xml tags and attributes, we must perform escaping.
            $replacement = htmlspecialchars($replacement);
        }
        return $replacement;
    }

    /**
     * Unproxify a URL
     * 
     * @access protected
     * @return void
     */
    protected function reverseReplaceUrl($toReplace)
    {
        if ($this->isApacheRewriteStyle()) {
            $reqUri = $this->getRequestUri();
            $host = $this->env->getHostOrIp();
            $rewriteDetails = $this->url->getRewriteDetails($reqUri, $host);
            $replaceThese = array(
                "http://$rewriteDetails[proxyHostPath]",
                "https://$rewriteDetails[proxyHostPath]",
            );
            $rewrite = sprintf(
                '%s://%s',
                $rewriteDetails['trueScheme'],
                $rewriteDetails['trueHostPathQuery']
            );
            $replacement = str_replace($replaceThese, $rewrite, $toReplace);
        }
        else {
            $parsedUrl = parse_url($toReplace);
            parse_str($parsedUrl['query'], $query);
            if (isset($query['_url'])) {
                $url = $query['_url'];
            } elseif (isset($query['url'])) {
                $url = $query['url'];
            }
            if ($url) {
                $replacement = $url;
            }
        }
        return $replacement;

    }

    /**
     * rewriteCookieHeader 
     * 
     * @param string $cookieHeaderString 
     * @access public
     * @return string
     */
    public function rewriteCookieHeader($cookieHeaderString)
    {
        // filter so that the path would be right
        $cookie = new Amcsi_HttpProxy_Cookie;
        $targetHost = $this->env->getEnv('HTTP_HOST');
        if (!$targetHost) {
            $targetHost = $this->env->getEnv('SERVER_ADDR');
        }
        $cookie->setTargetHost($targetHost);
        $cookie->setCookieHeaderValue(substr($cookieHeaderString, 12));
        if ($this->isApacheRewriteStyle()) {
            $rewriteDetails = $this->getRewriteDetails();
            $cookie->setSourcePath($rewriteDetails['proxyPath']);
        }
        $ret = sprintf('Set-Cookie: %s', $cookie->getFilteredSetCookie());
        return $ret;
    }

    public function getRewriteDetails()
    {
        $rewriteDetails = $this->url->getRewriteDetails($this->env);
        return $rewriteDetails;
    }

    public function isApacheRewriteStyle()
    {
        return $this->url->isApacheRewriteStyle();
    }
}
