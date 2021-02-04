<?php
/**
 * Unit Test for inc/common.php - pageinfo()
 *
 * @author Christopher Smith <chris@jalakai.co.uk>
 */
class common_pageinfo_test extends DokuWikiTest {

    function setup() : void{
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

    function _get_expected_pageinfo() {
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
        $info['rev'] = null;
        $info['subscribed'] = false;
        $info['locked'] = false;
        $info['exists'] = false;
        $info['writable'] = true;
        $info['editable'] = true;
        $info['lastmod'] = false;
        $info['currentrev'] = false;
        $info['meta'] = array();
        $info['ip'] = null;
        $info['user'] = null;
        $info['sum'] = null;
        $info['editor'] = null;

        return $info;
    }

    /**
     *  check info keys and values for a non-existent page & admin user
     */
    function test_basic_nonexistentpage(){
        global $ID,$conf;
        $ID = 'wiki:start';

        $info = $this->_get_expected_pageinfo();
        $info['id'] = 'wiki:start';
        $info['namespace'] = 'wiki';
        $info['filepath'] = $conf['datadir'].'/wiki/start.txt';

        $this->assertEquals($info, pageinfo());
    }

    /**
     *  check info keys and values for a existing page & admin user
     */
    function test_basic_existingpage(){
        global $ID,$conf;
        $ID = 'wiki:syntax';
        $filename = $conf['datadir'].'/wiki/syntax.txt';
        $rev = filemtime($filename);

        $info = $this->_get_expected_pageinfo();
        $info['id'] = 'wiki:syntax';
        $info['namespace'] = 'wiki';
        $info['filepath'] = $filename;
        $info['exists'] = true;
        $info['lastmod'] = $rev;
        $info['currentrev'] = $rev;
        $info['meta'] = p_get_metadata($ID);

        $this->assertEquals($info, pageinfo());
    }

    /**
     *  check info keys and values for anonymous user
     */
    function test_anonymoususer(){
        global $ID,$conf,$REV;

        unset($_SERVER['REMOTE_USER']);
        global $USERINFO; $USERINFO = array();

        $ID = 'wiki:syntax';
        $filename = $conf['datadir'].'/wiki/syntax.txt';
        $rev = filemtime($filename);

        $info = $this->_get_expected_pageinfo();
        $info['id'] = 'wiki:syntax';
        $info['namespace'] = 'wiki';
        $info['filepath'] = $filename;
        $info['exists'] = true;
        $info['lastmod'] = $rev;
        $info['currentrev'] = $rev;
        $info['meta'] = p_get_metadata($ID);
        $info['rev'] = '';

        $info = array_merge($info, array(
          'isadmin' => false,
          'ismanager' => false,
          'perm' => 8,
          'client' => '1.2.3.4',
        ));
        unset($info['userinfo']);
        $this->assertEquals($info, pageinfo());
    }

    /**
     *  check info keys and values with $REV
     *  (also see $RANGE tests)
     */
    function test_rev(){
        global $ID,$conf,$REV;

        $ID = 'wiki:syntax';
        $filename = $conf['datadir'].'/wiki/syntax.txt';
        $rev = filemtime($filename);
        $REV = $rev - 100;
        $ext = '.txt';
        if($conf['compression']) {  //compression in $info['filepath'] determined by wikiFN depends also on if the page exist
            $ext .= "." . $conf['compression']; //.gz or .bz2
        }

        $info = $this->_get_expected_pageinfo();
        $info['id'] = 'wiki:syntax';
        $info['namespace'] = 'wiki';
        $info['meta'] = p_get_metadata($ID);
        $info['rev'] = $REV;
        $info['currentrev'] = $rev;
        $info['filepath'] = str_replace('pages','attic',substr($filename,0,-3).$REV.$ext);

        $this->assertEquals($info, pageinfo());
        $this->assertEquals($rev-100, $REV);
    }

    /**
     *  check info keys and values with $RANGE
     */
    function test_range(){
        global $ID,$conf,$REV,$RANGE;

        $ID = 'wiki:syntax';
        $filename = $conf['datadir'].'/wiki/syntax.txt';
        $rev = filemtime($filename);
        $range = '1000-2000';

        $info = $this->_get_expected_pageinfo();
        $info['id'] = 'wiki:syntax';
        $info['namespace'] = 'wiki';
        $info['exists'] = true;
        $info['lastmod'] = $rev;
        $info['currentrev'] = $rev;
        $info['meta'] = p_get_metadata($ID);
        $info['filepath'] = $filename;

        // check $RANGE without $REV
        // expected result $RANGE unchanged
        $RANGE = $range;

        $this->assertEquals($info, pageinfo());
        $this->assertFalse(isset($REV));
        $this->assertEquals($range,$RANGE);

        // check $RANGE with $REV = current
        // expected result: $RANGE unchanged, $REV cleared
        $REV = $rev;
        $info['rev'] = '';

        $this->assertEquals($info, pageinfo());
        $this->assertEquals('',$REV);
        $this->assertEquals($range,$RANGE);

        // check with a real $REV
        // expected result: $REV and $RANGE are cleared
        $REV = $rev - 100;

        $this->assertEquals($info, pageinfo());
        $this->assertEquals('',$REV);
        $this->assertEquals('',$RANGE);
    }

    /**
     *  test editor entry and external edit
     */
    function test_editor_and_externaledits(){
        global $ID,$conf;
        $ID = 'wiki:syntax';
        $filename = $conf['datadir'].'/wiki/syntax.txt';
        $rev = filemtime($filename);

        $info = $this->_get_expected_pageinfo();
        $info['id'] = 'wiki:syntax';
        $info['namespace'] = 'wiki';
        $info['filepath'] = $filename;
        $info['exists'] = true;
        $info['lastmod'] = $rev;
        $info['currentrev'] = $rev;
        $info['meta'] = p_get_metadata($ID);  // need $INFO set correctly for addLogEntry()

        global $INFO;
        $INFO = $info;

        // add an editor for the current version of $ID
        addLogEntry($rev, $ID);

        $info['meta'] = p_get_metadata($ID);
        $info['editor'] = $_SERVER['REMOTE_USER'];
        $info['user'] = $_SERVER['REMOTE_USER'];
        $info['ip'] = $_SERVER['REMOTE_ADDR'];
        $info['sum'] = '';

        // with an editor ...
        $this->assertEquals($info, pageinfo());

        // clear the meta['last_change'] value, pageinfo should restore it
        p_set_metadata($ID,array('last_change' => false));

        $this->assertEquals($info, pageinfo());
        $this->assertEquals($info['meta']['last_change'], p_get_metadata($ID,'last_change'));

        // fake an external edit, pageinfo should clear the last change from meta data
        // and not return any editor data
        $now = time()+10;
        touch($filename,$now);

        $info['lastmod'] = $now;
        $info['currentrev'] = $now;
        $info['meta']['last_change'] = false;
        $info['ip'] = null;
        $info['user'] = null;
        $info['sum'] = null;
        $info['editor'] = null;

        $this->assertEquals($info, pageinfo());
        $this->assertEquals($info['meta'], p_get_metadata($ID));   // check metadata has been updated correctly
    }

    /**
     *  check draft
     */
    function test_draft(){
        global $ID,$conf;
        $ID = 'wiki:syntax';
        $filename = $conf['datadir'].'/wiki/syntax.txt';
        $rev = filemtime($filename);

        $info = $this->_get_expected_pageinfo();
        $info['id'] = 'wiki:syntax';
        $info['namespace'] = 'wiki';
        $info['filepath'] = $filename;
        $info['exists'] = true;
        $info['lastmod'] = $rev;
        $info['currentrev'] = $rev;
        $info['meta'] = p_get_metadata($ID);

        // setup a draft, make it more recent than the current page
        // - pageinfo should recognise it and keep it
        $draft = getCacheName($info['client'].$ID,'.draft');
        touch($draft,$rev + 10);

        $info['draft'] = $draft;

        $this->assertEquals($info, pageinfo());
        $this->assertFileExists($draft);

        // make the draft older than the current page
        // - pageinfo should remove it and not return the 'draft' key
        touch($draft,$rev - 10);
        unset($info['draft']);

        $this->assertEquals($info, pageinfo());
        $this->assertFalse(file_exists($draft));
    }

    /**
     *  check ismobile
     */
    function test_ismobile(){
        global $ID,$conf;
        $ID = 'wiki:start';

        $info = $this->_get_expected_pageinfo();
        $info['id'] = 'wiki:start';
        $info['namespace'] = 'wiki';
        $info['filepath'] = $conf['datadir'].'/wiki/start.txt';

        // overkill, ripped from clientismobile() as we aren't testing detection - but forcing it
        $_SERVER['HTTP_X_WAP_PROFILE'] = 'a fake url';
        $_SERVER['HTTP_ACCEPT'] .= ';wap';
        $_SERVER['HTTP_USER_AGENT'] = 'blackberry,symbian,hand,mobi,phone';

        $info['ismobile'] = clientismobile();

        $this->assertTrue(clientismobile());     // ensure THIS test fails if clientismobile() returns false
        $this->assertEquals($info, pageinfo());  // it would be a test failure not a pageinfo failure.
    }
}

//Setup VIM: ex: et ts=4 :
