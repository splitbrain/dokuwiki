<?php
require_once dirname(__FILE__).'/httpclient_http.test.php';

class httpclient_https_test extends httpclient_http_test {
    protected $server = 'https://eu.httpbin.org/';

    public function setUp() : void{
        // skip tests when this PHP has no SSL support
        $transports = stream_get_transports();
        if(!in_array('ssl',$transports)){
            $this->markTestSkipped('No SSL support available.');
        }
        parent::setUp();
    }
}
//Setup VIM: ex: et ts=4 :
