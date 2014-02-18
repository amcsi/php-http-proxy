<?php
class Amcsi_HttpProxy_HttpClient_Curl extends Amcsi_HttpProxy_HttpClient_Abstract
{
    public function doRequest($url, $headers)
    {
        error_reporting(E_ALL);
        ini_set('display_errors', true);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        if ($this->method && 'GET' !== strtoupper($this->method)) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            if ($this->content) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $this->content);
            }
        }
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);                                                      
        if ($this->timeout) {
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, $this->timeout * 1000);
        }
        $result = curl_exec($ch);
        $headerContent = explode("\r\n\r\n" , $result , 2);
        $header = $headerContent[0];
        $responseContent = isset($headerContent[1]) ? $headerContent[1] : null;
        $responseHeaders = explode("\r\n", $header);
        $response = new Amcsi_HttpProxy_Response(
            $responseContent,
            $responseHeaders
        );
        curl_close($ch);
        return $response;
    }
}
