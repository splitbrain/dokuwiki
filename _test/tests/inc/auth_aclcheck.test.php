<?php

use dokuwiki\test\mock\AuthPlugin;

class auth_acl_test extends DokuWikiTest {

    protected $oldAuthAcl;

    function setUp() : void {
        parent::setUp();
        global $AUTH_ACL;
        global $auth;
        $this->oldAuthAcl = $AUTH_ACL;
        $auth = new AuthPlugin();
    }

    function tearDown() : void {
        global $AUTH_ACL;
        $AUTH_ACL = $this->oldAuthAcl;

    }

    function test_restricted(){
        global $conf;
        global $AUTH_ACL;
        $conf['superuser'] = 'john';
        $conf['useacl']    = 1;

        $AUTH_ACL = array(
            '*           @ALL           0',
            '*           @user          8',
        );

        // anonymous user
        $this->assertEquals(auth_aclcheck('page',          '',array()), AUTH_NONE);
        $this->assertEquals(auth_aclcheck('namespace:page','',array()), AUTH_NONE);
        $this->assertEquals(auth_aclcheck('namespace:*',   '',array()), AUTH_NONE);

        // user with no matching group
        $this->assertEquals(auth_aclcheck('page',          'jill',array('foo')), AUTH_NONE);
        $this->assertEquals(auth_aclcheck('namespace:page','jill',array('foo')), AUTH_NONE);
        $this->assertEquals(auth_aclcheck('namespace:*',   'jill',array('foo')), AUTH_NONE);

        // user with matching group
        $this->assertEquals(auth_aclcheck('page',          'jill',array('foo','user')), AUTH_UPLOAD);
        $this->assertEquals(auth_aclcheck('namespace:page','jill',array('foo','user')), AUTH_UPLOAD);
        $this->assertEquals(auth_aclcheck('namespace:*',   'jill',array('foo','user')), AUTH_UPLOAD);

        // super user
        $this->assertEquals(auth_aclcheck('page',          'john',array('foo')), AUTH_ADMIN);
        $this->assertEquals(auth_aclcheck('namespace:page','john',array('foo')), AUTH_ADMIN);
        $this->assertEquals(auth_aclcheck('namespace:*',   'john',array('foo')), AUTH_ADMIN);
    }

    function test_restricted_ropage(){
        global $conf;
        global $AUTH_ACL;
        $conf['superuser'] = 'john';
        $conf['useacl']    = 1;

        $AUTH_ACL = array(
            '*                  @ALL           0',
            '*                  @user          8',
            'namespace:page     @user          1',
        );

        // anonymous user
        $this->assertEquals(auth_aclcheck('page',          '',array()), AUTH_NONE);
        $this->assertEquals(auth_aclcheck('namespace:page','',array()), AUTH_NONE);
        $this->assertEquals(auth_aclcheck('namespace:*',   '',array()), AUTH_NONE);

        // user with no matching group
        $this->assertEquals(auth_aclcheck('page',          'jill',array('foo')), AUTH_NONE);
        $this->assertEquals(auth_aclcheck('namespace:page','jill',array('foo')), AUTH_NONE);
        $this->assertEquals(auth_aclcheck('namespace:*',   'jill',array('foo')), AUTH_NONE);

        // user with matching group
        $this->assertEquals(auth_aclcheck('page',          'jill',array('foo','user')), AUTH_UPLOAD);
        $this->assertEquals(auth_aclcheck('namespace:page','jill',array('foo','user')), AUTH_READ);
        $this->assertEquals(auth_aclcheck('namespace:*',   'jill',array('foo','user')), AUTH_UPLOAD);

        // super user
        $this->assertEquals(auth_aclcheck('page',          'john',array('foo')), AUTH_ADMIN);
        $this->assertEquals(auth_aclcheck('namespace:page','john',array('foo')), AUTH_ADMIN);
        $this->assertEquals(auth_aclcheck('namespace:*',   'john',array('foo')), AUTH_ADMIN);
    }

