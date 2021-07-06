<?php

use dokuwiki\Parsing\ParserMode\Camelcaselink;
use dokuwiki\Parsing\ParserMode\Emaillink;
use dokuwiki\Parsing\ParserMode\Externallink;
use dokuwiki\Parsing\ParserMode\Filelink;
use dokuwiki\Parsing\ParserMode\Internallink;
use dokuwiki\Parsing\ParserMode\Media;
use dokuwiki\Parsing\ParserMode\Windowssharelink;

require_once 'parser.inc.php';

/**
 * Tests for the implementation of link syntax
 *
 * @group parser_links
*/
class TestOfDoku_Parser_Links extends TestOfDoku_Parser {

    function testExternalLinkSimple() {
        $this->P->addMode('externallink',new Externallink());
        $this->P->parse("Foo http://www.google.com Bar");
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('externallink',array('http://www.google.com', NULL)),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripByteIndex',$this->H->calls),$calls);
    }

    function testExternalLinkCase() {
        $this->P->addMode('externallink',new Externallink());
        $this->P->parse("Foo HTTP://WWW.GOOGLE.COM Bar");
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('externallink',array('HTTP://WWW.GOOGLE.COM', NULL)),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripByteIndex',$this->H->calls),$calls);
    }

    function testExternalIPv4() {
        $this->P->addMode('externallink',new Externallink());
        $this->P->parse("Foo http://123.123.3.21/foo Bar");
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('externallink',array('http://123.123.3.21/foo', NULL)),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripByteIndex',$this->H->calls),$calls);
    }

    function testExternalIPv6() {
        $this->P->addMode('externallink',new Externallink());
        $this->P->parse("Foo http://[3ffe:2a00:100:7031::1]/foo Bar");
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('externallink',array('http://[3ffe:2a00:100:7031::1]/foo', NULL)),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripByteIndex',$this->H->calls),$calls);
    }

    function testExternalMulti(){
        $this->teardown();

        $links = array(
            'http://www.google.com',
            'HTTP://WWW.GOOGLE.COM',
            'http://[FEDC:BA98:7654:3210:FEDC:BA98:7654:3210]:80/index.html',
            'http://[1080:0:0:0:8:800:200C:417A]/index.html',
            'http://[3ffe:2a00:100:7031::1]',
            'http://[1080::8:800:200C:417A]/foo',
            'http://[::192.9.5.5]/ipng',
            'http://[::FFFF:129.144.52.38]:80/index.html',
            'http://[2010:836B:4179::836B:4179]',
        );
        $titles = array(false,null,'foo bar');
        foreach($links as $link){
            foreach($titles as $title){
                if($title === false){
                    $source = $link;
                    $name   = null;
                }elseif($title === null){
                    $source = "[[$link]]";
                    $name   = null;
                }else{
                    $source = "[[$link|$title]]";
                    $name   = $title;
                }
                $this->setup();
                $this->P->addMode('internallink',new Internallink());
                $this->P->addMode('externallink',new Externallink());
                $this->P->parse("Foo $source Bar");
                $calls = array (
                    array('document_start',array()),
                    array('p_open',array()),
                    array('cdata',array("\n".'Foo ')),
                    array('externallink',array($link, $name)),
                    array('cdata',array(' Bar')),
                    array('p_close',array()),
                    array('document_end',array()),
                );
                $this->assertEquals(array_map('stripByteIndex',$this->H->calls), $calls, $source);
                $this->teardown();
            }
        }

        $this->setup();
    }

    function testExternalLinkJavascript() {
        $this->P->addMode('externallink',new Externallink());
        $this->P->parse("Foo javascript:alert('XSS'); Bar");
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\nFoo javascript:alert('XSS'); Bar")),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripByteIndex',$this->H->calls),$calls);
    }

    function testExternalWWWLink() {
        $this->P->addMode('externallink',new Externallink());
        $this->P->parse("Foo www.google.com Bar");
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('externallink',array('http://www.google.com', 'www.google.com')),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripByteIndex',$this->H->calls),$calls);
    }

