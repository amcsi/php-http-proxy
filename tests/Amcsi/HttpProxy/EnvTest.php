<?php
class EnvTest extends PHPUnit_Framework_TestCase
{
    private $env;

    public function setUp()
    {
        $server = array(
            'REQUEST_URI' => '/a/b/c/proxy.php/opts=u&scheme=http/target-url.com/d/e/z.php?foo=bar',
            'PATH_INFO' => '/opts=u&scheme=http/target-url.com/d/e/z.php',
            'QUERY_STRING' => 'foo=bar',
            'HTTP_HOST' => 'proxy-url.com',
        );
        $host = 'proxy-url.com';
        $headers = array(
            "Host: $host"
        );
        $env = new Amcsi_HttpProxy_Env('', $server, $headers);
        $this->env = $env;
    }

    public function tearDown()
    {
        unset($this->env);
    }

    public function testScriptName()
    {
        $expected = '/a/b/c/proxy.php';
        $actual = $this->env->getScriptName();
        $this->assertEquals($expected, $actual);
    }

    /**
     * testGetBaseUrl 
     * 
     * @access public
     * @return void
     * @depends testScriptName
     */
    public function testGetBaseUrl()
    {
        $expected = 'http://proxy-url.com/a/b/c/proxy.php';
        $actual = $this->env->getBaseUrl();
        $this->assertEquals($expected, $actual);
    }
}

