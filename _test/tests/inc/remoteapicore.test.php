<?php

use dokuwiki\Remote\Api;
use dokuwiki\Remote\ApiCore;
use dokuwiki\test\mock\AuthPlugin;
use dokuwiki\test\mock\AuthDeletePlugin;

/**
 * Class remoteapicore_test
 */
class remoteapicore_test extends DokuWikiTest {

    protected $userinfo;
    protected $oldAuthAcl;
    /** @var  Api */
    protected $remote;

    public function setUp() : void {
        // we need a clean setup before each single test:
        DokuWikiTest::setUpBeforeClass();

        parent::setUp();
        global $conf;
        global $USERINFO;
        global $AUTH_ACL;
        global $auth;
        $this->oldAuthAcl = $AUTH_ACL;
        $this->userinfo = $USERINFO;
        $auth = new AuthPlugin();

        $conf['remote'] = 1;
        $conf['remoteuser'] = '@user';
        $conf['useacl'] = 0;

        $this->remote = new Api();
    }

    public function tearDown() : void {
        parent::tearDown();

        global $USERINFO;
        global $AUTH_ACL;

        $USERINFO = $this->userinfo;
        $AUTH_ACL = $this->oldAuthAcl;
    }

    /** Delay writes of old revisions by a second. */
    public function handle_write(Doku_Event $event, $param) {
        if ($event->data[3] !== false) {
            $this->waitForTick();
        }
    }

    public function test_getVersion() {
        $this->assertEquals(getVersion(), $this->remote->call('dokuwiki.getVersion'));
    }

    public function test_getPageList() {
        $file1 = wikiFN('wiki:dokuwiki');
        $file2 = wikiFN('wiki:syntax');
        $expected = array(
            array(
                'id' => 'wiki:dokuwiki',
                'rev' => filemtime($file1),
                'mtime' => filemtime($file1),
                'size' => filesize($file1),
                'hash' => md5(trim(rawWiki('wiki:dokuwiki')))
            ),
            array(
                'id' => 'wiki:syntax',
                'rev' => filemtime($file2),
                'mtime' => filemtime($file2),
                'size' => filesize($file2),
                'hash' => md5(trim(rawWiki('wiki:syntax')))
            )
        );
        $params = array(
            'wiki:',
            array(
                'depth' => 0, // 0 for all
                'hash' => 1,
                'skipacl' => 1 // is ignored
            )
        );
        $this->assertEquals($expected, $this->remote->call('dokuwiki.getPagelist', $params));
    }

    public function test_search() {
        $id = 'wiki:syntax';
        $file = wikiFN($id);

        idx_addPage($id); //full text search depends on index
        $expected = array(
            array(
                'id' => $id,
                'score' => 1,
                'rev' => filemtime($file),
                'mtime' => filemtime($file),
                'size' => filesize($file),
                'snippet' => ' a footnote)) by using double parentheses.

===== <strong class="search_hit">Sectioning</strong> =====

You can use up to five different levels of',
                'title' => 'wiki:syntax'
            )
        );
        $params = array('Sectioning');
        $this->assertEquals($expected, $this->remote->call('dokuwiki.search', $params));
    }

    public function test_getTime() {
        $timeexpect = time();
        $timeactual = $this->remote->call('dokuwiki.getTime');
        $this->assertTrue(($timeexpect <= $timeactual) && ($timeactual <= $timeexpect + 1));
    }

    public function test_setLocks() {
        $expected = array(
            'locked' => array('wiki:dokuwiki', 'wiki:syntax', 'nonexisting'),
            'lockfail' => array(),
            'unlocked' => array(),
            'unlockfail' => array(),
        );
        $params = array(
            array(
                'lock' => array('wiki:dokuwiki', 'wiki:syntax', 'nonexisting'),
                'unlock' => array()
            )
        );
        $this->assertEquals($expected, $this->remote->call('dokuwiki.setLocks', $params));

        $expected = array(
            'locked' => array(),
            'lockfail' => array(),
            'unlocked' => array('wiki:dokuwiki', 'wiki:syntax', 'nonexisting'),
            'unlockfail' => array('nonexisting2'),
        );
        $params = array(
            array(
                'lock' => array(),
                'unlock' => array('wiki:dokuwiki', 'wiki:syntax', 'nonexisting', 'nonexisting2')
            )
        );
        $this->assertEquals($expected, $this->remote->call('dokuwiki.setLocks', $params));
    }

