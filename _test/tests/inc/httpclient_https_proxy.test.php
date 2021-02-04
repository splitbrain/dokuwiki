<?php
require_once dirname(__FILE__).'/httpclient_http_proxy.test.php';

class httpclient_https_proxy_test extends httpclient_http_proxy_test {
    protected $url = 'https://eu.httpbin.org/user-agent';

    public function setUp() : void{
        // skip tests when this PHP has no SSL support
        $transports = stream_get_transports();
        if(!in_array('ssl',$transports)){
            $this->markTestSkipped('No SSL support available.');
        }
        parent::setUp();
    }

    /**
     * @group internet
     */
    function test_connectfail(){
        $http = new HTTPMockClient();
        // proxy provided by  Andrwe Lord Weber <dokuwiki@andrwe.org>
        $http->proxy_host = 'proxy.andrwe.org';
        $http->proxy_port = 8080;

        // the proxy accepts connections to dokuwiki.org only - the connect call should fail
        $data = $http->get('https://www.google.com');
        $this->assertFalse($data);
        $this->assertEquals(-150, $http->status);
    }
}
