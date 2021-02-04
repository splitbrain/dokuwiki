<?php

use dokuwiki\test\mock\AuthCaseInsensitivePlugin;
use dokuwiki\test\mock\AuthPlugin;

class auth_admin_test extends DokuWikiTest
{

    private $oldauth;

    function setUp() : void
    {
        parent::setUp();
        global $auth;
        $this->oldauth = $auth;
    }

    function setSensitive()
    {
        global $auth;
        $auth = new AuthPlugin();
    }

    function setInSensitive()
    {
        global $auth;
        $auth = new AuthCaseInsensitivePlugin();
    }

    function teardown() : void
    {
        global $auth;
        global $AUTH_ACL;
        unset($AUTH_ACL);
        $auth = $this->oldauth;
    }

    function test_ismanager_insensitive()
    {
        $this->setInSensitive();
        global $conf;
        $conf['superuser'] = 'john,@admin,@Mötly Görls, Dörte';
        $conf['manager'] = 'john,@managers,doe, @Mötly Böys, Dänny';

        // anonymous user
        $this->assertFalse(auth_ismanager('jill', null, false, true));

        // admin or manager users
        $this->assertTrue(auth_ismanager('john', null, false, true));
        $this->assertTrue(auth_ismanager('doe', null, false, true));

        $this->assertTrue(auth_ismanager('dörte', null, false, true));
        $this->assertTrue(auth_ismanager('dänny', null, false, true));

        // admin or manager groups
        $this->assertTrue(auth_ismanager('jill', array('admin'), false, true));
        $this->assertTrue(auth_ismanager('jill', array('managers'), false, true));

        $this->assertTrue(auth_ismanager('jill', array('mötly görls'), false, true));
        $this->assertTrue(auth_ismanager('jill', array('mötly böys'), false, true));
    }

    function test_isadmin_insensitive()
    {
        $this->setInSensitive();
        global $conf;
        $conf['superuser'] = 'john,@admin,doe,@roots';

        // anonymous user
        $this->assertFalse(auth_ismanager('jill', null, true, true));

        // admin user
        $this->assertTrue(auth_ismanager('john', null, true, true));
        $this->assertTrue(auth_ismanager('doe', null, true, true));

        // admin groups
        $this->assertTrue(auth_ismanager('jill', array('admin'), true, true));
        $this->assertTrue(auth_ismanager('jill', array('roots'), true, true));
        $this->assertTrue(auth_ismanager('john', array('admin'), true, true));
        $this->assertTrue(auth_ismanager('doe', array('admin'), true, true));
    }

    function test_ismanager_sensitive()
    {
        $this->setSensitive();
        global $conf;
        $conf['superuser'] = 'john,@admin,@Mötly Görls, Dörte';
        $conf['manager'] = 'john,@managers,doe, @Mötly Böys, Dänny';

        // anonymous user
        $this->assertFalse(auth_ismanager('jill', null, false, true));

        // admin or manager users
        $this->assertTrue(auth_ismanager('john', null, false, true));
        $this->assertTrue(auth_ismanager('doe', null, false, true));

        $this->assertFalse(auth_ismanager('dörte', null, false, true));
        $this->assertFalse(auth_ismanager('dänny', null, false, true));

        // admin or manager groups
        $this->assertTrue(auth_ismanager('jill', array('admin'), false, true));
        $this->assertTrue(auth_ismanager('jill', array('managers'), false, true));

        $this->assertFalse(auth_ismanager('jill', array('mötly görls'), false, true));
        $this->assertFalse(auth_ismanager('jill', array('mötly böys'), false, true));
    }

    function test_isadmin_sensitive()
    {
        $this->setSensitive();
        global $conf;
        $conf['superuser'] = 'john,@admin,doe,@roots';

        // anonymous user
        $this->assertFalse(auth_ismanager('jill', null, true, true));

        // admin user
        $this->assertTrue(auth_ismanager('john', null, true, true));
        $this->assertFalse(auth_ismanager('Doe', null, true, true));

        // admin groups
        $this->assertTrue(auth_ismanager('jill', array('admin'), true, true));
        $this->assertTrue(auth_ismanager('jill', array('roots'), true, true));
        $this->assertTrue(auth_ismanager('john', array('admin'), true, true));
        $this->assertTrue(auth_ismanager('doe', array('admin'), true, true));
        $this->assertTrue(auth_ismanager('Doe', array('admin'), true, true));
    }

}
