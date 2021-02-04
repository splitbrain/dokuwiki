<?php
/**
 *  auth_loadACL carries out the user & group substitutions
 *
 * @author     Chris Smith <chris@jalakai.co.uk>
 */

class auth_loadacl_test extends DokuWikiTest {

    function setUp() : void {
        global $USERINFO;
        parent::setUp();
        $_SERVER['REMOTE_USER'] = 'testuser';
        $USERINFO['grps'] = array('foo','bar');
    }

    function tearDown() : void {
        parent::tearDown();
    }

    function auth_loadACL_testwrapper($acls) {
        global $config_cascade;
        $acl_file = $config_cascade['acl']['default'];

        $config_cascade['acl']['default'] .= '.test';
        file_put_contents($config_cascade['acl']['default'],$acls);

        $result = auth_loadACL();

        unlink($config_cascade['acl']['default']);
        $config_cascade['acl']['default'] = $acl_file;

        return $result;
    }

    function test_simple() {
        $acls = <<<ACL
* @ALL 2
ACL;
        $expect = array("*\t@ALL 2");
        $this->assertEquals($expect, $this->auth_loadACL_testwrapper($acls));
    }

    function test_user_substitution() {
        $acls = <<<ACL
%USER% %USER% 2
ACL;
        $expect = array(
            "testuser\ttestuser 2",
        );
        $this->assertEquals($expect, $this->auth_loadACL_testwrapper($acls));
    }

    function test_group_substitution() {
        $acls = <<<ACL
%GROUP% %GROUP% 2
ACL;
        $expect = array(
            "foo\t@foo 2",
            "bar\t@bar 2",
        );
        $this->assertEquals($expect, $this->auth_loadACL_testwrapper($acls));
    }

    function test_both_substitution() {
        $acls = <<<ACL
%GROUP%:%USER% %USER% 2
%GROUP%:%USER% %GROUP% 2
ACL;
        $expect = array(
            "foo:testuser\ttestuser 2",
            "bar:testuser\ttestuser 2",
            "foo:testuser\t@foo 2",
            "bar:testuser\t@bar 2",
        );
        $this->assertEquals($expect, $this->auth_loadACL_testwrapper($acls));
    }

    // put it all together - read the standard acl provided with the test suite
    function test_standardtestacls(){
        $expect = array(
            "*\t@ALL        8",
            "private:*\t@ALL        0",
            "users:*\t@ALL         1",
            "users:testuser:*\ttestuser       16",
            "groups:*\t@ALL         1",
            "groups:foo:*\t@foo      16",
            "groups:bar:*\t@bar      16",
        );
        $this->assertEquals($expect, auth_loadACL());
    }

    // FS#2867, '\s' in php regular expressions may match non-space characters utf8 strings
    // this is due to locale setting on the server, which may match bytes '\xA0' and '\x85'
    // these two bytes are present in valid multi-byte UTF-8 characters.
    // this test will use one, 'ठ' (DEVANAGARI LETTER TTHA, e0 a4 a0).  There are many others.
    function test_FS2867() {
        global $USERINFO;

        $old_locale = setlocale(LC_ALL, '0');
        setlocale(LC_ALL, "English_United States.1252");  // should only succeed on windows systems
        setlocale(LC_ALL, "en_US.UTF-8");                 // should succeed on other systems

        // no point continuing with this test if \s doesn't match A0
        if (!preg_match('/\s/',"\xa0")) {
            setlocale(LC_ALL, $old_locale);
            $this->markTestSkipped('Unable to change locale.');
        }

        $_SERVER['REMOTE_USER'] = 'utfठ8';
        $USERINFO['grps'] = array('utfठ16','utfठa');

        $acls = <<<ACL
%GROUP%:%USER% %USER% 2
%GROUP%:* %GROUP% 4
devangariठttha @ALL 2
ACL;
        $expect = array(
            "utfठ16:utfठ8\tutfठ8 2",
            "utfठa:utfठ8\tutfठ8 2",
            "utfठ16:*\t@utfठ16 4",
            "utfठa:*\t@utfठa 4",
            "devangariठttha\t@ALL 2",
        );
        $this->assertEquals($expect, $this->auth_loadACL_testwrapper($acls));
        setlocale(LC_ALL, $old_locale);
    }
}

//Setup VIM: ex: et ts=4 :
