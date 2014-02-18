<?php
/**
 * Tests that the cookie rewriter is generated correctly by
 * Amcsi_HttpProxy_Rewriter
 * by the 
 * 
 * @uses PHPUnit
 * @uses _Framework_TestCase
 * @package 
 * @version 
 * @copyright 
 * @author Attila Szeremi <attila.szeremi@netefficiency.co.uk> 
 * @license 
 */
class CookieRewriterByRewriterTest extends PHPUnit_Framework_TestCase
{

    private $cookieRewriter;

    public function setUp()
    {
        // non-apache style rewriter

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
        $rewriter = new Amcsi_HttpProxy_Rewriter($env);
        $this->cookieRewriter = $rewriter->getCookieRewriter();
    }

    public function setUpApacheStyle()
    {
        $server = array(
            'REDIRECT_URL' => '/a/b/c/y/z.php',
            'REQUEST_URI' => '/a/b/c/y/z.php?foo=bar',
            'PATH_INFO' => '/opts=u&scheme=http/target-url.com/d/e/y/z.php',
            'QUERY_STRING' => 'foo=bar',
            'HTTP_HOST' => 'proxy-url.com',
        );
        $host = 'proxy-url.com';
        $headers = array(
            "Host: $host"
        );
        $env = new Amcsi_HttpProxy_Env('', $server, $headers);
        $this->env = $env;
        $rewriter = new Amcsi_HttpProxy_Rewriter($env);
        $this->cookieRewriter = $rewriter->getCookieRewriter();
    }

    public function tearDown()
    {
        unset($this->cookieRewriter);
    }

    /**
     * 
     * @access public
     * @return void
     * @dataProvider cookieValueProvider
     */
    public function testGetFilteredSetCookie($val, $expected)
    {
        $cookieRewriter = $this->cookieRewriter;
        $filtered = $cookieRewriter->getFilteredSetCookie($val);
        $this->assertEquals($expected, $filtered);
    }

    /**
     * 
     * @access public
     * @return void
     * @dataProvider apacheCookieValueProvider
     */
    public function testGetFilteredSetCookieApacheRewriteStyle($val, $expected)
    {
        $this->tearDown();
        $this->setUpApacheStyle();

        $cookieRewriter = $this->cookieRewriter;
        $filtered = $cookieRewriter->getFilteredSetCookie($val);
        $this->assertEquals($expected, $filtered);
    }

    public function cookieValueProvider()
    {
        return array(
            array(
                'key=val; expires=Sat, 07-Feb-2015 15:17:36 GMT; path=/d/e/f/g/; domain=target-url.com; HttpOnly',
                'key=val; expires=Sat, 07-Feb-2015 15:17:36 GMT; path=/a/b/c/proxy.php/opts=u&scheme=http/target-url.com/d/e/f/g/; domain=proxy-url.com; HttpOnly',
            ),
            array(
                'key=val; expires=Sat, 07-Feb-2015 15:17:36 GMT; path=/d/f/g/; domain=lycee-tcg.eu; HttpOnly',
                'key=val; expires=Sat, 07-Feb-2015 15:17:36 GMT; path=/a/b/c/proxy.php/opts=u&scheme=http/target-url.com/d/f/g/; domain=proxy-url.com; HttpOnly',
            ),
            array(
                'comment_author_e2576ea9cee338224a1bc4868fb5da15=aaa; expires=Fri, 23-Jan-2015 18:02:20 GMT; path=/; domain=.subdomain.example.com',
                'comment_author_e2576ea9cee338224a1bc4868fb5da15=aaa; expires=Fri, 23-Jan-2015 18:02:20 GMT; path=/a/b/c/proxy.php/opts=u&scheme=http/target-url.com/; domain=proxy-url.com',
            ),
        );
    }

    public function apacheCookieValueProvider()
    {
        return array(
            array(
                'key=val; expires=Sat, 07-Feb-2015 15:17:36 GMT; path=/d/e/f/g/; domain=target-url.com; HttpOnly',
                'key=val; expires=Sat, 07-Feb-2015 15:17:36 GMT; path=/a/b/c/f/g/; domain=proxy-url.com; HttpOnly',
            ),
            array(
                'key=val; expires=Sat, 07-Feb-2015 15:17:36 GMT; path=/d/f/g/; domain=lycee-tcg.eu; HttpOnly',
                // this path doesn't adhere to the target base path, so let's just assume our base path
                // ... or should this be changed to not set a cookie at all? I mean this cookie setting shouldn't
                // be working on the real website, would it?
                'key=val; expires=Sat, 07-Feb-2015 15:17:36 GMT; path=/a/b/c/; domain=proxy-url.com; HttpOnly',
            ),
            array(
                'comment_author_e2576ea9cee338224a1bc4868fb5da15=aaa; expires=Fri, 23-Jan-2015 18:02:20 GMT; path=/; domain=.subdomain.example.com',
                'comment_author_e2576ea9cee338224a1bc4868fb5da15=aaa; expires=Fri, 23-Jan-2015 18:02:20 GMT; path=/a/b/c/; domain=proxy-url.com',
            ),
        );
    }
}
