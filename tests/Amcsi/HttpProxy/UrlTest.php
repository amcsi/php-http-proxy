<?php
class UrlTest extends PHPUnit_Framework_TestCase
{

    private $url;

    public function setUp()
    {
        $url = new Amcsi_HttpProxy_Url('http://target-url.com/d/e/x/y/z.php', array());
        $this->url = $url;
    }

    public function tearDown()
    {
        unset($this->url);
    }

    public function testNothing()
    {
        return $this->assertTrue(true);
    }
}

