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
     * url 
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

    public function getGetPost($name)
    {
        return $this->env->getParam($name);
    }

    public function isOptSet($optChar)
    {
        return !empty($this->optChars[$optChar]);
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

    public function getRequestUri()
    {
        if (!$this->requestUri) {
            $this->requestUri = $this->env->getEnv('REQUEST_URI');
        }
        return $this->requestUri;
    }

    public function dispatch() {
        $opts = $this->env->getParam('opts');
        $this->setOptsString($opts);
        $pass = $this->env->getParam('pass');
        $requirePass = $this->requirePassword;
        if (
            !$requirePass ||
            crypt($pass, $this->expectedPasswordHash) === $this->expectedPasswordHash
        ) {
            if ($sleep = $this->env->getParam('sleep')) {
                sleep($sleep);
            }
            $this->_pass = $pass;
            ini_set('display_errors', true);
            $apacheStyleRewriting = $this->isOptSet('r');
            $url = $this->env->getUrlObj($apacheStyleRewriting);
            $this->url = $url;
            $rewriter = new Amcsi_HttpProxy_Rewriter($url, $this->env);
            $this->rewriter = $rewriter;
            $this->request($url);
            exit;
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
            if ('Host' == $key || 'Content-Length' == $key) {
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
        if ($timeoutMs = $this->getGetPost('timeoutMs')) {
            $request->setTimeoutMs($timeoutMs);
        }
        $request->setHeaders($headers);
        $request->setContentAndLength($this->env->getInput());

        $this->debugLog('request url', (string) $url);
        $this->debugLog('request headers', $headers);

        $response = $request->doRequest();

        $this->debugLog('response headers', $response->getHeaders());
        $this->debugLog('response content', $response->getContent());
        $this->response($response);
    }

    public function response(Amcsi_HttpProxy_Response $response)
    {
        $content = $response->getContent();
        $responseHeaders = $response->getHeaders();
        if ($this->isOptSet('u')) {
            $content = $this->rewriter->proxify($content);
        }
        if (false && !$content) {
            var_dump($reqHeaders);
            var_dump($opts);
            var_dump($responseHeaders);
        }
        if (!empty($responseHeaders)) {
            foreach ($responseHeaders as $index => $hrh) {
                if (0 === strpos($hrh, 'Set-Cookie:')) {
                    $newHeader = $this->rewriter->rewriteCookieHeader($hrh);
                    header($newHeader, false);
                    continue;
                }
                if (0 === strpos($hrh, 'Location:') && $this->isOptSet('u')) {
                    $location = substr($hrh, 10);
                    $proxifiedLocation = $this->rewriter->replaceUrl($location);
                    header("Location: $proxifiedLocation");
                } elseif (0 === strpos($hrh, 'Transfer-Encoding: chunked')) {
                    continue;
                } else if (0 !== strpos($hrh, 'Content-Length')) {
                    header($hrh);
                }
            }
            header(sprintf("Content-Length: %d", strlen($content)));
            echo $content;
        }
        else {
            $lastError = error_get_last();
            if (false !== strpos($lastError['message'], 'Connection timed out')) {
                header('HTTP/1.1 504 Gateway Timeout');
            }
            else {
                ob_start();
                trigger_error(print_r($lastError, true));
                ob_end_clean();
                header('HTTP/1.1 500 Internal Server Error');
            }
            exit;
        }
    }

    public function debugLog($label, $value) {
        if ($this->config['debugLogFile']) {
            $text = sprintf("%s: %s\n", $label, print_r($value, true));
            error_log($text, 3, $this->config['debugLogFile']);
        }
    }
}
