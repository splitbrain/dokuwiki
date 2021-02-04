<?php

/**
 * These tests are designed to test the capacity of pluginauth to handle
 * correct escaping of colon field delimiters and backslashes in user content.
 *
 * (Note that these tests set some Real Names, etc. that are may not be
 * valid in the broader dokuwiki context, but the tests ensure that
 * authplain won't get unexpectedly surprised.)
 *
 * @group plugin_authplain
 * @group auth_plugins
 * @group plugins
 * @group bundled_plugins
 */
class helper_plugin_authplain_escaping_test extends DokuWikiTest {

    protected $pluginsEnabled = array('authplainharness');
    /** @var  auth_plugin_authplain|auth_plugin_authplainharness */
    protected $auth;

    protected function reloadUsers() {
        /* auth caches data loaded from file, but recreated object forces reload */
        $this->auth = new auth_plugin_authplainharness();
    }

    function setUp() : void {
        global $config_cascade;
        parent::setUp();
        $name = $config_cascade['plainauth.users']['default'];
        copy($name, $name.".orig");
        $this->reloadUsers();
    }

    function tearDown() : void {
        global $config_cascade;
        parent::tearDown();
        $name = $config_cascade['plainauth.users']['default'];
        copy($name.".orig", $name);
    }

    public function testMediawikiPasswordHash() {
        global $conf;
        $conf['passcrypt'] = 'mediawiki';
        $this->auth->createUser("mwuser", "12345", "Mediawiki User", "me@example.com");
        $this->reloadUsers();
        $this->assertTrue($this->auth->checkPass("mwuser", "12345"));
        $mwuser = $this->auth->getUserData("mwuser");
        $this->assertStringStartsWith(":B:",$mwuser['pass']);
        $this->assertEquals("Mediawiki User",$mwuser['name']);
    }

    public function testNameWithColons() {
        $name = ":Colon: User:";
        $this->auth->createUser("colonuser", "password", $name, "me@example.com");
        $this->reloadUsers();
        $user = $this->auth->getUserData("colonuser");
        $this->assertEquals($name,$user['name']);
    }

    public function testNameWithBackslashes() {
        $name = "\\Slash\\ User\\";
        $this->auth->createUser("slashuser", "password", $name, "me@example.com");
        $this->reloadUsers();
        $user = $this->auth->getUserData("slashuser");
        $this->assertEquals($name,$user['name']);
    }

    public function testModifyUser() {
        global $conf;
        $conf['passcrypt'] = 'mediawiki';
        $user = $this->auth->getUserData("testuser");
        $user['name'] = "\\New:Crazy:Name\\";
        $user['pass'] = "awesome new password";
        $this->auth->modifyUser("testuser", $user);
        $this->reloadUsers();

        $saved = $this->auth->getUserData("testuser");
        $this->assertEquals($saved['name'], $user['name']);
        $this->assertTrue($this->auth->checkPass("testuser", $user['pass']));
    }

    // really only required for developers to ensure this plugin will
    // work with systems running on PCRE 6.6 and lower.
    public function testLineSplit(){
        $this->auth->setPregsplit_safe(false);

        $names = array(
          'plain',
          'ut-fठ8',
          'colon:',
          'backslash\\',
          'alltogether\\ठ:'
        );
        $userpass = 'user:password_hash:';
        $other_user_data = ':email@address:group1,group2';

        foreach ($names as $testname) {
            $escaped = str_replace(array('\\',':'),array('\\\\','\\:'),$testname);   // escape : & \
            $test_line = $userpass.$escaped.$other_user_data;
            $result = $this->auth->splitUserData($test_line);

            $this->assertEquals($escaped, $result[2]);
        }
    }
}

/**
 * Class auth_plugin_authplainharness
 */
class auth_plugin_authplainharness extends auth_plugin_authplain {

    /**
     * @param boolean $bool
     */
    public function setPregsplit_safe($bool) {
        $this->pregsplit_safe = $bool;
    }

    /**
     * @return bool|mixed
     */
    public function getPregsplit_safe(){
        return $this->pregsplit_safe;
    }

    /**
     * @param string $line
     * @return array
     */
    public function splitUserData($line){
        return parent::splitUserData($line);
    }
}