    public function test_getTitle() {
        global $conf;
        $this->assertEquals($conf['title'], $this->remote->call('dokuwiki.getTitle'));
    }

    public function test_putPage() {
        $id = 'putpage';

        $content = "====Title====\nText";
        $params = array(
            $id,
            $content,
            array(
                'minor' => false,
                'sum' => 'Summary of nice text'
            )
        );
        $this->assertTrue($this->remote->call('wiki.putPage', $params));
        $this->assertEquals($content, rawWiki($id));

        //remove page
        $params = array(
            $id,
            '',
            array(
                'minor' => false,
            )
        );
        $this->assertTrue($this->remote->call('wiki.putPage', $params));
        $this->assertFileNotExists(wikiFN($id));
    }

    public function test_getPage() {
        $id = 'getpage';
        $content = 'a test';
        saveWikiText($id, $content, 'test for getpage');

        $params = array($id);
        $this->assertEquals($content, $this->remote->call('wiki.getPage', $params));
    }

    public function test_appendPage() {
        $id = 'appendpage';
        $content = 'a test';
        $morecontent = "\nOther text";
        saveWikiText($id, $content, 'local');

        $params = array(
            $id,
            $morecontent,
            array()
        );
        $this->assertEquals(true, $this->remote->call('dokuwiki.appendPage', $params));
        $this->assertEquals($content . $morecontent, rawWiki($id));
    }

    public function test_getPageVersion() {
        $id = 'pageversion';
        $file = wikiFN($id);

        saveWikiText($id, 'first version', 'first');
        $rev1 = filemtime($file);
        clearstatcache(false, $file);
        $this->waitForTick(true);
        saveWikiText($id, 'second version', 'second');
        $rev2 = filemtime($file);

        $params = array($id, '');
        $this->assertEquals('second version', $this->remote->call('wiki.getPageVersion', $params), 'no revision given');

        $params = array($id, $rev1);
        $this->assertEquals('first version', $this->remote->call('wiki.getPageVersion', $params), '1st revision given');

        $params = array($id, $rev2);
        $this->assertEquals('second version', $this->remote->call('wiki.getPageVersion', $params), '2nd revision given');

        $params = array($id, 1234);
        $this->assertEquals('', $this->remote->call('wiki.getPageVersion', $params), 'Non existing revision given');

        $params = array('foobar', 1234);
        $this->assertEquals('', $this->remote->call('wiki.getPageVersion', $params), 'Non existing page given');
    }

    public function test_getPageHTML() {
        $id = 'htmltest';
        $content = "====Title====\nText";
        $html = "\n<h3 class=\"sectionedit1\" id=\"title\">Title</h3>\n<div class=\"level3\">\n\n<p>\nText\n</p>\n\n</div>\n";

        saveWikiText($id, $content, 'htmltest');

        $params = array($id);
        $this->assertEquals($html, $this->remote->call('wiki.getPageHTML', $params));
    }

    public function test_getPageHTMLVersion() {
        $id = 'htmltest';
        $file = wikiFN($id);

        $content1 = "====Title====\nText";
        $html1 = "\n<h3 class=\"sectionedit1\" id=\"title\">Title</h3>\n<div class=\"level3\">\n\n<p>\nText\n</p>\n\n</div>\n";
        $content2 = "====Foobar====\nText Bamm";
        $html2 = "\n<h3 class=\"sectionedit1\" id=\"foobar\">Foobar</h3>\n<div class=\"level3\">\n\n<p>\nText Bamm\n</p>\n\n</div>\n";

        saveWikiText($id, $content1, 'first');
        $rev1 = filemtime($file);
        clearstatcache(false, $file);
        $this->waitForTick(true);
        saveWikiText($id, $content2, 'second');
        $rev2 = filemtime($file);

        $params = array($id, '');
        $this->assertEquals($html2, $this->remote->call('wiki.getPageHTMLVersion', $params), 'no revision given');

        $params = array($id, $rev1);
        $this->assertEquals($html1, $this->remote->call('wiki.getPageHTMLVersion', $params), '1st revision given');

        $params = array($id, $rev2);
        $this->assertEquals($html2, $this->remote->call('wiki.getPageHTMLVersion', $params), '2nd revision given');

        $params = array($id, 1234);
        $this->assertEquals('', $this->remote->call('wiki.getPageHTMLVersion', $params), 'Non existing revision given');

        $params = array('foobar', 1234);
        $this->assertEquals('', $this->remote->call('wiki.getPageHTMLVersion', $params), 'Non existing page given');
    }

