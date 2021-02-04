<?php

class fetch_imagetoken_test extends DokuWikiTest {

    private $media = 'wiki:dokuwiki-128.png';
    private $width = 200;
    private $height = 0;

    function setUp() : void {
        // check we can carry out these tests
        if (!file_exists(mediaFN($this->media))) {
            $this->markTestSkipped('Source image required for test');
        }

        header('X-Test: check headers working');
        $header_check = function_exists('xdebug_get_headers') ? xdebug_get_headers() : headers_list();
        if (empty($header_check)) {
            $this->markTestSkipped('headers not returned, perhaps your sapi does not return headers, try xdebug');
        } else {
            header_remove('X-Test');
        }

        parent::setUp();

        global $conf;
        $conf['xsendfile'] = 0;

        global $MIME, $EXT, $CACHE, $INPUT;    // variables fetch creates in global scope -- should this be in fetch?
    }

    function getUri() {
       $w = $this->width ? 'w='.$this->width.'&' : '';
       $h = $this->height ? 'h='.$this->height.'&' : '';

       return '/lib/exe/fetch.php?'.$w.$h.'{%token%}media='.$this->media;
    }

    function fetchResponse($token){
        $request = new TestRequest();
        return $request->get(array(),str_replace('{%token%}',$token,$this->getUri()));
    }

    /**
     *  modified image request with valid token
     *  expect: header with mime-type
     *  expect: content
     *  expect: no error response
     */
    function test_valid_token(){
        $valid_token = 'tok='.media_get_token($this->media, $this->width, $this->height).'&';
        $response = $this->fetchResponse($valid_token);
        $this->assertTrue((bool)$response->getHeader('Content-Type'));
        $this->assertTrue((bool)($response->getContent()));

        $status_code = $response->getStatusCode();
        $this->assertTrue(is_null($status_code) || (200 == $status_code));
    }

    /**
     *  modified image request with invalid token
     *  expect: 412 status code
     */
    function test_invalid_token(){
        $invalid_token = 'tok='.media_get_token('junk',200,100).'&';
        $this->assertEquals(412,$this->fetchResponse($invalid_token)->getStatusCode());
    }

    /**
     *  modified image request with no token
     *  expect: 412 status code
     */
    function test_missing_token(){
        $no_token = '';
        $this->assertEquals(412,$this->fetchResponse($no_token)->getStatusCode());
    }

    /**
     *  native image request which doesn't require a token
     *  try: with a token & without a token
     *  expect: (for both) header with mime-type, content matching source image filesize & no error response
     */
    function test_no_token_required(){
        $this->width = $this->height = 0;   // no width & height, means image request at native dimensions
        $any_token = 'tok='.media_get_token('junk',200,100).'&';
        $no_token = '';
        $bytes = filesize(mediaFN($this->media));

        foreach(array($any_token, $no_token) as $token) {
            $response = $this->fetchResponse($token);
            $this->assertTrue((bool)$response->getHeader('Content-Type'));
            $this->assertEquals(strlen($response->getContent()), $bytes);

            $status_code = $response->getStatusCode();
            $this->assertTrue(is_null($status_code) || (200 == $status_code));
        }
    }

}
//Setup VIM: ex: et ts=4 :
