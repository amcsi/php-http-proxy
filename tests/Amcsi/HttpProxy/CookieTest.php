<?php
class CookieTest extends PHPUnit_Framework_TestCase
{

    private $cookie;

    public function setUp()
    {
        $cookie = new Amcsi_HttpProxy_Cookie;
        $cookie->setSourcePath('/a/b/c/');
        $cookie->setTargetPath('/d/e/');
        $cookie->setTargetHost('target.com');
        $this->cookie = $cookie;
    }

    public function tearDown()
    {
        unset($this->cookie);
    }

    public function testGetFilteredSetCookie()
    {
        $cookie = $this->cookie;
        $val = 'key=val; expires=Sat, 07-Feb-2015 15:17:36 GMT; path=/d/e/f/g/; domain=lycee-tcg.eu; HttpOnly';
        $cookie->setCookieHeaderValue($val);
        $expected = 'key=val; expires=Sat, 07-Feb-2015 15:17:36 GMT; path=/a/b/c/f/g/; domain=target.com; HttpOnly';
        $filtered = $cookie->getFilteredSetCookie();
        $this->assertEquals($expected, $filtered);
    }

    public function testGetFilteredSetCookie2()
    {
        $cookie = $this->cookie;
        $val = 'key=val; expires=Sat, 07-Feb-2015 15:17:36 GMT; path=/d/f/g/; domain=lycee-tcg.eu; HttpOnly';
        $cookie->setCookieHeaderValue($val);
        $expected = 'key=val; expires=Sat, 07-Feb-2015 15:17:36 GMT; path=/a/b/c/; domain=target.com; HttpOnly';
        $filtered = $cookie->getFilteredSetCookie();
        $this->assertEquals($expected, $filtered);
    }
}