    public function test_getAllPages() {
        // all pages depends on index
        idx_addPage('wiki:syntax');
        idx_addPage('wiki:dokuwiki');

        $file1 = wikiFN('wiki:syntax');
        $file2 = wikiFN('wiki:dokuwiki');

        $expected = array(
            array(
                'id' => 'wiki:syntax',
                'perms' => 8,
                'size' => filesize($file1),
                'lastModified' => filemtime($file1)
            ),
            array(
                'id' => 'wiki:dokuwiki',
                'perms' => 8,
                'size' => filesize($file2),
                'lastModified' => filemtime($file2)
            )
        );
        $this->assertEquals($expected, $this->remote->call('wiki.getAllPages'));
    }

    public function test_getBacklinks() {
        saveWikiText('linky', '[[wiki:syntax]]', 'test');
        // backlinks need index
        idx_addPage('wiki:syntax');
        idx_addPage('linky');

        $params = array('wiki:syntax');
        $result = $this->remote->call('wiki.getBackLinks', $params);
        $this->assertTrue(count($result) > 0);
        $this->assertEquals(ft_backlinks('wiki:syntax'), $result);
    }

    public function test_getPageInfo() {
        $id = 'pageinfo';
        $file = wikiFN($id);

        saveWikiText($id, 'test', 'test');

        $expected = array(
            'name' => $id,
            'lastModified' => filemtime($file),
            'author' => clientIP(),
            'version' => filemtime($file)
        );
        $params = array($id);
        $this->assertEquals($expected, $this->remote->call('wiki.getPageInfo', $params));
    }

    public function test_getPageInfoVersion() {
        $id = 'pageinfo';
        $file = wikiFN($id);

        saveWikiText($id, 'first version', 'first');
        $rev1 = filemtime($file);
        clearstatcache(false, $file);
        $this->waitForTick(true);
        saveWikiText($id, 'second version', 'second');
        $rev2 = filemtime($file);

        $expected = array(
            'name' => $id,
            'lastModified' => $rev2,
            'author' => clientIP(),
            'version' => $rev2
        );
        $params = array($id, '');
        $this->assertEquals($expected, $this->remote->call('wiki.getPageInfoVersion', $params), 'no revision given');

        $expected = array(
            'name' => $id,
            'lastModified' => $rev1,
            'author' => clientIP(),
            'version' => $rev1
        );
        $params = array($id, $rev1);
        $this->assertEquals($expected, $this->remote->call('wiki.getPageInfoVersion', $params), '1st revision given');

        $expected = array(
            'name' => $id,
            'lastModified' => $rev2,
            'author' => clientIP(),
            'version' => $rev2
        );
        $params = array($id, $rev2);
        $this->assertEquals($expected, $this->remote->call('wiki.getPageInfoVersion', $params), '2nd revision given');
    }

    public function test_getRecentChanges() {

        saveWikiText('pageone', 'test', 'test');
        $rev1 = filemtime(wikiFN('pageone'));
        saveWikiText('pagetwo', 'test', 'test');
        $rev2 = filemtime(wikiFN('pagetwo'));

        $expected = array(
            array(
                'name' => 'pageone',
                'lastModified' => $rev1,
                'author' => '',
                'version' => $rev1,
                'perms' => 8,
                'size' => 4
            ),
            array(
                'name' => 'pagetwo',
                'lastModified' => $rev2,
                'author' => '',
                'version' => $rev2,
                'perms' => 8,
                'size' => 4
            )
        );
        $params = array(strtotime("-1 year"));
        $this->assertEquals($expected, $this->remote->call('wiki.getRecentChanges', $params));
    }