    function test_aclexample(){
        global $conf;
        global $AUTH_ACL;
        $conf['superuser'] = 'john';
        $conf['useacl']    = 1;

        $AUTH_ACL = array(
            '*                     @ALL        4',
            '*                     bigboss    16',
            'start                 @ALL        1',
            'marketing:*           @marketing  8',
            'devel:*               @ALL        0',
            'devel:*               @devel      8',
            'devel:*               bigboss    16',
            'devel:funstuff        bigboss     0',
            'devel:*               @marketing  1',
            'devel:marketing       @marketing  2',
        );


        $this->assertEquals(auth_aclcheck('page', ''        ,array())            , AUTH_CREATE);
        $this->assertEquals(auth_aclcheck('page', 'bigboss' ,array('foo'))       , AUTH_DELETE);
        $this->assertEquals(auth_aclcheck('page', 'jill'    ,array('marketing')) , AUTH_CREATE);
        $this->assertEquals(auth_aclcheck('page', 'jane'    ,array('devel'))     , AUTH_CREATE);

        $this->assertEquals(auth_aclcheck('start', ''        ,array())            , AUTH_READ);
        $this->assertEquals(auth_aclcheck('start', 'bigboss' ,array('foo'))       , AUTH_READ);
        $this->assertEquals(auth_aclcheck('start', 'jill'    ,array('marketing')) , AUTH_READ);
        $this->assertEquals(auth_aclcheck('start', 'jane'    ,array('devel'))     , AUTH_READ);

        $this->assertEquals(auth_aclcheck('marketing:page', ''        ,array())            , AUTH_CREATE);
        $this->assertEquals(auth_aclcheck('marketing:page', 'bigboss' ,array('foo'))       , AUTH_DELETE);
        $this->assertEquals(auth_aclcheck('marketing:page', 'jill'    ,array('marketing')) , AUTH_UPLOAD);
        $this->assertEquals(auth_aclcheck('marketing:page', 'jane'    ,array('devel'))     , AUTH_CREATE);


        $this->assertEquals(auth_aclcheck('devel:page', ''        ,array())            , AUTH_NONE);
        $this->assertEquals(auth_aclcheck('devel:page', 'bigboss' ,array('foo'))       , AUTH_DELETE);
        $this->assertEquals(auth_aclcheck('devel:page', 'jill'    ,array('marketing')) , AUTH_READ);
        $this->assertEquals(auth_aclcheck('devel:page', 'jane'    ,array('devel'))     , AUTH_UPLOAD);

        $this->assertEquals(auth_aclcheck('devel:funstuff', ''        ,array())            , AUTH_NONE);
        $this->assertEquals(auth_aclcheck('devel:funstuff', 'bigboss' ,array('foo'))       , AUTH_NONE);
        $this->assertEquals(auth_aclcheck('devel:funstuff', 'jill'    ,array('marketing')) , AUTH_READ);
        $this->assertEquals(auth_aclcheck('devel:funstuff', 'jane'    ,array('devel'))     , AUTH_UPLOAD);

        $this->assertEquals(auth_aclcheck('devel:marketing', ''        ,array())            , AUTH_NONE);
        $this->assertEquals(auth_aclcheck('devel:marketing', 'bigboss' ,array('foo'))       , AUTH_DELETE);
        $this->assertEquals(auth_aclcheck('devel:marketing', 'jill'    ,array('marketing')) , AUTH_EDIT);
        $this->assertEquals(auth_aclcheck('devel:marketing', 'jane'    ,array('devel'))     , AUTH_UPLOAD);

    }

    function test_multiadmin_restricted(){
        global $conf;
        global $AUTH_ACL;
        $conf['superuser'] = 'john,@admin,doe,@roots';
        $conf['useacl']    = 1;

        $AUTH_ACL = array(
            '*           @ALL           0',
            '*           @user          8',
        );

        // anonymous user
        $this->assertEquals(auth_aclcheck('page',          '',array()), AUTH_NONE);
        $this->assertEquals(auth_aclcheck('namespace:page','',array()), AUTH_NONE);
        $this->assertEquals(auth_aclcheck('namespace:*',   '',array()), AUTH_NONE);

        // user with no matching group
        $this->assertEquals(auth_aclcheck('page',          'jill',array('foo')), AUTH_NONE);
        $this->assertEquals(auth_aclcheck('namespace:page','jill',array('foo')), AUTH_NONE);
        $this->assertEquals(auth_aclcheck('namespace:*',   'jill',array('foo')), AUTH_NONE);

        // user with matching group
        $this->assertEquals(auth_aclcheck('page',          'jill',array('foo','user')), AUTH_UPLOAD);
        $this->assertEquals(auth_aclcheck('namespace:page','jill',array('foo','user')), AUTH_UPLOAD);
        $this->assertEquals(auth_aclcheck('namespace:*',   'jill',array('foo','user')), AUTH_UPLOAD);

        // super user john
        $this->assertEquals(auth_aclcheck('page',          'john',array('foo')), AUTH_ADMIN);
        $this->assertEquals(auth_aclcheck('namespace:page','john',array('foo')), AUTH_ADMIN);
        $this->assertEquals(auth_aclcheck('namespace:*',   'john',array('foo')), AUTH_ADMIN);

        // super user doe
        $this->assertEquals(auth_aclcheck('page',          'doe',array('foo')), AUTH_ADMIN);
        $this->assertEquals(auth_aclcheck('namespace:page','doe',array('foo')), AUTH_ADMIN);
        $this->assertEquals(auth_aclcheck('namespace:*',   'doe',array('foo')), AUTH_ADMIN);

        // user with matching admin group
        $this->assertEquals(auth_aclcheck('page',          'jill',array('foo','admin')), AUTH_ADMIN);
        $this->assertEquals(auth_aclcheck('namespace:page','jill',array('foo','admin')), AUTH_ADMIN);
        $this->assertEquals(auth_aclcheck('namespace:*',   'jill',array('foo','admin')), AUTH_ADMIN);

        // user with matching another admin group
        $this->assertEquals(auth_aclcheck('page',          'jill',array('foo','roots')), AUTH_ADMIN);
        $this->assertEquals(auth_aclcheck('namespace:page','jill',array('foo','roots')), AUTH_ADMIN);
        $this->assertEquals(auth_aclcheck('namespace:*',   'jill',array('foo','roots')), AUTH_ADMIN);
    }

