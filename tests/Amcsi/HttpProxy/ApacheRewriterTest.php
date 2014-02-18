<?php
/**
 * RewriterTest, but with Apache style RewriteRules in place
 * 
 * @uses PHPUnit
 * @uses _Framework_TestCase
 * @package 
 * @version 
 * @copyright 
 * @license 
 */
class RewriterTest extends PHPUnit_Framework_TestCase
{

    private $env;
    private $rewriter;

    public function setUp()
    {
        $server = array(
            'REDIRECT_URL' => '/a/b/c/z.php',
            'REQUEST_URI' => '/a/b/c/z.php?foo=bar',
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
        $this->rewriter = $rewriter;
    }

    public function tearDown()
    {
        unset($this->env);
    }

    public function testGetUrlString()
    {
        $expected = 'http://target-url.com/d/e/z.php?foo=bar';
        $this->assertEquals($expected, (string) $this->env->getUrlObj());
    }

    /**
     * @dataProvider provideUrlPairs
     */
    public function testReplaceUrl($toReplace, $expected)
    {
        $actual = $this->rewriter->replaceUrl($toReplace);
        $this->assertEquals($expected, $actual);
    }

    /**
     * @dataProvider provideUrlPairs
     * TBC get this test to pass
     */
    public function testReverseReplaceUrl($expected, $toReverse)
    {
        $actual = $this->rewriter->reverseReplaceUrl($toReverse);
        $this->assertEquals($expected, $actual);
    }

    public function provideUrlPairs()
    {
        $ret = array(
            array (
                'http://target-url.com/d/e/images/someimage.png?foo=bar',
                'http://proxy-url.com/a/b/c/images/someimage.png?foo=bar',
            ),
            /*
            array (
                // https
                'https://target-url.com/d/e/images/someimage.png?foo=bar',
                'http://proxy-url.com/a/b/c/images/someimage.png?foo=bar',
            ),
             */
            array (
                // different host
                'http://different-url.com/d/e/images/someimage.png?foo=bar',
                'http://different-url.com/d/e/images/someimage.png?foo=bar',
            ),
        );
        return $ret;
    }
}
