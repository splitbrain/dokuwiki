<?php

/**
 * @group internet
 */
class fetch_statuscodes_external_test extends DokuWikiTest {

    private $media = 'http://www.google.com/images/srpr/logo3w.png'; //used in media_get_from_url test too
    private $width = 200;
    private $height = 0;

    function setUp() : void {

        header('X-Test: check headers working');
        $header_check = function_exists('xdebug_get_headers') ? xdebug_get_headers() : headers_list();
        if(empty($header_check)) {
            $this->markTestSkipped('headers not returned, perhaps your sapi does not return headers, try xdebug');
        } else {
            header_remove('X-Test');
        }

        parent::setUp();

        global $conf;
        $conf['fetchsize'] = 500 * 1024; //500kb
        $conf['xsendfile'] = 0;

        global $MIME, $EXT, $CACHE, $INPUT; // variables fetch creates in global scope -- should this be in fetch?
    }

    function getUri() {
        $w = $this->width ? 'w='.$this->width.'&' : '';
        $h = $this->height ? 'h='.$this->height.'&' : '';
        return '/lib/exe/fetch.php?'.$w.$h.'{%token%}media='.rawurlencode($this->media);
    }

    function fetchResponse($token) {
        $request = new TestRequest();
        return $request->get(array(), str_replace('{%token%}', $token, $this->getUri()));
    }

    /**
     *  modified image request with valid token
     *  and not-modified image request with valid token
     *
     *  expect: header with mime-type
     *  expect: content
     *  expect: no error response
     */
    function test_valid_token() {
        $valid_token_resize = 'tok='.media_get_token($this->media, $this->width, $this->height).'&';

        $this->handlevalidresponse($valid_token_resize);

        //original size
        $this->width          = $this->height = 0;
        $valid_token_original = 'tok='.media_get_token($this->media, $this->width, $this->height).'&';

        $this->handlevalidresponse($valid_token_original);

    }

    /**
     * Performs asserts for valid request
     *
     * @param $valid_token
     */
    private function handlevalidresponse($valid_token){
        $response = $this->fetchResponse($valid_token);
        $this->assertTrue((bool) $response->getHeader('Content-Type'));
        $this->assertTrue((bool) ($response->getContent()));

        $status_code = $response->getStatusCode();
        $this->assertTrue(is_null($status_code) || (200 == $status_code));
    }

    /**
     *  modified image request with invalid token
     *  expect: 412 status code
     */
    function test_invalid_token() {
        $invalid_tokens = array(
            'invalid_token_wrongid' => media_get_token('junk', 200, 100),
            'invalid_token_wrongh'  => media_get_token($this->media, 200, 10),
            'invalid_token_wrongw'  => media_get_token($this->media, 20, 100),
            'invalid_token_wrongwh' => media_get_token($this->media, 20, 10)
        );
        foreach($invalid_tokens as $invalid_token)
            $this->assertEquals(412, $this->fetchResponse('tok='.$invalid_token.'&')->getStatusCode());

    }

    /**
     *  modified image request with no token
     *  and not modified image with no token
     *  expect: 412 status code
     */
    function test_missing_token() {
        $no_token = '';

        $this->assertEquals(412, $this->fetchResponse($no_token)->getStatusCode());

        $this->width = $this->height = 0;
        $this->assertEquals(412, $this->fetchResponse($no_token)->getStatusCode());
    }
}
//Setup VIM: ex: et ts=4 :
