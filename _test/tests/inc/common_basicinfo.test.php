<?php

class common_infofunctions_test extends DokuWikiTest {

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
     * Its important to have the correct set of keys.
     * Other functions provide the values
     */
    function test_basicinfo(){
        global $ID;
        // test with REMOTE_USER set and the user an admin user
        $info = $this->_get_info();
        $this->assertEquals(basicinfo($ID,true),$info);

        // with $httpclient parameter set to false
        unset($info['ismobile']);
        $this->assertEquals(basicinfo($ID,false),$info);

        // with anonymous user
        unset($_SERVER['REMOTE_USER']);
        global $USERINFO; $USERINFO = array();

        $info = array(
          'isadmin' => false,
          'ismanager' => false,
          'perm' => 8,
          'namespace' => false,
          'ismobile' => false,
          'client' => '1.2.3.4',
        );
        $this->assertEquals(basicinfo($ID,true),$info);
    }

}

//Setup VIM: ex: et ts=4 :
