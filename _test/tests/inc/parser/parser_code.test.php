<?php

use dokuwiki\Parsing\ParserMode\Code;

require_once 'parser.inc.php';

/**
 * Tests to ensure functionality of the <code> syntax tag.
 *
 * @group parser_code
 */
class TestOfDoku_Parser_Code extends TestOfDoku_Parser {

    function setUp() : void {
        parent::setUp();
        $this->P->addMode('code',new Code());
    }

    function testCode() {
        $this->P->parse('Foo <code>Test</code> Bar');
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('p_close',array()),
            array('code',array('Test',null,null)),
            array('p_open',array()),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripbyteindex',$this->H->calls),$calls);
    }

    function testCodeBash() {
        $this->P->parse('Foo <code bash>Test</code> Bar');
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('p_close',array()),
            array('code',array('Test','bash',null)),
            array('p_open',array()),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripbyteindex',$this->H->calls),$calls);
    }

    function testCodeDownload() {
        $this->P->parse('Foo <code bash script.sh>Test</code> Bar');
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('p_close',array()),
            array('code',array('Test','bash','script.sh')),
            array('p_open',array()),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripbyteindex',$this->H->calls),$calls);
    }

    function testCodeToken() {
        $this->P->parse('Foo <code2>Bar</code2><code>Test</code>');
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo <code2>Bar</code2>')),
            array('p_close',array()),
            array('code',array('Test',null,null)),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripbyteindex',$this->H->calls),$calls);
    }

    function testCodeOptionsArray_OneOption() {
        $this->P->parse('Foo <code C [enable_line_numbers]>Test</code> Bar');
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('p_close',array()),
            array('code',array('Test','C', null,
                               array('enable_line_numbers' => 1)
                               )),
            array('p_open',array()),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripbyteindex',$this->H->calls),$calls);
    }

    function testCodeOptionsArray_TwoOptions() {
        $this->P->parse('Foo <code C [enable_line_numbers highlight_lines_extra="3"]>Test</code> Bar');
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('p_close',array()),
            array('code',array('Test','C', null,
                               array('enable_line_numbers' => true,
                                     'highlight_lines_extra' => array(3)
                               ))),
            array('p_open',array()),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripbyteindex',$this->H->calls),$calls);
    }

    function testCodeOptionsArray_UnknownOption() {
        $this->P->parse('Foo <code C [unknown="I will be deleted/ignored!"]>Test</code> Bar');
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('p_close',array()),
            array('code',array('Test','C', null, null)),
            array('p_open',array()),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripbyteindex',$this->H->calls),$calls);
    }

    function testCodeOptionsArray_EnableLineNumbers1() {
        $this->P->parse('Foo <code C [enable_line_numbers]>Test</code> Bar');
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('p_close',array()),
            array('code',array('Test','C', null,
                               array('enable_line_numbers' => true)
                               )),
            array('p_open',array()),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripbyteindex',$this->H->calls),$calls);
    }

    function testCodeOptionsArray_EnableLineNumbers2() {
        $this->P->parse('Foo <code C [enable_line_numbers="1"]>Test</code> Bar');
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('p_close',array()),
            array('code',array('Test','C', null,
                               array('enable_line_numbers' => true)
                               )),
            array('p_open',array()),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripbyteindex',$this->H->calls),$calls);
    }

    function testCodeOptionsArray_EnableLineNumbers3() {
        $this->P->parse('Foo <code C [enable_line_numbers="0"]>Test</code> Bar');
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('p_close',array()),
            array('code',array('Test','C', null,
                               array('enable_line_numbers' => false)
                               )),
            array('p_open',array()),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripbyteindex',$this->H->calls),$calls);
    }

    function testCodeOptionsArray_EnableLineNumbers4() {
        $this->P->parse('Foo <code C [enable_line_numbers=""]>Test</code> Bar');
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('p_close',array()),
            array('code',array('Test','C', null,
                               array('enable_line_numbers' => true)
                               )),
            array('p_open',array()),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripbyteindex',$this->H->calls),$calls);
    }

    function testCodeOptionsArray_HighlightLinesExtra1() {
        $this->P->parse('Foo <code C [enable_line_numbers highlight_lines_extra="42, 123, 456, 789"]>Test</code> Bar');
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('p_close',array()),
            array('code',array('Test','C', null,
                               array('enable_line_numbers' => true,
                                     'highlight_lines_extra' => array(42, 123, 456, 789)
                               ))),
            array('p_open',array()),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripbyteindex',$this->H->calls),$calls);
    }

    function testCodeOptionsArray_HighlightLinesExtra2() {
        $this->P->parse('Foo <code C [enable_line_numbers highlight_lines_extra]>Test</code> Bar');
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('p_close',array()),
            array('code',array('Test','C', null,
                               array('enable_line_numbers' => true,
                                     'highlight_lines_extra' => array(1))
                               )),
            array('p_open',array()),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripbyteindex',$this->H->calls),$calls);
    }

    function testCodeOptionsArray_HighlightLinesExtra3() {
        $this->P->parse('Foo <code C [enable_line_numbers highlight_lines_extra=""]>Test</code> Bar');
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('p_close',array()),
            array('code',array('Test','C', null,
                               array('enable_line_numbers' => true,
                                     'highlight_lines_extra' => array(1))
                               )),
            array('p_open',array()),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripbyteindex',$this->H->calls),$calls);
    }

    function testCodeOptionsArray_StartLineNumbersAt1() {
        $this->P->parse('Foo <code C [enable_line_numbers [enable_line_numbers start_line_numbers_at="42"]]>Test</code> Bar');
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('p_close',array()),
            array('code',array('Test','C', null,
                               array('enable_line_numbers' => true,
                                     'start_line_numbers_at' => 42)
                               )),
            array('p_open',array()),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripbyteindex',$this->H->calls),$calls);
    }

    function testCodeOptionsArray_StartLineNumbersAt2() {
        $this->P->parse('Foo <code C [enable_line_numbers [enable_line_numbers start_line_numbers_at]]>Test</code> Bar');
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('p_close',array()),
            array('code',array('Test','C', null,
                               array('enable_line_numbers' => true,
                                     'start_line_numbers_at' => 1)
                               )),
            array('p_open',array()),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripbyteindex',$this->H->calls),$calls);
    }

    function testCodeOptionsArray_StartLineNumbersAt3() {
        $this->P->parse('Foo <code C [enable_line_numbers [enable_line_numbers start_line_numbers_at=""]]>Test</code> Bar');
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('p_close',array()),
            array('code',array('Test','C', null,
                               array('enable_line_numbers' => true,
                                     'start_line_numbers_at' => 1)
                               )),
            array('p_open',array()),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripbyteindex',$this->H->calls),$calls);
    }

    function testCodeOptionsArray_EnableKeywordLinks1() {
        $this->P->parse('Foo <code C [enable_keyword_links="false"]>Test</code> Bar');
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('p_close',array()),
            array('code',array('Test','C', null,
                               array('enable_keyword_links' => false)
                               )),
            array('p_open',array()),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripbyteindex',$this->H->calls),$calls);
    }

    function testCodeOptionsArray_EnableKeywordLinks2() {
        $this->P->parse('Foo <code C [enable_keyword_links="true"]>Test</code> Bar');
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('p_close',array()),
            array('code',array('Test','C', null,
                               array('enable_keyword_links' => true)
                               )),
            array('p_open',array()),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripbyteindex',$this->H->calls),$calls);
    }

}
