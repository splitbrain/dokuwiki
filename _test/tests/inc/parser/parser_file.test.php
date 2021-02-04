<?php

use dokuwiki\Parsing\ParserMode\File;

require_once 'parser.inc.php';

class TestOfDoku_Parser_File extends TestOfDoku_Parser {

    function setUp() : void {
        parent::setUp();
        $this->P->addMode('file',new File());
    }

    function testFile() {
        $this->P->parse('Foo <file>Test</file> Bar');
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('p_close',array()),
            array('file',array('Test',null,null)),
            array('p_open',array()),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripbyteindex',$this->H->calls),$calls);
    }

    function testFileHighlightDownload() {
        $this->P->parse('Foo <file txt test.txt>Test</file> Bar');
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('p_close',array()),
            array('file',array('Test','txt','test.txt')),
            array('p_open',array()),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripbyteindex',$this->H->calls),$calls);
    }

    function testFileToken() {
        $this->P->parse('Foo <file2>Test</file2> Bar');
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo <file2>Test</file2> Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripbyteindex',$this->H->calls),$calls);
    }

}
