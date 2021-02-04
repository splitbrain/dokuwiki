<?php

use dokuwiki\test\mock\AuthCaseInsensitivePlugin;

class auth_acl_caseinsensitive_test extends DokuWikiTest {
    protected $oldAuth;
    protected $oldAuthAcl;

    function setUp() : void {
        parent::setUp();
        global $auth;
        global $AUTH_ACL;

        $this->oldAuth    = $auth;
        $this->oldAuthAcl = $AUTH_ACL;

        $auth = new AuthCaseInsensitivePlugin();
    }

    function tearDown() : void {
        global $conf;
        global $AUTH_ACL;
        global $auth;

        $auth     = $this->oldAuth;
        $AUTH_ACL = $this->oldAuthAcl;
    }

    function test_multiadmin_restricted_ropage() {
        global $conf;
        global $AUTH_ACL;

        $conf['superuser'] = 'John,doe,@Admin1,@admin2';
        $conf['useacl']    = 1;

        $AUTH_ACL = array(
            '*              @ALL       0',
            '*              @Group1    8',
            '*              @group2    8',
            'namespace:page @Group1    1',
            'namespace:page @group2    1',
        );

        // anonymous user
        $this->assertEquals(auth_aclcheck('page',           '', array()), AUTH_NONE);
        $this->assertEquals(auth_aclcheck('namespace:page', '', array()), AUTH_NONE);
        $this->assertEquals(auth_aclcheck('namespace:*',    '', array()), AUTH_NONE);

        // user with no matching group
        $this->assertEquals(auth_aclcheck('page',           'jill', array('foo')), AUTH_NONE);
        $this->assertEquals(auth_aclcheck('namespace:page', 'jill', array('foo')), AUTH_NONE);
        $this->assertEquals(auth_aclcheck('namespace:*',    'jill', array('foo')), AUTH_NONE);

        // user with matching group 1
        $this->assertEquals(auth_aclcheck('page',           'jill', array('foo', 'group1')), AUTH_UPLOAD);
        $this->assertEquals(auth_aclcheck('namespace:page', 'jill', array('foo', 'group1')), AUTH_READ);
        $this->assertEquals(auth_aclcheck('namespace:*',    'jill', array('foo', 'group1')), AUTH_UPLOAD);

        // user with matching group 2
        $this->assertEquals(auth_aclcheck('page',           'jill', array('foo', 'Group2')), AUTH_UPLOAD);
        $this->assertEquals(auth_aclcheck('namespace:page', 'jill', array('foo', 'Group2')), AUTH_READ);
        $this->assertEquals(auth_aclcheck('namespace:*',    'jill', array('foo', 'Group2')), AUTH_UPLOAD);

        // super user John
        $this->assertEquals(auth_aclcheck('page',           'john', array('foo')), AUTH_ADMIN);
        $this->assertEquals(auth_aclcheck('namespace:page', 'john', array('foo')), AUTH_ADMIN);
        $this->assertEquals(auth_aclcheck('namespace:*',    'john', array('foo')), AUTH_ADMIN);

        // super user doe
        $this->assertEquals(auth_aclcheck('page',           'Doe', array('foo')), AUTH_ADMIN);
        $this->assertEquals(auth_aclcheck('namespace:page', 'Doe', array('foo')), AUTH_ADMIN);
        $this->assertEquals(auth_aclcheck('namespace:*',    'Doe', array('foo')), AUTH_ADMIN);

        // user with matching admin group 1
        $this->assertEquals(auth_aclcheck('page',           'jill', array('foo', 'admin1')), AUTH_ADMIN);
        $this->assertEquals(auth_aclcheck('namespace:page', 'jill', array('foo', 'admin1')), AUTH_ADMIN);
        $this->assertEquals(auth_aclcheck('namespace:*',    'jill', array('foo', 'admin1')), AUTH_ADMIN);

        // user with matching admin group 2
        $this->assertEquals(auth_aclcheck('page',           'jill', array('foo', 'Admin2')), AUTH_ADMIN);
        $this->assertEquals(auth_aclcheck('namespace:page', 'jill', array('foo', 'Admin2')), AUTH_ADMIN);
        $this->assertEquals(auth_aclcheck('namespace:*',    'jill', array('foo', 'Admin2')), AUTH_ADMIN);
    }

    /*
     * Test aclcheck on @ALL group
     *
     * The default permission for @ALL group is AUTH_NONE. So we use an
     * ACL entry which grants @ALL group an AUTH_READ permission to see
     * whether ACL matching is properly done or not.
     */
    function test_restricted_allread() {
        global $conf;
        global $AUTH_ACL;

        $conf['superuser'] = 'john';
        $conf['useacl']    = 1;

        $AUTH_ACL = array(
            '*           @ALL           1',
            '*           @group1        8',
        );

        // anonymous user
        $this->assertEquals(auth_aclcheck('page',           '', array()), AUTH_READ);
        $this->assertEquals(auth_aclcheck('namespace:page', '', array()), AUTH_READ);
        $this->assertEquals(auth_aclcheck('namespace:*',    '', array()), AUTH_READ);

        // user with no matching group
        $this->assertEquals(auth_aclcheck('page',           'jill', array('foo')), AUTH_READ);
        $this->assertEquals(auth_aclcheck('namespace:page', 'jill', array('foo')), AUTH_READ);
        $this->assertEquals(auth_aclcheck('namespace:*',    'jill', array('foo')), AUTH_READ);

        // user with matching group
        $this->assertEquals(auth_aclcheck('page',           'jill', array('foo', 'Group1')), AUTH_UPLOAD);
        $this->assertEquals(auth_aclcheck('namespace:page', 'jill', array('foo', 'Group1')), AUTH_UPLOAD);
        $this->assertEquals(auth_aclcheck('namespace:*',    'jill', array('foo', 'Group1')), AUTH_UPLOAD);

        // super user
        $this->assertEquals(auth_aclcheck('page',           'John', array('foo')), AUTH_ADMIN);
        $this->assertEquals(auth_aclcheck('namespace:page', 'John', array('foo')), AUTH_ADMIN);
        $this->assertEquals(auth_aclcheck('namespace:*',    'John', array('foo')), AUTH_ADMIN);
    }
}
