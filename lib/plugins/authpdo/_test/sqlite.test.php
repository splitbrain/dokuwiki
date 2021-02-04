<?php

/**
 * Class testable_auth_plugin_authpdo
 *
 * makes protected methods public for testing
 */
class testable_auth_plugin_authpdo extends auth_plugin_authpdo {
    public function getPluginName() {
        return 'authpdo';
    }

    public function selectGroups() {
        return parent::selectGroups();
    }

    public function addGroup($group) {
        return parent::addGroup($group);
    }
}

/**
 * General tests for the authpdo plugin
 *
 * @group plugin_authpdo
 * @group plugins
 */
class sqlite_plugin_authpdo_test extends DokuWikiTest {

    protected $dbfile;

    public function test_pdo_sqlite_support() {
        if(!class_exists('PDO') || !in_array('sqlite',PDO::getAvailableDrivers())) {
            $this->markTestSkipped('skipping all authpdo tests for sqlite.  Need PDO_sqlite extension');
        }
        $this->assertTrue(true); // avoid being marked as risky for having no assertion
    }

    public function setUp() : void {
        parent::setUp();
        $this->dbfile = tempnam('/tmp/', 'pluginpdo_test_');
        copy(__DIR__ . '/test.sqlite3', $this->dbfile);

        global $conf;

        $conf['plugin']['authpdo']['debug'] = 1;
        $conf['plugin']['authpdo']['dsn'] = 'sqlite:' . $this->dbfile;
        $conf['plugin']['authpdo']['user'] = '';
        $conf['plugin']['authpdo']['pass'] = '';

        $conf['plugin']['authpdo']['select-user'] = 'SELECT id AS uid, login AS user, name, pass AS clear, mail FROM user WHERE login = :user';
        $conf['plugin']['authpdo']['select-user-groups'] = 'SELECT * FROM member AS m, "group" AS g  WHERE m.gid = g.id AND  m.uid = :uid';
        $conf['plugin']['authpdo']['select-groups'] = 'SELECT id AS gid, "group" FROM "group"';

        $conf['plugin']['authpdo']['insert-user'] = 'INSERT INTO user (login, pass, name, mail) VALUES (:user, :hash, :name, :mail)';
        $conf['plugin']['authpdo']['delete-user'] = 'DELETE FROM user WHERE id = :uid';

        $conf['plugin']['authpdo']['list-users'] = 'SELECT DISTINCT login as user
                                                      FROM user U, member M, "group" G
                                                     WHERE U.id = M.uid
                                                       AND M.gid = G.id
                                                       AND G."group" LIKE :group
                                                       AND U.login LIKE :user
                                                       AND U.name LIKE :name
                                                       AND U.mail LIKE :mail
                                                  ORDER BY login
                                                     LIMIT :start,:limit';

        $conf['plugin']['authpdo']['count-users'] = 'SELECT COUNT(DISTINCT login) as count
                                                      FROM user U, member M, "group" G
                                                     WHERE U.id = M.uid
                                                       AND M.gid = G.id
                                                       AND G."group" LIKE :group
                                                       AND U.login LIKE :user
                                                       AND U.name LIKE :name
                                                       AND U.mail LIKE :mail';


        $conf['plugin']['authpdo']['update-user-login'] = 'UPDATE user SET login = :newlogin WHERE id = :uid';
        $conf['plugin']['authpdo']['update-user-info'] = 'UPDATE user SET name = :name, mail = :mail WHERE id = :uid';
        $conf['plugin']['authpdo']['update-user-pass'] = 'UPDATE user SET pass = :hash WHERE id = :uid';

        $conf['plugin']['authpdo']['insert-group'] = 'INSERT INTO "group" ("group") VALUES (:group)';
        $conf['plugin']['authpdo']['join-group'] = 'INSERT INTO member (uid, gid) VALUES (:uid, :gid)';
        $conf['plugin']['authpdo']['leave-group'] = 'DELETE FROM member WHERE uid = :uid AND gid = :gid';
    }

    public function tearDown() : void {
        parent::tearDown();
        unlink($this->dbfile);
    }

    /**
     * @depends test_pdo_sqlite_support
     */
    public function test_internals() {
        $auth = new testable_auth_plugin_authpdo();

        $groups = $auth->selectGroups();
        $this->assertArrayHasKey('user', $groups);
        $this->assertEquals(1, $groups['user']['gid']);
        $this->assertArrayHasKey('admin', $groups);
        $this->assertEquals(2, $groups['admin']['gid']);

        $ok = $auth->addGroup('test');
        $this->assertTrue($ok);
        $groups = $auth->selectGroups();
        $this->assertArrayHasKey('test', $groups);
        $this->assertEquals(3, $groups['test']['gid']);
    }

