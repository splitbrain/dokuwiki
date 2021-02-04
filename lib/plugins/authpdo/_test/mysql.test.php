<?php

/**
 * mysql tests for the authpdo plugin
 *
 * @group plugin_authpdo
 * @group plugins
 */
class mysql_plugin_authpdo_test extends DokuWikiTest {

    protected $driver = 'mysql';
    protected $host = '';
    protected $database = 'authpdo_testing';
    protected $user = '';
    protected $pass = '';
    protected $port = '';

    public function setUp() : void {
        parent::setUp();
        $configuration = DOKU_UNITTEST . "{$this->driver}.conf.php";
        if(!file_exists($configuration)) {
            return;
        }
        /** @var $conf array */
        include $configuration;
        $this->host = $conf['host'];
        $this->user = $conf['user'];
        $this->pass = $conf['pass'];
        $this->port = $conf['port'];
    }

    /**
     * try to remove the last set up database
     *
     * it might still be there if something went wrong
     */
    public function tearDown() : void {
        parent::tearDown();
        $this->dropDatabase();
    }

    /**
     * Check if database credentials and extensions exist
     */
    public function test_requirements() {
        if(!$this->host || !$this->user) {
            $this->markTestSkipped("Skipped {$this->driver} tests. Missing configuration");
        }
        if(!class_exists('PDO')) {
            $this->markTestSkipped("Skipped {$this->driver} tests. Missing PDO extension");
        }
        if(!in_array($this->driver, pdo_drivers())) {
            $this->markTestSkipped("Skipped {$this->driver} tests. Missing pdo_{$this->driver} extension");
        }
        $this->assertTrue(true); // avoid being marked as risky for having no assertion
    }

