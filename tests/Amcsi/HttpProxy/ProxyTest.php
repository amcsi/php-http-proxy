<?php
class ProxyTest extends PHPUnit_Framework_TestCase
{

    private $proxy;

    public function setUp()
    {
        $server = array(
            'REQUEST_URI' => '/a/b/c/proxy.php/opts=u&scheme=http/target-url.com/d/e/z.php?foo=bar',
            'PATH_INFO' => '/opts=u&scheme=http/target-url.com/d/e/z.php',
            'QUERY_STRING' => 'foo=bar',
            'HTTP_HOST' => 'proxy-url.com',
            'SCRIPT_NAME' => '/a/b/c/proxy.php',
        );
        $host = 'proxy-url.com';
        $headers = array(
            "Host: $host"
        );
        $env = new Amcsi_HttpProxy_Env('', $server, $headers);
        $this->proxy = new Amcsi_HttpProxy_Proxy($env);
    }

    public function tearDown()
    {
        unset($this->proxy);
    }

    public function testResponseNoFilter()
    {
        $proxy = $this->proxy;

        $request = new Amcsi_HttpProxy_Request;
        $response = new Amcsi_HttpProxy_Response(
            'content',
            array('HTTP/1.1 200 OK')
        );

        $proxyResponse = $proxy->response($request, $response);

        $this->assertSame('content', $proxyResponse->getContent());
    }

    public function testResponseFilter()
    {
        $proxy = $this->proxy;

        $content = 'content';

        $request = new Amcsi_HttpProxy_Request;
        $response = new Amcsi_HttpProxy_Response(
            $content,
            array('HTTP/1.1 200 OK')
        );

        $conf = array(
            'contentFilters' => array(
                function (Amcsi_HttpProxy_ContentFilterData $cfd) {
                    // should result in no change
                    return null;
                },
                function (Amcsi_HttpProxy_ContentFilterData $cfd) {
                    // same content
                    return $cfd->getResponseContent();
                },
                function (Amcsi_HttpProxy_ContentFilterData $cfd) {
                    $content = $cfd->getResponseContent();
                    $content = str_replace('onten', '-LOL-', $content);
                    return $content;
                },
            )
        );
        $proxy->setConf($conf);

        $proxyResponse = $proxy->response($request, $response);

        $this->assertSame('c-LOL-t', $proxyResponse->getContent());
    }

    /**
     * testIsUrlAllowed 
     * 
     * @dataProvider urlProvider
     * @return void
     */
    public function testIsUrlAllowed($baseUrl, $url, $expected)
    {
        $proxy = $this->proxy;
        $proxy->setConf(array(
            'allowedBaseUrls' => $baseUrl
        ));
        $method = new ReflectionMethod($proxy, 'isUrlAllowed');
        $method->setAccessible(true);
        $result = $method->invoke($proxy, $url);

        $this->assertSame($expected, $result);
    }

    public function urlProvider()
    {
        return array(
            array (
                'http://some-host',
                'https://some-host/bla',
                false,
            ),
            array (
                'http://some-host/bla',
                'http://some-host/bla/yo',
                true,
            ),
            array (
                'http://some-host/bla',
                'http://some-host/bla/yo/more',
                true,
            ),
            array (
                'http://some-host/bla',
                'http://some-host/foo',
                false,
            ),
            array (
                'http://some-host/bla',
                array('http://some-host/foo', 'http://some-host/bla/yo'),
                false,
            ),
            array (
                'http://some-host/bla',
                'http://some-host/blabla',
                false,
            ),
            array (
                'http://some-host/bla',
                'http://some-other-host/bla',
                false,
            ),
            array (
                'http://some-host/bla',
                'https://some-host/bla',
                false,
            ),
        );
    }
}

