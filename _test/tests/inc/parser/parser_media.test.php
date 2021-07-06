<?php
require_once 'parser.inc.php';

/**
 * Tests for the implementation of audio and video files
 *
 * @group parser_media
 * @author  Michael Große <grosse@cosmocode.de>
*/
class TestOfDoku_Parser_Media extends TestOfDoku_Parser {

    function testVideoOGVExternal() {
        $file = 'http://some.where.far/away.ogv';
        $parser_response = p_get_instructions('{{' . $file . '}}');

        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('externalmedia',array($file,null,null,null,null,'cache','details',array('controls'=>true))),
            array('cdata',array(null)),
            array('p_close',array()),
            array('document_end',array())
        );
        $this->assertEquals(array_map('stripbyteindex',$parser_response),$calls);

        $Renderer = new Doku_Renderer_xhtml();
        $url = $Renderer->externalmedia($file,null,null,null,null,'cache','details',array('controls'=>true),true);
        //print_r("url: " . $url);
        $video = '<video class="media" controls="controls">';
        $this->assertEquals(substr($url,0,41),$video);
        $source = '<source src="http://some.where.far/away.ogv" type="video/ogg" />';
        $this->assertEquals(substr($url,42,64),$source);
        // work around random token
        $a_first_part = '<a href="' . DOKU_BASE . 'lib/exe/fetch.php?cache=&amp;tok=';
        $a_second_part = '&amp;media=http%3A%2F%2Fsome.where.far%2Faway.ogv" class="media mediafile mf_ogv" title="http://some.where.far/away.ogv">';

        $substr_start = 107;
        $substr_len = strlen($a_first_part);
        $this->assertEquals($a_first_part, substr($url, $substr_start, $substr_len));

        $substr_start = strpos($url, '&amp;media', $substr_start + $substr_len);
        $this->assertNotSame(false, $substr_start, 'Substring not found.');
        $substr_len = strlen($a_second_part);
        $this->assertEquals($a_second_part, substr($url, $substr_start, $substr_len));

