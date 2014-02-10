<?php
class Amcsi_HttpProxy_HttpClient_FileGetContents implements Amcsi_HttpProxy_HttpClient_Interface
{
    protected $method = 'GET';
    protected $timeout;

    public function setMethod($method)
    {
        $this->method = $method;
        return $this;
    }

    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;
    }

    public function setContent($content)
    {
        $this->content = $content;
        return $this;
    }

    public function doRequest($url, $headers)
    {
        $http = array(
            'method'  => $this->method,
            'follow_location' => 0,
            'ignore_errors' => true // so the contents of non-2xx responses would be taken as well
        );
        $connectionCloseHeaderSent = false;
        /**
         * Make sure Connection: close is being sent, otherwise
         * file_get_contents() will be really slow!
         **/
        foreach ($headers as $key => &$val) {
            if (0 === strpos('Connection: ', $val)) {
                if (false !== strpos('keep-alive')) {
                    $val = "Connection: close";
                    $connectionCloseHeaderSent = true;
                    break;
                } else {
                    $connectionCloseHeaderSent = true;
                }
            }
        }
        unset($val);
        if (!$connectionCloseHeaderSent) {
            $headers[] = "Connection: close";
            $connectionCloseHeaderSent = true;
        }

        $http['header'] = join("\r\n", $headers);
        if ($this->timeout) {
            $http['timeout'] = $this->timeout;
        }
        if ($this->content) {
            $http['content'] = $this->content;
        }
        $opts = array('http' => $http);
        $context = stream_context_create($opts);
        $responseContent = @file_get_contents($url, false, $context);
        $responseHeaders = isset($http_response_header) ? $http_response_header : null;
        $response = new Amcsi_HttpProxy_Response($responseContent, $responseHeaders);
        return $response;
    }
}
