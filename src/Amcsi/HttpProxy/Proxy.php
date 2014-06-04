<?php
class Amcsi_HttpProxy_Proxy
{
    /**
     * Contains the environment information to use
     * 
     * @var Amcsi_HttpProxy_Env
     * @access protected
     */
    protected $env;
    protected $get;

    protected $expectedPasswordHash;
    protected $config;
    protected $requirePassword = true;
    /**
     * Array of option characters set.
     *
     * p: POST should be taken into account when checking for REQUEST
     *  variables. Also, POST should be used for proxified urls
     * r: Use RewriteRule based rewriting
     * u: Rewrite all XML/HTML attributes to make all urls in them
     *  proxified.
     * 
     * @var array
     * @access protected
     */
    protected $optChars;
    protected $rewriteDetails;
    /**
     * Url object.
     * Has information on the target url, and has
     * get-style options. 
     * 
     * @var Amcsi_HttpProxy_Url
     * @access protected
     */
    protected $url;

    protected $requestUri;
    protected $host;

    protected $reqHeaders;
    protected $post;

    public function __construct(Amcsi_HttpProxy_Env $env)
    {
        $this->env = $env;
        $urlObj = $env->getUrlObj();
        $this->url = $urlObj;
    }

    public function setConf(array $conf)
    {
        $map = array(
            'expectedPasswordHash', 'requirePassword'
        );
        foreach ($map as $thisMember) {
            $key = $thisMember;
            if (array_key_exists($key, $conf)) {
                $this->$thisMember = $conf[$key];
            }
        }
        $this->config = $conf;
        return $this;
    }

    /**
     * getUrlObj 
     * 
     * @access public
     * @return Amcsi_HttpProxy_Url
     */
    public function getUrlObj()
    {
        return $this->url;
    }

    public function isOptSet($optChar)
    {
        return $this->url->isOptSet($optChar);
    }

    public function setOptsString($string)
    {
        $optChars = array();
        $strlen = strlen($string);
        for ($i = 0; $i < $strlen; $i++) {
            $optChars[$string[$i]] = true;
        }
        $this->optChars = $optChars;
        return $this;
    }

    /**
     * It is the URL object whose params should be taken into
     * account.
     * 
     * @param mixed $name 
     * @access public
     * @return void
     */
    public function getParam($name)
    {
        return $this->url->getParam($name);
    }

    public function dispatch() {
        $opts = $this->getParam('opts');
        $this->setOptsString($opts);
        $pass = $this->getParam('pass');
        $requirePass = $this->requirePassword;
        if (
            !$requirePass ||
            crypt($pass, $this->expectedPasswordHash) === $this->expectedPasswordHash
        ) {
            if ($sleep = $this->getParam('sleep')) {
                sleep($sleep);
            }
            $this->_pass = $pass;
            ini_set('display_errors', true);
            $url = $this->getUrlObj();
            $this->url = $url;
            $rewriter = new Amcsi_HttpProxy_Rewriter($this->env, $url);
            $this->rewriter = $rewriter;
            return $this->request($url);
        }
        else {
            echo 'Forbidden';
            header ("HTTP/1.1 403 Forbidden");
            exit;
        }
    }