    function test_multiadmin_restricted_ropage(){
        global $conf;
        global $AUTH_ACL;
        $conf['superuser'] = 'john,@admin,doe,@roots';
        $conf['useacl']    = 1;

        $AUTH_ACL = array(
            '*                  @ALL           0',
            '*                  @user          8',
            'namespace:page     @user          1',
        );

        // anonymous user
        $this->assertEquals(auth_aclcheck('page',          '',array()), AUTH_NONE);
        $this->assertEquals(auth_aclcheck('namespace:page','',array()), AUTH_NONE);
        $this->assertEquals(auth_aclcheck('namespace:*',   '',array()), AUTH_NONE);

        // user with no matching group
        $this->assertEquals(auth_aclcheck('page',          'jill',array('foo')), AUTH_NONE);
        $this->assertEquals(auth_aclcheck('namespace:page','jill',array('foo')), AUTH_NONE);
        $this->assertEquals(auth_aclcheck('namespace:*',   'jill',array('foo')), AUTH_NONE);

        // user with matching group
        $this->assertEquals(auth_aclcheck('page',          'jill',array('foo','user')), AUTH_UPLOAD);
        $this->assertEquals(auth_aclcheck('namespace:page','jill',array('foo','user')), AUTH_READ);
        $this->assertEquals(auth_aclcheck('namespace:*',   'jill',array('foo','user')), AUTH_UPLOAD);

        // super user john
        $this->assertEquals(auth_aclcheck('page',          'john',array('foo')), AUTH_ADMIN);
        $this->assertEquals(auth_aclcheck('namespace:page','john',array('foo')), AUTH_ADMIN);
        $this->assertEquals(auth_aclcheck('namespace:*',   'john',array('foo')), AUTH_ADMIN);

        // super user doe
        $this->assertEquals(auth_aclcheck('page',          'doe',array('foo')), AUTH_ADMIN);
        $this->assertEquals(auth_aclcheck('namespace:page','doe',array('foo')), AUTH_ADMIN);
        $this->assertEquals(auth_aclcheck('namespace:*',   'doe',array('foo')), AUTH_ADMIN);

        // user with matching admin group
        $this->assertEquals(auth_aclcheck('page',          'jill',array('foo','admin')), AUTH_ADMIN);
        $this->assertEquals(auth_aclcheck('namespace:page','jill',array('foo','admin')), AUTH_ADMIN);
        $this->assertEquals(auth_aclcheck('namespace:*',   'jill',array('foo','admin')), AUTH_ADMIN);

        // user with matching another admin group
        $this->assertEquals(auth_aclcheck('page',          'jill',array('foo','roots')), AUTH_ADMIN);
        $this->assertEquals(auth_aclcheck('namespace:page','jill',array('foo','roots')), AUTH_ADMIN);
        $this->assertEquals(auth_aclcheck('namespace:*',   'jill',array('foo','roots')), AUTH_ADMIN);
    }

    function test_wildcards(){
        global $conf;
        global $AUTH_ACL;
        global $USERINFO;
        $conf['useacl']    = 1;

        $_SERVER['REMOTE_USER'] = 'john';
        $USERINFO['grps']       = array('test','töst','foo bar');
        $AUTH_ACL = auth_loadACL(); // default test file

        // default setting
        $this->assertEquals(AUTH_UPLOAD, auth_aclcheck('page', $_SERVER['REMOTE_USER'], $USERINFO['grps']));

        // user namespace
        $this->assertEquals(AUTH_DELETE, auth_aclcheck('users:john:foo', $_SERVER['REMOTE_USER'], $USERINFO['grps']));
        $this->assertEquals(AUTH_READ, auth_aclcheck('users:john:foo', 'schmock', array()));

        // group namespace
        $this->assertEquals(AUTH_DELETE, auth_aclcheck('groups:test:foo', $_SERVER['REMOTE_USER'], $USERINFO['grps']));
        $this->assertEquals(AUTH_READ, auth_aclcheck('groups:test:foo', 'schmock', array()));
        $this->assertEquals(AUTH_DELETE, auth_aclcheck('groups:toest:foo', $_SERVER['REMOTE_USER'], $USERINFO['grps']));
        $this->assertEquals(AUTH_READ, auth_aclcheck('groups:toest:foo', 'schmock', array()));
        $this->assertEquals(AUTH_DELETE, auth_aclcheck('groups:foo_bar:foo', $_SERVER['REMOTE_USER'], $USERINFO['grps']));
        $this->assertEquals(AUTH_READ, auth_aclcheck('groups:foo_bar:foo', 'schmock', array()));

    }

}

//Setup VIM: ex: et ts=4 :
