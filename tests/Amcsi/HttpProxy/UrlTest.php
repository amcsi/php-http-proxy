<?php
class UrlTest extends PHPUnit_Framework_TestCase
{

    private $url;

    public function setUp()
    {
        $url = new Amcsi_HttpProxy_Url('http://target-url.com/d/e/x/y/z.php');
        $this->url = $url;
    }

    public function tearDown()
    {
        unset($this->url);
    }

    public function testGetRewriteDetails()
    {
        $reqUri = '/a/b/c/x/y/z.php';
        $host = 'source-url.com';
        $result = $this->url->getRewriteDetailsByReqUriAndHost($reqUri, $host);
        $expected = array(
            'proxyPath' => '/a/b/c/',
            'proxyHostPath' => 'source-url.com/a/b/c/',
            'proxyProtocolHostPath' => 'http://source-url.com/a/b/c/',
            'trueHostPathQuery' => 'target-url.com/d/e/',
            'trueScheme' => 'http',
        );
        $this->assertEquals($expected, $result);
    }

    public function testGetRewriteDetailsWithQuery()
    {
        $this->url = $url = new Amcsi_HttpProxy_Url(
            'http://target-url.com/d/e/x/y/z.php?foo=bar'
        );
        $reqUri = '/a/b/c/x/y/z.php?foo=bar';
        $host = 'source-url.com';
        $result = $this->url->getRewriteDetailsByReqUriAndHost($reqUri, $host);
        $expected = array(
            'proxyPath' => '/a/b/c/',
            'proxyHostPath' => 'source-url.com/a/b/c/',
            'proxyProtocolHostPath' => 'http://source-url.com/a/b/c/',
            'trueHostPathQuery' => 'target-url.com/d/e/',
            'trueScheme' => 'http',
        );
        $this->assertEquals($expected, $result);
    }
}

