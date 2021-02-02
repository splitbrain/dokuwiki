<?php

class common_pagetemplate_test extends DokuWikiTest {

    function test_none(){
        global $conf;
        $conf['sepchar'] = '-';
        $data = array(
            'id' => 'page-id-long',
            'tpl' => '"@PAGE@" "@!PAGE@" "@!!PAGE@" "@!PAGE!@"',
        );
        $this->assertEquals(parsePageTemplate($data), '"page id long" "Page id long" "Page Id Long" "PAGE ID LONG"');
    }

    function test_start_root(){
        global $conf;
        $conf['startpage'] = 'start';
        $data = [
            'id' => 'start',
            'tpl' => '@PAGE@',
        ];
        $this->assertEquals(parsePageTemplate($data), 'start');
    }

    function test_start_namespace(){
        global $conf;
        $conf['startpage'] = 'start';
        $data = [
            'id' => 'wiki:topic:start',
            'tpl' => '@PAGE@',
        ];
        $this->assertEquals(parsePageTemplate($data), 'topic');
    }
}
//Setup VIM: ex: et ts=4 :
