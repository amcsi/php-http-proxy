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
        $result = $this->url->getRewriteDetails($reqUri, $host);
        $expected = array(
            'proxyHostPath' => 'source-url.com/a/b/c/',
            'proxyProtocolHostPath' => 'http://source-url.com/a/b/c/',
            'trueHostPathQuery' => 'target-url.com/d/e/',
            'trueScheme' => 'http',
        );
        $this->assertEquals($expected, $result);
    }
}