    public function request($url)
    {
        $reqHeaders = $this->env->getRequestHeaders();
        $headers = array ();
        foreach ($reqHeaders as $key => $val) {
            if ('Host' == $key || 'Content-Length' == $key || 'Connection' == $key) {
                continue;
            }
            if ('SOAPAction' == $key) {
                $headers[] = 'SOAPAction: ' . trim($reqHeaders[$key], '"');
            } elseif ('Referer' == $key && $this->isOptSet('r')) {
                $headers[] = 'Referer: ' . $this->rewriter->reverseReplaceUrl($val);
            } else {
                $headers[] = sprintf('%s: %s', $key, $reqHeaders[$key]);
            }
        }
        $headers[] = 'Connection: close';
        $request = new Amcsi_HttpProxy_Request;
        $request->setUrl($url);

        $parsedUrl = parse_url($url);
        if (filter_var($parsedUrl['host'], FILTER_VALIDATE_IP)) {
            // host is IP
        } else {
            $headers[] = "Host: $parsedUrl[host]";
        }
        $xForwardedForPrefix = '';
        if (!empty($reqHeaders['X-Forwarded-For'])) {
            $xForwardedForPrefix = $reqHeaders['X-Forwarded-For'] . ', ';
        }
        $headers[] = sprintf("X-Forwarded-For: %s%s", $xForwardedForPrefix, $this->env->getEnv('REMOTE_ADDR'));
        $request->setMethod($this->env->getenv('REQUEST_METHOD'));
        if ($timeoutMs = $this->getParam('timeoutMs')) {
            $request->setTimeoutMs($timeoutMs);
        }
        $request->setHeaders($headers);
        $request->setContentAndLength($this->env->getInput());

        $this->debugLog('request url', (string) $url);
        $this->debugLog('request headers', $headers);

        $response = $request->doRequest();

        $this->debugLog('response headers', $response->getHeaders());
        $this->debugLog('response content', $response->getContent());
        $proxyResponse = $this->response($request, $response);

        foreach ($proxyResponse->getHeaders() as $header) {
            header($header);
        }
        echo $proxyResponse->getContent();

        exit(0);
    }

    /**
     * response
     * 
     * @param Amcsi_HttpProxy_Request $request 
     * @param Amcsi_HttpProxy_Response $response    Response received
     * @access public
     * @return Amcsi_HttpProxy_Response             Proxied response
     */
    public function response(
        Amcsi_HttpProxy_Request $request,
        Amcsi_HttpProxy_Response $response
    ) {
        $content = $response->getContent();

        if ($contentFilters = $this->config['contentFilters']) {
            foreach ((array) $contentFilters as $contentFilter) {
                if (is_callable($contentFilter)) {
                    /**
                     * $request and $response are not immutable, so clone them
                     **/
                    $contentFilterData = new Amcsi_HttpProxy_ContentFilterData(
                        clone $request,
                        clone $response
                    );

                    $result = call_user_func($contentFilter, $contentFilterData);
                    if (is_string($result)) {
                        $content = $result;
                    }
                }
            }
        }

        $responseHeaders = $response->getHeaders();
        if (false && !$content) {
            var_dump($reqHeaders);
            var_dump($opts);
            var_dump($responseHeaders);
        }
        $returnHeaders = array();
        if (!empty($responseHeaders)) {
            foreach ($responseHeaders as $index => $hrh) {
                if (0 === strpos($hrh, 'Connection:')) {
                    continue;
                }
                if (0 === strpos($hrh, 'Set-Cookie:')) {
                    $newHeader = $this->rewriter->rewriteCookieHeader($hrh);
                    $returnHeaders[] = $newHeader;
                    continue;
                }
                if (0 === strpos($hrh, 'Content-Type')) {
                    if ($this->isOptSet('u') && false !== strpos($hrh, 'text/')) {
                        $content = $this->rewriter->proxify($content);
                    }
                }
                if (0 === strpos($hrh, 'Location:') && $this->isOptSet('u')) {
                    $location = substr($hrh, 10);
                    $proxifiedLocation = $this->rewriter->replaceUrl($location);
                    $returnHeaders[] = "Location: $proxifiedLocation";
                } elseif (0 === strpos($hrh, 'Transfer-Encoding: chunked')) {
                    continue;
                } else if (0 !== strpos($hrh, 'Content-Length')) {
                    $returnHeaders[] = $hrh;
                }
            }
        }
        else {
            $lastError = error_get_last();
            if (false !== strpos($lastError['message'], 'Connection timed out')) {
                $returnHeaders[] = 'HTTP/1.1 504 Gateway Timeout';
            }
            else {
                ob_start();
                trigger_error(print_r($lastError, true));
                ob_end_clean();
                $returnHeaders[] = 'HTTP/1.1 500 Internal Server Error';
            }
        }
        $returnResponse = new Amcsi_HttpProxy_Response($content, $returnHeaders);

        return $returnResponse;
    }

    public function debugLog($label, $value) {
        if ($this->config['debugLogFile']) {
            $text = sprintf("%s: %s\n", $label, print_r($value, true));
            error_log($text, 3, $this->config['debugLogFile']);
        }
    }
}
