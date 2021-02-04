<?php

use dokuwiki\Parsing\Parser;

require_once DOKU_INC . 'inc/parser/parser.php';
require_once DOKU_INC . 'inc/parser/handler.php';
if (!defined('DOKU_PARSER_EOL')) define('DOKU_PARSER_EOL', "\n");   // add this to make handling test cases simpler

abstract class TestOfDoku_Parser extends DokuWikiTest {

    /** @var  Parser */
    protected $P;
    /** @var  Doku_Handler */
    protected $H;

    function setUp() : void {
        parent::setUp();
        $this->H = new Doku_Handler();
        $this->P = new Parser($this->H);
    }

    function tearDown() : void {
        unset($this->P);
        unset($this->H);
    }
}

function stripByteIndex($call) {
    unset($call[2]);
    if ($call[0] == "nest") {
      $call[1][0] = array_map('stripByteIndex',$call[1][0]);
    }
    return $call;
}