    function testExternalWWWLinkStartOfLine() {
        // Regression test for issue #2399
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('externallink',array('http://www.google.com', 'www.google.com')),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $instructions = p_get_instructions("www.google.com Bar");
        $this->assertEquals(array_map('stripByteIndex',$instructions),$calls);
    }

    function testExternalWWWLinkInRoundBrackets() {
        $this->P->addMode('externallink',new ExternalLink());
        $this->P->parse("Foo (www.google.com) Bar");
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo (')),
            array('externallink',array('http://www.google.com', 'www.google.com')),
            array('cdata',array(') Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripByteIndex',$this->H->calls),$calls);
    }

    function testExternalWWWLinkInPath() {
        $this->P->addMode('externallink',new Externallink());
        // See issue #936. Should NOT generate a link!
        $this->P->parse("Foo /home/subdir/www/www.something.de/somedir/ Bar");
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo /home/subdir/www/www.something.de/somedir/ Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripByteIndex',$this->H->calls),$calls);
    }

    function testExternalWWWLinkFollowingPath() {
        $this->P->addMode('externallink',new Externallink());
        $this->P->parse("Foo /home/subdir/www/ www.something.de/somedir/ Bar");
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo /home/subdir/www/ ')),
            array('externallink',array('http://www.something.de/somedir/', 'www.something.de/somedir/')),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripByteIndex',$this->H->calls),$calls);
    }

    function testExternalFTPLink() {
        $this->P->addMode('externallink',new Externallink());
        $this->P->parse("Foo ftp.sunsite.com Bar");
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('externallink',array('ftp://ftp.sunsite.com', 'ftp.sunsite.com')),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripByteIndex',$this->H->calls),$calls);
    }

    function testExternalFTPLinkInPath() {
        $this->P->addMode('externallink',new Externallink());
        // See issue #936. Should NOT generate a link!
        $this->P->parse("Foo /home/subdir/www/ftp.something.de/somedir/ Bar");
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo /home/subdir/www/ftp.something.de/somedir/ Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripByteIndex',$this->H->calls),$calls);
    }

    function testExternalFTPLinkFollowingPath() {
        $this->P->addMode('externallink',new Externallink());
        $this->P->parse("Foo /home/subdir/www/ ftp.something.de/somedir/ Bar");
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo /home/subdir/www/ ')),
            array('externallink',array('ftp://ftp.something.de/somedir/', 'ftp.something.de/somedir/')),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripByteIndex',$this->H->calls),$calls);
    }

    function testEmail() {
        $this->P->addMode('emaillink',new Emaillink());
        $this->P->parse("Foo <bugs@php.net> Bar");
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('emaillink',array('bugs@php.net', NULL)),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripByteIndex',$this->H->calls),$calls);
    }

    function testEmailRFC2822() {
        $this->P->addMode('emaillink',new Emaillink());
        $this->P->parse("Foo <~fix+bug's.for/ev{e}r@php.net> Bar");
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('emaillink',array("~fix+bug's.for/ev{e}r@php.net", NULL)),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripByteIndex',$this->H->calls),$calls);
    }

    function testEmailCase() {
        $this->P->addMode('emaillink',new Emaillink());
        $this->P->parse("Foo <bugs@pHp.net> Bar");
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('emaillink',array('bugs@pHp.net', NULL)),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripByteIndex',$this->H->calls),$calls);
    }


    function testInternalLinkOneChar() {
        $this->P->addMode('internallink',new Internallink());
        $this->P->parse("Foo [[l]] Bar");
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('internallink',array('l',NULL)),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripByteIndex',$this->H->calls),$calls);
    }

    function testInternalLinkNoChar() {
        $this->P->addMode('internallink',new Internallink());
        $this->P->parse("Foo [[]] Bar");
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('internallink',array('',NULL)),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripByteIndex',$this->H->calls),$calls);
    }

    function testInternalLinkNamespaceNoTitle() {
        $this->P->addMode('internallink',new Internallink());
        $this->P->parse("Foo [[foo:bar]] Bar");
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('internallink',array('foo:bar',NULL)),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripByteIndex',$this->H->calls),$calls);
    }

    function testInternalLinkNamespace() {
        $this->P->addMode('internallink',new Internallink());
        $this->P->parse("Foo [[x:1:y:foo_bar:z|Test]] Bar");
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('internallink',array('x:1:y:foo_bar:z','Test')),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripByteIndex',$this->H->calls),$calls);
    }

    function testInternalLinkSectionRef() {
        $this->P->addMode('internallink',new Internallink());
        $this->P->parse("Foo [[wiki:syntax#internal|Syntax]] Bar");
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('internallink',array('wiki:syntax#internal','Syntax')),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripByteIndex',$this->H->calls),$calls);
    }

    function testInternalLinkCodeFollows() {
        $this->P->addMode('internallink',new Internallink());
        $this->P->parse("Foo [[wiki:internal:link|Test]] Bar <code>command [arg1 [arg2 [arg3]]]</code>");
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('internallink',array('wiki:internal:link','Test')),
            array('cdata',array(' Bar <code>command [arg1 [arg2 [arg3]]]</code>')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripByteIndex',$this->H->calls),$calls);
    }

    function testInternalLinkCodeFollows2() {
        $this->P->addMode('internallink',new Internallink());
        $this->P->parse("Foo [[wiki:internal:link|[Square brackets in title] Test]] Bar <code>command [arg1 [arg2 [arg3]]]</code>");
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('internallink',array('wiki:internal:link','[Square brackets in title] Test')),
            array('cdata',array(' Bar <code>command [arg1 [arg2 [arg3]]]</code>')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripByteIndex',$this->H->calls),$calls);
    }

    function testExternalInInternalLink() {
        $this->P->addMode('internallink',new Internallink());
        $this->P->parse("Foo [[http://www.google.com|Google]] Bar");
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('externallink',array('http://www.google.com','Google')),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripByteIndex',$this->H->calls),$calls);
    }

    function testExternalInInternalLink2() {
        $this->P->addMode('internallink',new Internallink());
        $this->P->parse("Foo [[http://www.google.com?test[]=squarebracketsinurl|Google]] Bar");
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('externallink',array('http://www.google.com?test[]=squarebracketsinurl','Google')),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripByteIndex',$this->H->calls),$calls);
    }

    function testExternalInInternalLink2CodeFollows() {
        $this->P->addMode('internallink',new Internallink());
        $this->P->parse("Foo [[http://www.google.com?test[]=squarebracketsinurl|Google]] Bar <code>command [arg1 [arg2 [arg3]]]</code>");
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('externallink',array('http://www.google.com?test[]=squarebracketsinurl','Google')),
            array('cdata',array(' Bar <code>command [arg1 [arg2 [arg3]]]</code>')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripByteIndex',$this->H->calls),$calls);
    }

    function testTwoInternalLinks() {
        $this->P->addMode('internallink',new Internallink());
        $this->P->parse("Foo [[foo:bar|one]] and [[bar:foo|two]] Bar");
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('internallink',array('foo:bar','one')),
            array('cdata',array(' and ')),
            array('internallink',array('bar:foo','two')),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripByteIndex',$this->H->calls),$calls);
    }


    function testInterwikiLink() {
        $this->P->addMode('internallink',new Internallink());
        $this->P->parse("Foo [[iw>somepage|Some Page]] Bar");
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('interwikilink',array('iw>somepage','Some Page','iw','somepage')),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripByteIndex',$this->H->calls),$calls);
    }

    function testInterwikiLinkCase() {
        $this->P->addMode('internallink',new Internallink());
        $this->P->parse("Foo [[IW>somepage|Some Page]] Bar");
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('interwikilink',array('IW>somepage','Some Page','iw','somepage')),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripByteIndex',$this->H->calls),$calls);
    }

    function testInterwikiPedia() {
        $this->P->addMode('internallink',new Internallink());
        $this->P->parse("Foo [[wp>Callback_(computer_science)|callbacks]] Bar");
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('interwikilink',array('wp>Callback_(computer_science)','callbacks','wp','Callback_(computer_science)')),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripByteIndex',$this->H->calls),$calls);
    }

    function testCamelCase() {
        $this->P->addMode('camelcaselink',new Camelcaselink());
        $this->P->parse("Foo FooBar Bar");
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('camelcaselink',array('FooBar')),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripByteIndex',$this->H->calls),$calls);
    }

    function testFileLink() {
        $this->P->addMode('filelink',new FileLink());
        $this->P->parse('Foo file://temp/file.txt Bar');
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('filelink',array('file://temp/file.txt ',NULL)),
            array('cdata',array('Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripByteIndex',$this->H->calls),$calls);
    }

    function testFileLinkInternal() {
        $this->P->addMode('internallink',new Internallink());
        $this->P->parse('Foo [[file://temp/file.txt|Some File]] Bar');
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('externallink',array('file://temp/file.txt','Some File')),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripByteIndex',$this->H->calls),$calls);
    }

    function testWindowsShareLink() {
        $this->P->addMode('windowssharelink',new Windowssharelink());
        $this->P->parse('Foo \\\server\share Bar');
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('windowssharelink',array('\\\server\share',NULL)),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripByteIndex',$this->H->calls),$calls);
    }

    function testWindowsShareLinkHyphen() {
        $this->P->addMode('windowssharelink',new Windowssharelink());
        $this->P->parse('Foo \\\server\share-hyphen Bar');
        $calls = array (
        array('document_start',array()),
        array('p_open',array()),
        array('cdata',array("\n".'Foo ')),
        array('windowssharelink',array('\\\server\share-hyphen',NULL)),
        array('cdata',array(' Bar')),
        array('p_close',array()),
        array('document_end',array()),
        );
        $this->assertEquals(array_map('stripByteIndex',$this->H->calls),$calls);
    }

    function testWindowsShareLinkInternal() {
        $this->P->addMode('internallink',new Internallink());
        $this->P->parse('Foo [[\\\server\share|My Documents]] Bar');
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('windowssharelink',array('\\\server\share','My Documents')),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripByteIndex',$this->H->calls),$calls);
    }

    function testMediaInternal() {
        $this->P->addMode('media',new Media());
        $this->P->parse('Foo {{img.gif}} Bar');
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('internalmedia',array('img.gif',NULL,NULL,NULL,NULL,'cache','details',NULL)),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripByteIndex',$this->H->calls),$calls);
    }

    function testMediaInternalLinkOnly() {
        $this->P->addMode('media',new Media());
        $this->P->parse('Foo {{img.gif?linkonly}} Bar');
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('internalmedia',array('img.gif',NULL,NULL,NULL,NULL,'cache','linkonly',NULL)),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripByteIndex',$this->H->calls),$calls);
    }

    function testMediaNotImage() {
        $this->P->addMode('media',new Media());
        $this->P->parse('Foo {{foo.txt?10x10|Some File}} Bar');
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('internalmedia',array('foo.txt','Some File',null,10,10,'cache','details',NULL)),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripByteIndex',$this->H->calls),$calls);
    }

    function testMediaInternalLAlign() {
        $this->P->addMode('media',new Media());
        $this->P->parse('Foo {{img.gif }} Bar');
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('internalmedia',array('img.gif',NULL,'left',NULL,NULL,'cache','details',NULL)),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripByteIndex',$this->H->calls),$calls);
    }

    function testMediaInternalRAlign() {
        $this->P->addMode('media',new Media());
        $this->P->parse('Foo {{ img.gif}} Bar');
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('internalmedia',array('img.gif',NULL,'right',NULL,NULL,'cache','details',NULL)),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripByteIndex',$this->H->calls),$calls);
    }

    function testMediaInternalCenter() {
        $this->P->addMode('media',new Media());
        $this->P->parse('Foo {{ img.gif }} Bar');
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('internalmedia',array('img.gif',NULL,'center',NULL,NULL,'cache','details',NULL)),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripByteIndex',$this->H->calls),$calls);
    }

    function testMediaInternalParams() {
        $this->P->addMode('media',new Media());
        $this->P->parse('Foo {{img.gif?50x100nocache}} Bar');
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('internalmedia',array('img.gif',NULL,NULL,'50','100','nocache','details',NULL)),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripByteIndex',$this->H->calls),$calls);
    }

    function testMediaInternalTitle() {
        $this->P->addMode('media',new Media());
        $this->P->parse('Foo {{img.gif?50x100|Some Image}} Bar');
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('internalmedia',array('img.gif','Some Image',NULL,'50','100','cache','details',NULL)),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripByteIndex',$this->H->calls),$calls);
    }

    function testMediaExternal() {
        $this->P->addMode('media',new Media());
        $this->P->parse('Foo {{http://www.google.com/img.gif}} Bar');
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('externalmedia',array('http://www.google.com/img.gif',NULL,NULL,NULL,NULL,'cache','details',NULL)),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripByteIndex',$this->H->calls),$calls);
    }

    function testMediaExternalParams() {
        $this->P->addMode('media',new Media());
        $this->P->parse('Foo {{http://www.google.com/img.gif?50x100nocache}} Bar');
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('externalmedia',array('http://www.google.com/img.gif',NULL,NULL,'50','100','nocache','details',NULL)),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripByteIndex',$this->H->calls),$calls);
    }

    function testMediaExternalTitle() {
        $this->P->addMode('media',new Media());
        $this->P->parse('Foo {{http://www.google.com/img.gif?50x100|Some Image}} Bar');
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('externalmedia',
            array('http://www.google.com/img.gif','Some Image',NULL,'50','100','cache','details',NULL)),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripByteIndex',$this->H->calls),$calls);
    }

    function testMediaInInternalLink() {
        $this->P->addMode('internallink',new Internallink());
        $this->P->parse("Foo [[x:1:y:foo_bar:z|{{img.gif?10x20nocache|Some Image}}]] Bar");

        $image = array(
            'type'=>'internalmedia',
            'src'=>'img.gif',
            'title'=>'Some Image',
            'align'=>NULL,
            'width'=>10,
            'height'=>20,
            'cache'=>'nocache',
            'linking'=>'details'
        );

        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('internallink',array('x:1:y:foo_bar:z',$image)),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripByteIndex',$this->H->calls),$calls);
    }

    function testMediaNoImageInInternalLink() {
        $this->P->addMode('internallink',new Internallink());
        $this->P->parse("Foo [[x:1:y:foo_bar:z|{{foo.txt?10x20nocache|Some Image}}]] Bar");

        $image = array(
            'type'=>'internalmedia',
            'src'=>'foo.txt',
            'title'=>'Some Image',
            'align'=>NULL,
            'width'=>10,
            'height'=>20,
            'cache'=>'nocache',
            'linking'=>'details'
        );

        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('internallink',array('x:1:y:foo_bar:z',$image)),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripByteIndex',$this->H->calls),$calls);
    }

    function testMediaInEmailLink() {
        $this->P->addMode('internallink',new Internallink());
        $this->P->parse("Foo [[foo@example.com|{{img.gif?10x20nocache|Some Image}}]] Bar");

        $image = array(
            'type'=>'internalmedia',
            'src'=>'img.gif',
            'title'=>'Some Image',
            'align'=>NULL,
            'width'=>10,
            'height'=>20,
            'cache'=>'nocache',
            'linking'=>'details'
        );

        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('emaillink',array('foo@example.com',$image)),
            array('cdata',array(' Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripByteIndex',$this->H->calls),$calls);
    }

    function testNestedMedia() {
        $this->P->addMode('media',new Media());
        $this->P->parse('Foo {{img.gif|{{foo.gif|{{bar.gif|Bar}}}}}} Bar');
        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('cdata',array("\n".'Foo ')),
            array('internalmedia',
            array('img.gif','{{foo.gif|{{bar.gif|Bar',NULL,NULL,NULL,'cache','details',NULL)),
            array('cdata',array('}}}} Bar')),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripByteIndex',$this->H->calls),$calls);
    }

}

