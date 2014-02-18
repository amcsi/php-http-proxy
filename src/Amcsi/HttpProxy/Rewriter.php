<?php
class Amcsi_HttpProxy_Rewriter
{
    protected $url;
    protected $env;

    protected $proxyUrl;
    protected $cookieRewriter;

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

    public function getParam($name)
    {
        return $this->url->getParam($name);
    }

    /**
     * getRewriteDetails 
     *
     * @todo Cover by tests!
     * 
     * @access public
     * @return void
     */
    public function getRewriteDetails()
    {
        $reqUri = $this->env->getRequestUri();
        $host = $this->env->getHostOrIp();

        $strpos = strpos($this->url, $reqUri);
        $parsedReqUri = parse_url($reqUri);
        $parsedUrl = parse_url($this->url);

        $mutualPartsReversed = array();

        $urlQuerySeparated = explode('?', $this->url);
        $urlWithoutQuery = $urlQuerySeparated[0];
        $urlParts = explode('/', $urlWithoutQuery);
        $reqUriQuerySeparated = explode('?', $reqUri);
        $reqUriWithoutQuery = $reqUriQuerySeparated[0];
        $reqUriParts = explode('/', $reqUriWithoutQuery);

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
        $proxyHost = $this->env->getHostOrIp();
        $trueHostPathQuery = "$urlRemainingParts/";
        $trueHostPathQuery = preg_replace('@^https?://@', '', $trueHostPathQuery);

        $cookieBase = "$reqUriRemainingParts/";
        if (!$this->isApacheRewriteStyle()) {
            $cookieBase .= $this->url->getHostOrIp() . '/';
        }

        $ret = array();
        $ret['proxyPath']               = "$reqUriRemainingParts/";
        $ret['proxyHostPath']           = sprintf('%s%s/', $host, $reqUriRemainingParts);
        $ret['proxyProtocolHostPath']   = sprintf('http://%s%s/', $host, $reqUriRemainingParts);
        $ret['trueHostPathQuery']       = $trueHostPathQuery;
        $ret['trueScheme']              = $parsedUrl['scheme'];
        // # e.g. /a/b/c/proxy.php/opts=u&scheme=http/target-url.com/
        $ret['cookieBase']              = $cookieBase;

        /**
         * for apache-rewriterule style only
         */
        # cut off from the beginning until the nearest slash, e.g. target-url.com/d/e/ => /d/e/
        $ret['truePath']                = preg_replace('@^[^/]*@', '', $trueHostPathQuery);

        /**
         * for non-apache-rewriterule style only
         */
        $ret['proxyPhpUrl']             =   sprintf(
                                                '%s://%s%s',
                                                $parsedUrl['scheme'],
                                                $host,
                                                $this->env->getScriptName()
                                            );
        return $ret;
    }

    /**
     * Returns the base url needed for proxifying urls.
     * 
     * @access public
     * @return string
     */
    public function getProxyUrl()
    {
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
    protected function _proxifyReplaceCallback($match)
    {
        static $ignoreUrlParts = array ();
        static $ignoreExtensions = array ();
        if (!$ignoreUrlParts) {
        }
        $toReplace = html_entity_decode($match[2]);
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
        $ret = $match[1] . htmlspecialchars($replacement) . $match[3];
        return $ret;
    }

    /**
     * Proxify a URL 
     * 
     * @param mixed $toReplace 
     * @access protected
     * @return void
     */
    public function replaceUrl($toReplace)
    {
        $parsedUrl = parse_url($toReplace);

        if ($this->isApacheRewriteStyle()) {
            $rewriteDetails = $this->getRewriteDetails();


            $replaceThese = array(
                "http://$rewriteDetails[trueHostPathQuery]",
                "https://$rewriteDetails[trueHostPathQuery]",
            );
            $replacement = str_replace(
                $replaceThese,
                $rewriteDetails['proxyProtocolHostPath'],
                $toReplace
            );

        } else {
            $newUrl = $this->url->newMerged($toReplace, array());
            $scheme = $this->env->getScheme();

            # http://proxy-url.com/a/b/c/proxy.php
            $sourceBaseUrl = $this->env->getBaseUrl();

            # /opts=u&scheme=http/target-url.com/d/e/lol?foo=bar
            $assembledUrlPart = $newUrl->assembleUrlStringWithFakeGet();
            $rewriteDetails = $this->getRewriteDetails();

            $replacement = rtrim($sourceBaseUrl, '/') . $assembledUrlPart;
        }

        return $replacement;
    }

    /**
     * Unproxify a URL
     * 
     * @access protected
     * @return void
     */
    public function reverseReplaceUrl($toReplace)
    {
        $replacement = $toReplace;
        $rewriteDetails = $this->getRewriteDetails();
        if ($this->isApacheRewriteStyle()) {
            $reqUri = $this->env->getRequestUri();
            $host = $this->env->getHostOrIp();
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
            $proxyPhpUrl = $rewriteDetails['proxyPhpUrl'];
            $pathInfoWithGet = str_replace($proxyPhpUrl, '', $toReplace, $count);
            if ($count) {
                $newUrl = Amcsi_HttpProxy_Url::newInstanceByPathFromFakeGet(
                    $pathInfoWithGet
                );
                $replacement = (string) $newUrl;
            } else {
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
        list(,$cookieValue) = explode(': ', $cookieHeaderString, 2);
        $cookieRewriter = $this->getCookieRewriter();
        $ret = sprintf(
            'Set-Cookie: %s',
            $cookieRewriter->getFilteredSetCookie($cookieValue)
        );
        return $ret;
    }

    public function getCookieRewriter()
    {
        if (!$this->cookieRewriter) {
            // filter so that the path would be right
            $cookieRewriter = new Amcsi_HttpProxy_CookieRewriter;
            $targetHost = $this->env->getEnv('HTTP_HOST');
            if (!$targetHost) {
                $targetHost = $this->env->getEnv('SERVER_ADDR');
            }
            $rewriteDetails = $this->getRewriteDetails();
            $cookieRewriter->setTargetHost($targetHost);
            $cookieRewriter->setSourcePath($rewriteDetails['cookieBase']);
            if (!$this->isApacheRewriteStyle()) {
                $cookieRewriter->setTargetPath('/');
            } else {
                $cookieRewriter->setTargetPath($rewriteDetails['truePath']);
            }
            $this->cookieRewriter = $cookieRewriter;
        }
        return $this->cookieRewriter;
    }

    public function isApacheRewriteStyle()
    {
        return $this->env->isApacheRewriteStyle();
    }
}
