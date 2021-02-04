<?php

use dokuwiki\Parsing\ParserMode\Quotes;

require_once 'parser.inc.php';

class TestOfDoku_Parser_Quotes extends TestOfDoku_Parser {

    function setUp() : void {
        parent::setUp();
        global $conf;
        $conf['typography'] = 2;
    }

    function testSingleQuoteOpening() {
        $raw = "Foo 'hello Bar";
        $this->P->addMode('quotes',new Quotes());
        $this->P->parse($raw);

        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('singlequoteopening',array()),
            array('cdata',array('hello Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );

        $this->assertEquals(array_map('stripbyteindex',$this->H->calls),$calls, 'wikitext => '.$raw);
    }

    function testSingleQuoteOpeningSpecial() {
        $raw = "Foo said:'hello Bar";
        $this->P->addMode('quotes',new Quotes());
        $this->P->parse($raw);

        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo said:')),
            array('singlequoteopening',array()),
            array('cdata',array('hello Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );

        $this->assertEquals(array_map('stripbyteindex',$this->H->calls),$calls, 'wikitext => '.$raw);
    }

    function testSingleQuoteClosing() {
        $raw = "Foo hello' Bar";
        $this->P->addMode('quotes',new Quotes());
        $this->P->parse($raw);

        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo hello')),
            array('singlequoteclosing',array()),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );

        $this->assertEquals(array_map('stripbyteindex',$this->H->calls),$calls, 'wikitext => '.$raw);
    }

    function testSingleQuoteClosingSpecial() {
        $raw = "Foo hello') Bar";
        $this->P->addMode('quotes',new Quotes());
        $this->P->parse($raw);

        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo hello')),
            array('singlequoteclosing',array()),
            array('cdata',array(') Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );

        $this->assertEquals(array_map('stripbyteindex',$this->H->calls),$calls, 'wikitext => '.$raw);
    }

    function testSingleQuotes() {
        $raw = "Foo 'hello' Bar";
        $this->P->addMode('quotes',new Quotes());
        $this->P->parse($raw);

        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('singlequoteopening',array()),
            array('cdata',array('hello')),
            array('singlequoteclosing',array()),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );

        $this->assertEquals(array_map('stripbyteindex',$this->H->calls),$calls, 'wikitext => '.$raw);
    }

    function testApostrophe() {
        $raw = "hey it's fine weather today";
        $this->P->addMode('quotes',new Quotes());
        $this->P->parse($raw);

        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'hey it')),
            array('apostrophe',array()),
            array('cdata',array('s fine weather today')),
            array('p_close',array()),
            array('document_end',array()),
        );

        $this->assertEquals(array_map('stripbyteindex',$this->H->calls),$calls, 'wikitext => '.$raw);
    }


    function testSingleQuotesSpecial() {
        $raw = "Foo ('hello') Bar";
        $this->P->addMode('quotes',new Quotes());
        $this->P->parse($raw);

        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo (')),
            array('singlequoteopening',array()),
            array('cdata',array('hello')),
            array('singlequoteclosing',array()),
            array('cdata',array(') Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );

        $this->assertEquals(array_map('stripbyteindex',$this->H->calls),$calls, 'wikitext => '.$raw);
    }

    function testDoubleQuoteOpening() {
        $raw = 'Foo "hello Bar';
        $this->P->addMode('quotes',new Quotes());
        $this->P->parse($raw);

        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('doublequoteopening',array()),
            array('cdata',array('hello Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );

        $this->assertEquals(array_map('stripbyteindex',$this->H->calls),$calls, 'wikitext => '.$raw);
    }

    function testDoubleQuoteOpeningSpecial() {
        $raw = 'Foo said:"hello Bar';
        $this->P->addMode('quotes',new Quotes());
        $this->P->parse($raw);

        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo said:')),
            array('doublequoteopening',array()),
            array('cdata',array('hello Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );

        $this->assertEquals(array_map('stripbyteindex',$this->H->calls),$calls, 'wikitext => '.$raw);
    }

    function testDoubleQuoteClosing() {
        $raw = 'Foo hello" Bar';
        $this->P->addMode('quotes',new Quotes());

        /** @noinspection PhpUnhandledExceptionInspection */
        $status = $this->getInaccessibleProperty($this->H, 'status');
        $status['doublequote'] = 1;
        /** @noinspection PhpUnhandledExceptionInspection */
        $this->setInaccessibleProperty($this->H, 'status', $status);
        $this->P->parse($raw);

        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo hello')),
            array('doublequoteclosing',array()),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );

        $this->assertEquals(array_map('stripbyteindex',$this->H->calls),$calls, 'wikitext => '.$raw);
    }

    function testDoubleQuoteClosingSpecial() {
        $raw = 'Foo hello") Bar';
        $this->P->addMode('quotes',new Quotes());
        /** @noinspection PhpUnhandledExceptionInspection */
        $status = $this->getInaccessibleProperty($this->H, 'status');
        $status['doublequote'] = 1;
        /** @noinspection PhpUnhandledExceptionInspection */
        $this->setInaccessibleProperty($this->H, 'status', $status);

        $this->P->parse($raw);

        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo hello')),
            array('doublequoteclosing',array()),
            array('cdata',array(') Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );

        $this->assertEquals(array_map('stripbyteindex',$this->H->calls),$calls, 'wikitext => '.$raw);
    }
    function testDoubleQuoteClosingSpecial2() {
        $raw = 'Foo hello") Bar';
        $this->P->addMode('quotes',new Quotes());
        /** @noinspection PhpUnhandledExceptionInspection */
        $status = $this->getInaccessibleProperty($this->H, 'status');
        $status['doublequote'] = 0;
        /** @noinspection PhpUnhandledExceptionInspection */
        $this->setInaccessibleProperty($this->H, 'status', $status);

        $this->P->parse($raw);

        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo hello')),
            array('doublequoteopening',array()),
            array('cdata',array(') Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );

        $this->assertEquals(array_map('stripbyteindex',$this->H->calls),$calls, 'wikitext => '.$raw);
    }

    function testDoubleQuotes() {
        $raw = 'Foo "hello" Bar';
        $this->P->addMode('quotes',new Quotes());
        $this->P->parse($raw);

        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('doublequoteopening',array()),
            array('cdata',array('hello')),
            array('doublequoteclosing',array()),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );

        $this->assertEquals(array_map('stripbyteindex',$this->H->calls),$calls, 'wikitext => '.$raw);
    }

    function testDoubleQuotesSpecial() {
        $raw = 'Foo ("hello") Bar';
        $this->P->addMode('quotes',new Quotes());
        $this->P->parse($raw);

        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo (')),
            array('doublequoteopening',array()),
            array('cdata',array('hello')),
            array('doublequoteclosing',array()),
            array('cdata',array(') Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );

        $this->assertEquals(array_map('stripbyteindex',$this->H->calls),$calls, 'wikitext => '.$raw);
    }

    function testDoubleQuotesEnclosingBrackets() {
        $raw = 'Foo "{hello}" Bar';
        $this->P->addMode('quotes',new Quotes());
        $this->P->parse($raw);

        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('doublequoteopening',array()),
            array('cdata',array('{hello}')),
            array('doublequoteclosing',array()),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );

        $this->assertEquals(array_map('stripbyteindex',$this->H->calls),$calls,'wikitext - '.$raw);
    }

    function testDoubleQuotesEnclosingLink() {
        $raw = 'Foo "[[www.domain.com]]" Bar';
        $this->P->addMode('quotes',new Quotes());
        $this->P->parse($raw);

        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('doublequoteopening',array()),
            array('cdata',array('[[www.domain.com]]')),
            array('doublequoteclosing',array()),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );

        $this->assertEquals(array_map('stripbyteindex',$this->H->calls),$calls,'wikitext => '.$raw);
    }


    function testAllQuotes() {
        $raw = 'There was written "He thought \'It\'s a man\'s world\'".';
        $this->P->addMode('quotes',new Quotes());
        $this->P->parse($raw);

        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'There was written ')),
            array('doublequoteopening',array()),
            array('cdata',array('He thought ')),
            array('singlequoteopening',array()),
            array('cdata',array('It')),
            array('apostrophe',array()),
            array('cdata',array('s a man')),
            array('apostrophe',array()),
            array('cdata',array('s world')),
            array('singlequoteclosing',array()),
            array('doublequoteclosing',array()),
            array('cdata',array(".")),
            array('p_close',array()),
            array('document_end',array()),
        );

        $this->assertEquals(array_map('stripbyteindex',$this->H->calls),$calls,'wikitext => '.$raw);
    }

}