        $rest = 'away.ogv</a></video>'."\n";
        $substr_start = strlen($url) - strlen($rest);
        $this->assertEquals($rest, substr($url, $substr_start));
    }

    /**
     * unknown extension of external media file
     */
    function testVideoVIDExternal() {
        $file = 'http://some.where.far/away.vid';
        $parser_response = p_get_instructions('{{' . $file . '}}');

        $calls = array(
            array('document_start', array()),
            array('p_open', array()),
            array('externalmedia', array($file, null, null, null, null, 'cache', 'details', null)),
            array('cdata', array(null)),
            array('p_close', array()),
            array('document_end', array()),
        );
        $this->assertEquals(array_map('stripbyteindex', $parser_response), $calls);

        $Renderer = new Doku_Renderer_xhtml();
        $url = $Renderer->externalmedia($file, null, null, null, null, 'cache', 'details', null, true);
        // work around random token
        $a_first_part = '<a href="' . DOKU_BASE . 'lib/exe/fetch.php?tok=';
        $a_second_part = '&amp;media=http%3A%2F%2Fsome.where.far%2Faway.vid" class="media mediafile mf_vid" title="http://some.where.far/away.vid">';

        $substr_start = 0;
        $substr_len = strlen($a_first_part);
        $this->assertEquals($a_first_part, substr($url, $substr_start, $substr_len));

        $substr_start = strpos($url, '&amp;media', $substr_start + $substr_len);
        $this->assertNotSame(false, $substr_start, 'Substring not found.');
        $substr_len = strlen($a_second_part);
        $this->assertEquals($a_second_part, substr($url, $substr_start, $substr_len));

        $rest = 'away.vid</a>';
        $substr_start = strlen($url) - strlen($rest);
        $this->assertEquals($rest, substr($url, $substr_start));
    }


    function testVideoOGVInternal() {
        $file = 'wiki:kind_zu_katze.ogv';
        $parser_response = p_get_instructions('{{' . $file . '}}');

        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('internalmedia',array($file,null,null,null,null,'cache','details',array('controls'=>true))),
            array('cdata',array(null)),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripbyteindex',$parser_response),$calls);

        $Renderer = new Doku_Renderer_xhtml();
        $url = $Renderer->externalmedia($file,null,null,null,null,'cache','details',array('controls'=>true),true);

        $video = '<video class="media" controls="controls" poster="' . DOKU_BASE . 'lib/exe/fetch.php?media=wiki:kind_zu_katze.png">';
        $substr_start = 0;
        $substr_len = strlen($video);
        $this->assertEquals($video, substr($url, $substr_start, $substr_len));

        // find $source_webm in $url
        $source_webm = '<source src="' . DOKU_BASE . 'lib/exe/fetch.php?media=wiki:kind_zu_katze.webm" type="video/webm" />';
        $substr_start = strpos($url, $source_webm, $substr_start + $substr_len);
        $this->assertNotSame(false, $substr_start, 'Substring not found.');

        // find $source_ogv in $url
        $source_ogv = '<source src="' . DOKU_BASE . 'lib/exe/fetch.php?media=wiki:kind_zu_katze.ogv" type="video/ogg" />';
        $substr_start = strpos($url, $source_ogv, $substr_start + strlen($source_webm));
        $this->assertNotSame(false, $substr_start, 'Substring not found.');

        // find $a_webm in $url
        $a_webm = '<a href="' . DOKU_BASE . 'lib/exe/fetch.php?id=&amp;cache=&amp;media=wiki:kind_zu_katze.webm" class="media mediafile mf_webm" title="wiki:kind_zu_katze.webm (99.1'."\xC2\xA0".'KB)">kind_zu_katze.webm</a>';
        $substr_start = strpos($url, $a_webm, $substr_start + strlen($source_ogv));
        $this->assertNotSame(false, $substr_start, 'Substring not found.');

        // find $a_webm in $url
        $a_ogv = '<a href="' . DOKU_BASE . 'lib/exe/fetch.php?id=&amp;cache=&amp;media=wiki:kind_zu_katze.ogv" class="media mediafile mf_ogv" title="wiki:kind_zu_katze.ogv (44.8'."\xC2\xA0".'KB)">kind_zu_katze.ogv</a>';
        $substr_start = strpos($url, $a_ogv, $substr_start + strlen($a_webm));
        $this->assertNotSame(false, $substr_start, 'Substring not found.');

        $rest = '</video>'."\n";
        $substr_start = strlen($url) - strlen($rest);
        $this->assertEquals($rest, substr($url, $substr_start));
    }

    function testVideoInternalTitle() {
        $file = 'wiki:kind_zu_katze.ogv';
        $title = 'Single quote: \' Ampersand: &';
        
        $Renderer = new Doku_Renderer_xhtml();
        $url = $Renderer->externalmedia($file, $title, null, null, null, 'cache', 'details', array('controls'=>true), true);
        
        // make sure the title is escaped just once
        $this->assertEquals(htmlspecialchars($title), substr($url, 28, 32));
    }

    function testSimpleLinkText() {
        $file = 'wiki:dokuwiki-128.png';
        $parser_response = p_get_instructions('{{' . $file . '|This is a simple text.}}');

        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('internalmedia',array($file,'This is a simple text.',null,null,null,'cache','details',null)),
            array('cdata',array(null)),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripbyteindex',$parser_response),$calls);
    }

    function testLinkTextWithWavedBrackets_1() {
        $file = 'wiki:dokuwiki-128.png';
        $parser_response = p_get_instructions('{{' . $file . '|We got a { here.}}');

        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('internalmedia',array($file,'We got a { here.',null,null,null,'cache','details',null)),
            array('cdata',array(null)),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripbyteindex',$parser_response),$calls);
    }

    function testLinkTextWithWavedBrackets_2() {
        $file = 'wiki:dokuwiki-128.png';
        $parser_response = p_get_instructions('{{' . $file . '|We got a } here.}}');

        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('internalmedia',array($file,'We got a } here.',null,null,null,'cache','details',null)),
            array('cdata',array(null)),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripbyteindex',$parser_response),$calls);
    }

    function testLinkTextWithWavedBrackets_3() {
        $file = 'wiki:dokuwiki-128.png';
        $parser_response = p_get_instructions('{{' . $file . '|We got a { and a } here.}}');

        $calls = array (
            array('document_start',array()),
            array('p_open',array()),
            array('internalmedia',array($file,'We got a { and a } here.',null,null,null,'cache','details',null)),
            array('cdata',array(null)),
            array('p_close',array()),
            array('document_end',array()),
        );
        $this->assertEquals(array_map('stripbyteindex',$parser_response),$calls);
    }
}
