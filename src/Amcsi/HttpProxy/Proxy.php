<?php
class Amcsi_HttpProxy_Proxy
{
    protected $expectedPasswordHash;
    protected $requirePassword = true;
    protected $forceSoapContentType;
    protected $proxyUrl;

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
        return $this;
    }

    public function dispatch() {
        $pass = isset($_REQUEST['pass']) ? $_REQUEST['pass'] : null;
        $requirePass = $this->requirePassword;
        if (
            !$requirePass ||
            crypt($pass, $this->expectedPasswordHash) === $this->expectedPasswordHash
        ) {
            if (isset($_GET['sleep'])) {
                sleep($_GET['sleep']);
            }
            $this->_pass = $pass;
            ini_set('display_errors', true);
            if (!empty($_REQUEST['force_soap_content_type'])) {
                $this->forceSoapContentType = true;
            }
            $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : null;
            switch ($action) {
            case 'auto_get_post':
            case 'auto_post':
            default:
                $url = $_GET['url'];
                $reqHeaders = apache_request_headers();
                $post = file_get_contents('php://input');
                $this->request($url, $reqHeaders, $post);;
                exit;
            }
        }
        else {
            echo 'Forbidden';
            header ("HTTP/1.1 403 Forbidden");
            exit;
        }
    }

    public function request($url, $reqHeaders, $post)
    {
        if (isset($_GET['sleep'])) {
            sleep($_GET['sleep']);
        }
        $headers = array ();
        foreach ($reqHeaders as $key => $val) {
            if ('Host' == $key) {
                continue;
            }
            if ('SOAPAction' == $key) {
                $headers[] = 'SOAPAction: ' . trim($reqHeaders[$key], '"');
            }
            else {
                $headers[] = sprintf('%s: %s', $key, $reqHeaders[$key]);
            }
        }
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
        $headers[] = sprintf("X-Forwarded-For: %s%s", $xForwardedForPrefix, getenv('REMOTE_ADDR'));
        $http = array(
            'method'  => getenv('REQUEST_METHOD'),
            'ignore_errors' => true // so the contents of non-2xx responses would be taken as well
        );
        if (isset($_GET['timeoutMs'])) {
            $http['timeout'] = $_GET['timeoutMs'] / 1000;
        }
        if ($post) {
            $http['content'] = $post;
            $headers[] = 'Content-Length: ' . strlen($post);
        }
        $header = join("\r\n", $headers);
        $http['header'] = $header;

        $opts = array('http' => $http);
        $context = stream_context_create($opts);

        $response = @file_get_contents($url, false, $context);
        $responseHeaders = isset($http_response_header) ? $http_response_header : null;
        $this->response($response, $responseHeaders);
    }

    public function response($response, $responseHeaders)
    {
        if (!empty($_REQUEST['proxify_urls'])) {
            $proxifyUrls = $_REQUEST['proxify_urls'];
            $response = $this->proxify($response, $proxifyUrls);
        }
        if (false && !$response) {
            var_dump($reqHeaders);
            var_dump($opts);
            var_dump($responseHeaders);
        }
        if (!empty($responseHeaders)) {
            foreach ($responseHeaders as $hrh) {
                if (0 === strpos($hrh, 'Set-Cookie:')) {
                    // filter so that the path would be right
                    $cookie = new Amcsi_HttpProxy_Cookie;
                    $targetHost = getenv('HTTP_HOST');
                    if (!$targetHost) {
                        $targetHost = getenv('SERVER_ADDR');
                    }
                    $cookie->setTargetHost($targetHost);
                    header(sprintf('Set-Cookie: %s', $cookie->getFilteredSetCookie()));
                    continue;
                }
                if (0 === strpos($hrh, 'Content-Type')) {
                    header($hrh);
                }
                // I don't remember why I'm doing this, since I'm overwriting the Content-Length anyway
                else if (0 !== strpos($hrh, 'Content-Length')) {
                    header($hrh);
                }
            }
            header(sprintf("Content-Length: %d", strlen($response)));
            echo $response;
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

    /**
     * Returns the base url needed for proxifying urls.
     * 
     * @access public
     * @return string
     */
    public function getProxyUrl() {
        if (!$this->proxyUrl) {
            $schema = 'on' == getenv('HTTPS') || 'true' == getenv('HTTP_SSL_CONNECTION') ?
                'https://' :
                'http://';
            $host = getenv('HTTP_HOST');
            if (!$host) {
                $host = getenv('SERVER_ADDR');
            }
            $pathWithGet = getenv('REQUEST_URI');
            $split = explode('?', $pathWithGet);
            $path = $split[0];
            $get = array ();
            $get['pass'] = $this->_pass;
            $get['action'] = 'auto_get_post';
            $get['proxify_urls'] = $_REQUEST['proxify_urls'];
            if ($this->forceSoapContentType) {
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
            $toReplace = html_entity_decode($toReplace, ENT_QUOTES, 'utf-8');
            $proxyUrl = $this->getProxyUrl();
            $replacement = $proxyUrl . '&url=' . rawurlencode($toReplace);
            // since we are working with xml tags and attributes, we must perform escaping.
            $replacement = htmlspecialchars($replacement);
        }
        $ret = $match[1] . $replacement . $match[3];
        return $ret;
    }
}
