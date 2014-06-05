<?php
class Amcsi_HttpProxy_Response
{

    protected $content;
    protected $headers;

    public function __construct($content, $headers)
    {
        $this->setContentAndHeaders($content, $headers);
    }

    public function setContentAndHeaders($content, $headers)
    {
        $this->content = $content;
        $this->headers = $headers;
    }

    public function setContent($content)
    {
        $this->content = $content;
        return $this;
    }

    public function setHeaders(array $headers)
    {
        $this->headers = $headers;
        return $this;
    }

    public function getContent()
    {
        return (string) $this->content;
    }

    public function getHeaders()
    {
        return (array) $this->headers;
    }

    public function __toString()
    {
        return $this->getContent();
    }

    /**
     * Returns whether the content is gzipped
     * 
     * @access public
     * @return boolean
     */
    public function isGzipped()
    {
        $ret = false;
        foreach ($this->getHeaders() as $header) {
            if (0 === strpos(strtolower($header), 'content-encoding')) {
                if (false !== strpos($header, 'gzip')) {
                    $ret = true;
                }
                break;
            }
        }

        return $ret;
    }
}