    /**
     * create the database for testing
     */
    protected function createDatabase() {
        $pdo = new PDO(
            "{$this->driver}:host={$this->host};port={$this->port}", $this->user, $this->pass,
            array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // we want exceptions, not error codes
            )
        );
        $pdo->exec("DROP DATABASE IF EXISTS {$this->database}");
        $pdo->exec("CREATE DATABASE {$this->database}");
        $pdo = null;
    }

    /**
     * remove the database
     */
    protected function dropDatabase() {
        $pdo = new PDO(
            "{$this->driver}:host={$this->host};port={$this->port}", $this->user, $this->pass,
            array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // we want exceptions, not error codes
            )
        );
        try {
            $pdo->exec("DROP DATABASE IF EXISTS {$this->database}");
        } catch (PDOException $e) {
            // ignore - sometimes this fails even though the database was deleted
        }
        $pdo = null;
    }

    /**
     * imports a database dump
     *
     * @param $file
     */
    protected function importDatabase($file) {
        // connect to database and import dump
        $pdo = null;
        $pdo = new PDO(
            "{$this->driver}:dbname={$this->database};host={$this->host};port={$this->port}", $this->user, $this->pass,
            array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // we want exceptions, not error codes
            )
        );
        $sql = file_get_contents($file);
        $pdo->exec($sql);
        $pdo = null;
    }

    /**
     * Run general tests on all users
     *
     * @param auth_plugin_authpdo $auth
     * @param array $users
     */
    protected function runGeneralTests(auth_plugin_authpdo $auth, $users) {
        global $conf;
        $info = 'DSN: ' . $auth->getConf('dsn');
        $this->assertTrue($auth->success, $info);

        if($auth->canDo('getUsers')) {
            $list = $auth->retrieveUsers();
            $this->assertGreaterThanOrEqual(count($users), count($list), $info);
        }

        if($auth->canDo('getGroups')) {
            $list = $auth->retrieveGroups();
            $this->assertGreaterThanOrEqual(1, $list, $info);
        }

        if($auth->canDo('getUserCount')) {
            $count = $auth->getUserCount();
            $this->assertGreaterThanOrEqual(count($users), $count);
        }

        if($auth->canDo('addUser')) {
            $newuser = array(
                'user' => 'newuserfoobar',
                'name' => 'First LastFoobar',
                'pass' => 'password',
                'mail' => 'newuserfoobar@example.com',
                'grps' => array('acompletelynewgroup')
            );
            $ok = $auth->createUser(
                $newuser['user'],
                $newuser['pass'],
                $newuser['name'],
                $newuser['mail'],
                $newuser['grps']
            );
            $this->assertTrue($ok, $info);
            $check = $auth->getUserData($newuser['user']);
            $this->assertEquals($newuser['user'], $check['user'], $info);
            $this->assertEquals($newuser['mail'], $check['mail'], $info);
            $groups = array_merge($newuser['grps'], array($conf['defaultgroup']));
            $this->assertEquals($groups, $check['grps'], $info);
        }
    }

    /**
     * run all the tests with the given user, depending on the capabilities
     *
     * @param auth_plugin_authpdo $auth
     * @param $user
     */
    protected function runUserTests(auth_plugin_authpdo $auth, $user) {
        global $conf;
        $info = 'DSN: ' . $auth->getConf('dsn') . ' User:' . $user['user'];

        // minimal setup
        $this->assertTrue($auth->checkPass($user['user'], $user['pass']), $info);
        $check = $auth->getUserData($user['user']);
        $this->assertEquals($user['user'], $check['user'], $info);
        $this->assertEquals($user['name'], $check['name'], $info);
        $this->assertEquals($user['mail'], $check['mail'], $info);
        $groups = array_merge($user['grps'], array($conf['defaultgroup']));
        $this->assertEquals($groups, $check['grps'], $info);

        // getUsers
        if($auth->canDo('getUsers')) {
            $list = $auth->retrieveUsers(0, -1, array('user' => $user['user']));
            $this->assertGreaterThanOrEqual(1, count($list));
            $list = $auth->retrieveUsers(0, -1, array('name' => $user['name']));
            $this->assertGreaterThanOrEqual(1, count($list));
            $list = $auth->retrieveUsers(0, -1, array('mail' => $user['mail']));
            $this->assertGreaterThanOrEqual(1, count($list));
        }

        // getUserCount
        if($auth->canDo('getUserCount')) {
            $count = $auth->getUserCount(array('user' => $user['user']));
            $this->assertGreaterThanOrEqual(1, $count);
            $count = $auth->getUserCount(array('name' => $user['name']));
            $this->assertGreaterThanOrEqual(1, $count);
            $count = $auth->getUserCount(array('mail' => $user['mail']));
            $this->assertGreaterThanOrEqual(1, $count);
        }

        // modGroups
        if($auth->canDo('modGroups')) {
            $newgroup = 'foobar';
            $ok = $auth->modifyUser($user['user'], array('grps' => array($newgroup)));
            $this->assertTrue($ok, $info);
            $check = $auth->getUserData($user['user']);
            $this->assertTrue(in_array($newgroup, $check['grps']), $info);
        }

        // modPass
        if($auth->canDo('modPass')) {
            $newpass = 'foobar';
            $ok = $auth->modifyUser($user['user'], array('pass' => $newpass));
            $this->assertTrue($ok, $info);
            $this->assertTrue($auth->checkPass($user['user'], $newpass), $info);
        }

        // modMail
        if($auth->canDo('modMail')) {
            $newmail = 'foobar@example.com';
            $ok = $auth->modifyUser($user['user'], array('mail' => $newmail));
            $this->assertTrue($ok, $info);
            $check = $auth->getUserData($user['user']);
            $this->assertEquals($newmail, $check['mail'], $info);
        }

        // modName
        if($auth->canDo('modName')) {
            $newname = 'FirstName Foobar';
            $ok = $auth->modifyUser($user['user'], array('name' => $newname));
            $this->assertTrue($ok, $info);
            $check = $auth->getUserData($user['user']);
            $this->assertEquals($newname, $check['name'], $info);
        }

        // modLogin
        if($auth->canDo('modLogin')) {
            $newuser = 'foobar' . $user['user'];
            $ok = $auth->modifyUser($user['user'], array('user' => $newuser));
            $this->assertTrue($ok, $info);
            $check = $auth->getUserData($newuser);
            $this->assertEquals($newuser, $check['user'], $info);
            // rename back
            $ok = $auth->modifyUser($newuser, array('user' => $user['user']));
            $this->assertTrue($ok, $info);
        }

        // delUser
        if($auth->canDo('delUser')) {
            $num = $auth->deleteUsers(array($user['user']));
            $this->assertEquals(1, $num, $info);
            $this->assertFalse($auth->getUserData($user['user']), $info);
        }
    }

    /**
     * prepares the individual configurations for testing
     *
     * @return array
     */
    public function data_provider() {
        $testdata = array();

        $files = glob(__DIR__ . "/{$this->driver}/*.php");
        foreach($files as $file) {
            $dump = preg_replace('/\.php$/', '.sql', $file);
            $dbname = 'authpdo_testing_' . basename($file, '.php');

            /** @var $data array */
            include $file;

            $testdata[] = array($dbname, $dump, $data);
        }

        return $testdata;
    }

    /**
     * This triggers all the tests based on the dumps and configurations
     *
     * @dataProvider data_provider
     * @depends      test_requirements
     * @param string $dbname Name of the database to use
     * @param string $dump The path to the dump file to import
     * @param array|string $data config and test user setup. When a string is passed, test is skipped with that msg
     */
    public function test_database($dbname, $dump, $data){
        global $conf;

        if(!is_array($data)) {
            $this->markTestSkipped($data);
            return;
        }

        $this->database = $dbname;

        $this->createDatabase();
        $this->importDatabase($dump);

        // Setup the configuration and initialize a new auth object
        $conf['plugin']['authpdo'] = array();
        $conf['plugin']['authpdo'] = $data['conf'];
        $conf['plugin']['authpdo']['dsn'] = "{$this->driver}:dbname={$this->database};host={$this->host};port={$this->port}";
        $conf['plugin']['authpdo']['user'] = $this->user;
        $conf['plugin']['authpdo']['pass'] = $this->pass;
        $conf['plugin']['authpdo']['debug'] = 1;
        if($data['passcrypt']) $conf['passcrypt'] = $data['passcrypt'];
        $auth = new auth_plugin_authpdo();

        $this->runGeneralTests($auth, $data['users']);
        foreach($data['users'] as $user) {
            $this->runUserTests($auth, $user);
        }

        $this->dropDatabase();
    }

}
