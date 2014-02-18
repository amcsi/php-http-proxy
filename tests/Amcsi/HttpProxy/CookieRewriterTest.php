<?php
class CookieRewriterTest extends PHPUnit_Framework_TestCase
{

    private $cookieRewriter;

    public function setUp()
    {
        $cookieRewriter = new Amcsi_HttpProxy_CookieRewriter;
        $cookieRewriter->setSourcePath('/a/b/c/');
        $cookieRewriter->setTargetPath('/d/e/');
        $cookieRewriter->setTargetHost('target.com');
        $this->cookieRewriter = $cookieRewriter;
    }

    public function tearDown()
    {
        unset($this->cookieRewriter);
    }

    public function testGetFilteredSetCookie()
    {
        $cookieRewriter = $this->cookieRewriter;
        $val = 'key=val; expires=Sat, 07-Feb-2015 15:17:36 GMT; path=/d/e/f/g/; domain=lycee-tcg.eu; HttpOnly';
        $expected = 'key=val; expires=Sat, 07-Feb-2015 15:17:36 GMT; path=/a/b/c/f/g/; domain=target.com; HttpOnly';
        $filtered = $cookieRewriter->getFilteredSetCookie($val);
        $this->assertEquals($expected, $filtered);
    }

    public function testGetFilteredSetCookie2()
    {
        $cookieRewriter = $this->cookieRewriter;
        $val = 'key=val; expires=Sat, 07-Feb-2015 15:17:36 GMT; path=/d/f/g/; domain=lycee-tcg.eu; HttpOnly';
        $expected = 'key=val; expires=Sat, 07-Feb-2015 15:17:36 GMT; path=/a/b/c/; domain=target.com; HttpOnly';
        $filtered = $cookieRewriter->getFilteredSetCookie($val);
        $this->assertEquals($expected, $filtered);
    }

    public function testGetFilteredSetCookie3()
    {
        $cookieRewriter = $this->cookieRewriter;
        $val = 'comment_author_e2576ea9cee338224a1bc4868fb5da15=aaa; expires=Fri, 23-Jan-2015 18:02:20 GMT; path=/; domain=.subdomain.example.com';
        $expected = 'comment_author_e2576ea9cee338224a1bc4868fb5da15=aaa; expires=Fri, 23-Jan-2015 18:02:20 GMT; path=/a/b/c/; domain=target.com';
        $filtered = $cookieRewriter->getFilteredSetCookie($val);
        $this->assertEquals($expected, $filtered);
    }

    public function testGetFilteredSetCookieUnknownTarget()
    {
        $cookieRewriter = $this->cookieRewriter;
        $cookieRewriter->setTargetPath(null);
        $val = 'key=val; expires=Sat, 07-Feb-2015 15:17:36 GMT; path=/d/e/f/g/; domain=lycee-tcg.eu; HttpOnly';
        $expected = 'key=val; expires=Sat, 07-Feb-2015 15:17:36 GMT; path=/a/b/c/; domain=target.com; HttpOnly';
        $filtered = $cookieRewriter->getFilteredSetCookie($val);
        $this->assertEquals($expected, $filtered);
    }
}
