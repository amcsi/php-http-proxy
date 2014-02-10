<?php
interface Amcsi_HttpProxy_HttpClient_Interface
{
    public function setMethod($method);

    public function setContent($content);

    public function doRequest($url, $headers);
}