    public function test_getPageVersions() {
        /** @var $EVENT_HANDLER \dokuwiki\Extension\EventHandler */
        global $EVENT_HANDLER;
        $EVENT_HANDLER->register_hook('IO_WIKIPAGE_WRITE', 'BEFORE', $this, 'handle_write');
        global $conf;

        $id = 'revpage';
        $file = wikiFN($id);

        $rev = array();
        for($i = 0; $i < 6; $i++) {
            $this->waitForTick();
            saveWikiText($id, "rev$i", "rev$i");
            clearstatcache(false, $file);
            $rev[$i] = filemtime($file);
        }

        $params = array($id, 0);
        $versions = $this->remote->call('wiki.getPageVersions', $params);
        $this->assertEquals(6, count($versions));
        $this->assertEquals($rev[5], $versions[0]['version']);
        $this->assertEquals($rev[4], $versions[1]['version']);
        $this->assertEquals($rev[3], $versions[2]['version']);
        $this->assertEquals($rev[2], $versions[3]['version']);
        $this->assertEquals($rev[1], $versions[4]['version']);
        $this->assertEquals($rev[0], $versions[5]['version']);

        $params = array($id, 1); // offset 1
        $versions = $this->remote->call('wiki.getPageVersions', $params);
        $this->assertEquals(5, count($versions));
        $this->assertEquals($rev[4], $versions[0]['version']);
        $this->assertEquals($rev[3], $versions[1]['version']);
        $this->assertEquals($rev[2], $versions[2]['version']);
        $this->assertEquals($rev[1], $versions[3]['version']);
        $this->assertEquals($rev[0], $versions[4]['version']);

        $conf['recent'] = 3; //set number of results per page

        $params = array($id, 0); // first page
        $versions = $this->remote->call('wiki.getPageVersions', $params);
        $this->assertEquals(3, count($versions));
        $this->assertEquals($rev[5], $versions[0]['version']);
        $this->assertEquals($rev[4], $versions[1]['version']);
        $this->assertEquals($rev[3], $versions[2]['version']);

        $params = array($id, $conf['recent']); // second page
        $versions = $this->remote->call('wiki.getPageVersions', $params);
        $this->assertEquals(3, count($versions));
        $this->assertEquals($rev[2], $versions[0]['version']);
        $this->assertEquals($rev[1], $versions[1]['version']);
        $this->assertEquals($rev[0], $versions[2]['version']);

        $params = array($id, $conf['recent'] * 2); // third page
        $versions = $this->remote->call('wiki.getPageVersions', $params);
        $this->assertEquals(0, count($versions));
    }

    public function test_deleteUser()
    {
        global $conf, $auth;
        $auth = new AuthDeletePlugin();
        $conf['remote'] = 1;
        $conf['remoteuser'] = 'testuser';
        $_SERVER['REMOTE_USER'] = 'testuser';
        $params = [
            ['testuser']
        ];
        $actualCallResult = $this->remote->call('dokuwiki.deleteUsers', $params);
        $this->assertTrue($actualCallResult);
    }

    public function test_aclCheck() {
        $id = 'aclpage';

        $params = array($id);
        $this->assertEquals(AUTH_UPLOAD, $this->remote->call('wiki.aclCheck', $params));

        global $conf;
        global $AUTH_ACL, $USERINFO;
        $conf['useacl'] = 1;
        $_SERVER['REMOTE_USER'] = 'john';
        $USERINFO['grps'] = array('user');
        $AUTH_ACL = array(
            '*                  @ALL           0',
            '*                  @user          2', //edit
        );

        $params = array($id);
        $this->assertEquals(AUTH_EDIT, $this->remote->call('wiki.aclCheck', $params));
    }

    public function test_getXMLRPCAPIVersion() {
        $this->assertEquals(ApiCore::API_VERSION, $this->remote->call('dokuwiki.getXMLRPCAPIVersion'));
    }

    public function test_getRPCVersionSupported() {
        $this->assertEquals(2, $this->remote->call('wiki.getRPCVersionSupported'));
    }

