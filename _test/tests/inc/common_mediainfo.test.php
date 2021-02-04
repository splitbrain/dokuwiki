<?php

class common_basicinfo_test extends DokuWikiTest {

    function setup() : void {
        parent::setup();

        global $USERINFO;
        $USERINFO = array(
           'pass' => '179ad45c6ce2cb97cf1029e212046e81',
           'name' => 'Arthur Dent',
           'mail' => 'arthur@example.com',
           'grps' => array ('admin','user'),
        );
        $_SERVER['REMOTE_USER'] = 'testuser';
        $_SERVER['REMOTE_ADDR'] = '1.2.3.4';
    }

    function _get_info() {
        global $USERINFO;
        $info = array (
          'isadmin' => true,
          'ismanager' => true,
          'userinfo' => $USERINFO,
          'perm' => 255,
          'namespace' => false,
          'ismobile' => false,
          'client' => 'testuser',
        );

        return $info;
    }

    /**
     * We're interested in the extra keys for $INFO when its a media request
     */
    function test_mediainfo(){
        global $NS, $IMG;
        $NS = '';
        $IMG = 'testimage.png';

        $info = $this->_get_info();
        $info['image'] = 'testimage.png';

        $this->assertEquals(mediainfo(),$info);
    }
}

//Setup VIM: ex: et ts=4 :
