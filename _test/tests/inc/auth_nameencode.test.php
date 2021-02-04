<?php

class auth_nameencode_test extends DokuWikiTest {

    function tearDown() : void {
        global $cache_authname;
        $cache_authname = array();
    }

    function test_simple(){
        $in  = 'hey$you';
        $out = 'hey%24you';
        $this->assertEquals(auth_nameencode($in),$out);
    }

    function test_quote(){
        $in  = 'hey"you';
        $out = 'hey%22you';
        $this->assertEquals(auth_nameencode($in),$out);
    }

    function test_apostrophe(){
        $in  = 'hey\'you';
        $out = 'hey%27you';
        $this->assertEquals(auth_nameencode($in),$out);
    }

    function test_backslash(){
        $in  = 'hey\\you';
        $out = 'hey%5cyou';
        $this->assertEquals(auth_nameencode($in),$out);
    }

    function test_complex(){
        $in  = 'hey $ you !$%! foo ';
        $out = 'hey%20%24%20you%20%21%24%25%21%20foo%20';
        $this->assertEquals(auth_nameencode($in),$out);
    }

    function test_complexutf8(){
        $in  = 'häü $ yü !$%! foo ';
        $out = 'häü%20%24%20yü%20%21%24%25%21%20foo%20';
        $this->assertEquals(auth_nameencode($in),$out);
    }

    function test_groupskipon(){
        $in  = '@hey$you';
        $out = '@hey%24you';
        $this->assertEquals(auth_nameencode($in,true),$out);
    }

    function test_groupskipoff(){
        $in  = '@hey$you';
        $out = '%40hey%24you';
        $this->assertEquals(auth_nameencode($in),$out);
    }

    // include a two byte utf8 character which shouldn't be encoded
    function test_hebrew(){
        $in = 'nun-נ8';
        $expect = 'nun%2dנ8';

        $this->assertEquals($expect, auth_nameencode($in));
    }

    // include a three byte utf8 character which shouldn't be encoded
    function test_devanagiri(){
        $in = 'ut-fठ8';
        $expect = 'ut%2dfठ8';

        $this->assertEquals($expect, auth_nameencode($in));
    }
}

//Setup VIM: ex: et ts=4 :