    public function test_listLinks() {
        $localdoku = array(
            'type' => 'local',
            'page' => 'DokuWiki',
            'href' => DOKU_BASE . DOKU_SCRIPT . '?id=DokuWiki'
        );
        $expected = array(  //no local links
                            $localdoku,
                            array(
                                'type' => 'extern',
                                'page' => 'http://www.freelists.org',
                                'href' => 'http://www.freelists.org'
                            ),
                            array(
                                'type' => 'extern',
                                'page' => 'https://tools.ietf.org/html/rfc1855',
                                'href' => 'https://tools.ietf.org/html/rfc1855'
                            ),
                            array(
                                'type' => 'extern',
                                'page' => 'http://www.catb.org/~esr/faqs/smart-questions.html',
                                'href' => 'http://www.catb.org/~esr/faqs/smart-questions.html'
                            ),
                            $localdoku,
                            $localdoku
        );
        $params = array('mailinglist');
        $this->assertEquals($expected, $this->remote->call('wiki.listLinks', $params));
    }

    public function test_coreattachments() {
        global $conf;
        global $AUTH_ACL, $USERINFO;

        $filecontent = io_readFile(mediaFN('wiki:dokuwiki-128.png'), false);
        $params = array('test:dokuwiki-128_2.png', $filecontent, array('ow' => false));
        $this->assertEquals('test:dokuwiki-128_2.png', $this->remote->call('wiki.putAttachment', $params)); //prints a success div

        $params = array('test:dokuwiki-128_2.png');
        $this->assertEquals($filecontent, $this->remote->call('wiki.getAttachment', $params));
        $rev = filemtime(mediaFN('test:dokuwiki-128_2.png'));

        $expected = array(
            'lastModified' => $rev,
            'size' => 27895,
        );
        $params = array('test:dokuwiki-128_2.png');
        $this->assertEquals($expected, $this->remote->call('wiki.getAttachmentInfo', $params));

        $params = array(strtotime("-5 year"));
        $expected = array(
            array(
                'name' => 'test:dokuwiki-128_2.png',
                'lastModified' => $rev,
                'author' => '',
                'version' => $rev,
                'perms' => 8,
                'size' => 27895 //actual size, not size change
            )
        );
        $this->assertEquals($expected, $this->remote->call('wiki.getRecentMediaChanges', $params));

        $this->waitForTick(true);
        $conf['useacl'] = 1;
        $_SERVER['REMOTE_USER'] = 'john';
        $USERINFO['grps'] = array('user');
        $AUTH_ACL = array(
            '*                  @ALL           0',
            '*                  @user          16',
        );

        $params = array('test:dokuwiki-128_2.png');
        $this->assertEquals(0, $this->remote->call('wiki.deleteAttachment', $params));

        $rev2 = filemtime($conf['media_changelog']);
        $expected = array(
            'lastModified' => $rev2,
            'size' => 0,
        );
        $params = array('test:dokuwiki-128_2.png');
        $this->assertEquals($expected, $this->remote->call('wiki.getAttachmentInfo', $params));

        $expected = array(
            'lastModified' => 0,
            'size' => 0,
        );
        $params = array('test:nonexisting.png');
        $this->assertEquals($expected, $this->remote->call('wiki.getAttachmentInfo', $params));

        $media1 = mediaFN('wiki:dokuwiki-128.png');
        $expected = array(
            array(
                'id' => 'wiki:dokuwiki-128.png',
                'file' => 'dokuwiki-128.png',
                'size' => filesize($media1),
                'mtime' => filemtime($media1),
                'writable' => 1,
                'isimg' => 1,
                'hash' => md5(io_readFile($media1, false)),
                'perms' => 16,
                'lastModified' => filemtime($media1)
            )
        );
        $params = array(
            'wiki:',
            array(
                'depth' => 0, // 0 for all
                'hash' => 1,
                'skipacl' => 1, // is ignored
                'showmsg' => true, //useless??
                'pattern' => '/128/' //filter
            )
        );
        $this->assertEquals($expected, $this->remote->call('wiki.getAttachments', $params));
    }

}
