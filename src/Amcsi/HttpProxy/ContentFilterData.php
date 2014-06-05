<?php
class Amcsi_HttpProxy_ContentFilterData
{
    private $request;
    private $response;

    /**
     * __construct 
     * 
     * @param Amcsi_HttpProxy_Request $request 
     * @param Amcsi_HttpProxy_Response $Amcsi_HttpProxy_Response 
     * @access public
     * @return void
     */
    public function __construct(
        Amcsi_HttpProxy_Request $request,
        Amcsi_HttpProxy_Response $response
    ) {
        $this->request = $request;
        $this->response = $response;
    }

    /**
     * getResponseContent 
     * 
     * @access public
     * @return string
     */
    public function getResponseContent()
    {
        return $this->response->getContent();
    }

    /**
     * getResponseContentDecoded 
     * 
     * @access public
     * @return string
     */
    public function getResponseContentDecoded()
    {
        $content = $this->getResponseContent();

        return $this->response->isGzipped() ? gzdecode($content) : $content;
    }

    /**
     * encodeContent 
     * 
     * @param string $content 
     * @access public
     * @return string
     */
    public function encodeContent($content)
    {
        return $this->response->isGzipped() ? gzencode($content) : $content;
    }
}