    /**
     * @depends test_pdo_sqlite_support
     */
    public function test_userinfo() {
        global $conf;
        $auth = new auth_plugin_authpdo();

        // clear text pasword (with default config above
        $this->assertFalse($auth->checkPass('nobody', 'nope'));
        $this->assertFalse($auth->checkPass('admin', 'nope'));
        $this->assertTrue($auth->checkPass('admin', 'password'));

        // now with a hashed password
        $conf['plugin']['authpdo']['select-user'] = 'SELECT id AS uid, login AS user, name, pass AS hash, mail FROM user WHERE login = :user';
        $this->assertFalse($auth->checkPass('admin', 'password'));
        $this->assertFalse($auth->checkPass('user', md5('password')));

        // access user data
        $info = $auth->getUserData('admin');
        $this->assertEquals('admin', $info['user']);
        $this->assertEquals('The Admin', $info['name']);
        $this->assertEquals('admin@example.com', $info['mail']);
        $this->assertEquals(array('admin', 'user'), $info['grps']);

        // group retrieval
        $this->assertEquals(array('admin', 'user'), $auth->retrieveGroups());
        $this->assertEquals(array('user'), $auth->retrieveGroups(1));
        $this->assertEquals(array('admin'), $auth->retrieveGroups(0, 1));

        // user creation
        $auth->createUser('test', 'password', 'A Test user', 'test@example.com', array('newgroup'));
        $info = $auth->getUserData('test');
        $this->assertEquals('test', $info['user']);
        $this->assertEquals('A Test user', $info['name']);
        $this->assertEquals('test@example.com', $info['mail']);
        $this->assertEquals(array('newgroup', 'user'), $info['grps']);
        $this->assertEquals(array('admin', 'newgroup', 'user'), $auth->retrieveGroups());

        // user modification
        $auth->modifyUser('test', array('user' => 'tester', 'name' => 'The Test User', 'pass' => 'secret'));
        $info = $auth->getUserData('tester');
        $this->assertEquals('tester', $info['user']);
        $this->assertEquals('The Test User', $info['name']);
        $this->assertTrue($auth->checkPass('tester','secret'));

        // move user to different groups
        $auth->modifyUser('tester', array('grps' => array('user', 'admin', 'another')));
        $info = $auth->getUserData('tester');
        $this->assertEquals(array('admin', 'another', 'user'), $info['grps']);


        $expect = array(
            'admin' => array(
                'user' => 'admin',
                'name' => 'The Admin',
                'mail' => 'admin@example.com',
                'uid' => '1',
                'grps' => array('admin', 'user')
            ),
            'user' => array(
                'user' => 'user',
                'name' => 'A normal user',
                'mail' => 'user@example.com',
                'uid' => '2',
                'grps' => array('user')
            ),
            'tester' => array(
                'user' => 'tester',
                'name' => 'The Test User',
                'mail' => 'test@example.com',
                'uid' => '3',
                'grps' => array('admin', 'another', 'user')
            )
        );

        // list users
        $users = $auth->retrieveUsers();
        $this->assertEquals(array($expect['admin'], $expect['tester'], $expect['user']), $users);

        $users = $auth->retrieveUsers(1); // offset
        $this->assertEquals(array($expect['tester'], $expect['user']), $users);

        $users = $auth->retrieveUsers(1, 1); // offset + limit
        $this->assertEquals(array($expect['tester']), $users);

        $users = $auth->retrieveUsers(0, -1, array('group' => 'admin')); // full group
        $this->assertEquals(array($expect['admin'], $expect['tester']), $users);
        $count = $auth->getUserCount(array('grps' => 'admin'));
        $this->assertSame(2, $count);

        $users = $auth->retrieveUsers(0, -1, array('group' => 'dmi')); // substring
        $this->assertEquals(array($expect['admin'], $expect['tester']), $users);
        $count = $auth->getUserCount(array('grps' => 'dmi'));
        $this->assertSame(2, $count);

        $users = $auth->retrieveUsers(0, -1, array('user' => 'dmi')); // substring
        $this->assertEquals(array($expect['admin']), $users);
        $count = $auth->getUserCount(array('user' => 'dmi'));
        $this->assertSame(1, $count);

        // delete user
        $num = $auth->deleteUsers(array('tester', 'foobar'));
        $this->assertSame(1, $num);

    }

}
