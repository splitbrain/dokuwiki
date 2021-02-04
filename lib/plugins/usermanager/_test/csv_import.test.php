<?php

/**
 * @group plugin_usermanager
 * @group admin_plugins
 * @group plugins
 * @group bundled_plugins
 */

require_once(dirname(__FILE__).'/mocks.class.php');

/**
 *  !!!!! NOTE !!!!!
 *
 *  At present, users imported in individual tests remain in the user list for subsequent tests
 */
class plugin_usermanager_csv_import_test extends DokuWikiTest
{
    private $old_files;
    protected $usermanager;
    protected $importfile;

    public function setUp() : void
    {
        $this->importfile = tempnam(TMP_DIR, 'csv');

        $this->old_files = $_FILES;
        $_FILES = array(
            'import'    =>  array(
                'name'      =>  'import.csv',
                'tmp_name'  =>  $this->importfile,
                'type'      =>  'text/plain',
                'size'      =>  1,
                'error'     =>  0,
            ),
        );

        $this->usermanager = new admin_mock_usermanager();
        parent::setUp();
    }

    public function tearDown() : void
    {
        $_FILES = $this->old_files;
        parent::tearDown();
    }

    public function doImportTest($importCsv, $expectedResult, $expectedNewUsers, $expectedFailures)
    {
        global $auth;
        $before_users = $auth->retrieveUsers();

        io_saveFile($this->importfile, $importCsv);
        $result = $this->usermanager->tryImport();

        $after_users = $auth->retrieveUsers();

        $before_users = array_map('serialize', $before_users);
        $after_users = array_map('serialize', $after_users);
        $import_count = count($after_users) - count($before_users);
        $new_users = array_map('unserialize', array_diff_key($after_users, $before_users));
        $diff_users = array_map('unserialize', array_diff_assoc($after_users, $before_users));

        $expectedCount = count($expectedNewUsers);

        $this->assertEquals($expectedResult, $result);                                       // import result as expected
        $this->assertEquals($expectedCount, $import_count);                                  // number of new users matches expected number imported
        $this->assertEquals($expectedNewUsers, $this->stripPasswords($new_users));           // new user data matches imported user data
        $this->assertEquals($expectedCount, $this->countPasswords($new_users));              // new users have a password
        $this->assertEquals($expectedCount, $this->usermanager->mock_email_notifications_sent);   // new users notified of their passwords
        $this->assertEquals($new_users, $diff_users);                                        // no other users were harmed in the testing of this import
        $this->assertEquals($expectedFailures, $this->usermanager->getImportFailures());     // failures as expected
    }

    public function test_cantImport()
    {
        global $auth;
        $oldauth = $auth;

        $auth = new auth_mock_authplain();
        $auth->setCanDo('addUser', false);

        $csv = 'User,"Real Name",Email,Groups
importuser,"Ford Prefect",ford@example.com,user
';

        $this->doImportTest($csv, false, array(), array());

        $auth = $oldauth;
    }

    public function test_import()
    {
        $csv = 'User,"Real Name",Email,Groups
importuser,"Ford Prefect",ford@example.com,user
';
        $expected = array(
            'importuser' => array(
                'name'  => 'Ford Prefect',
                'mail'  => 'ford@example.com',
                'grps'  => array('user'),
            ),
        );

        $this->doImportTest($csv, true, $expected, array());
    }

    public function test_importExisting()
    {
        $csv = 'User,"Real Name",Email,Groups
importuser,"Ford Prefect",ford@example.com,user
';
        $failures = array(
            '2' => array(
                'error' => $this->usermanager->getLang('import_error_create'),
                'user'  => array(
                    'importuser',
                    'Ford Prefect',
                    'ford@example.com',
                    'user',
                ),
                'orig'   => 'importuser,"Ford Prefect",ford@example.com,user'.NL,
            ),
        );

        $this->doImportTest($csv, true, array(), $failures);
    }

    public function test_importUtf8()
    {
        $csv = 'User,"Real Name",Email,Groups
importutf8,"Førd Prefect",ford@example.com,user
';
        $expected = array(
            'importutf8' => array(
                'name'  => 'Førd Prefect',
                'mail'  => 'ford@example.com',
                'grps'  => array('user'),
            ),
        );

        $this->doImportTest($csv, true, $expected, array());
    }

    /**
     *  utf8: u+00F8 (ø) <=> 0xF8 :iso-8859-1
     */
    public function test_importIso8859()
    {
        $csv = 'User,"Real Name",Email,Groups
importiso8859,"F'.chr(0xF8).'rd Prefect",ford@example.com,user
';
        $expected = array(
            'importiso8859' => array(
                'name'  => 'Førd Prefect',
                'mail'  => 'ford@example.com',
                'grps'  => array('user'),
            ),
        );

        $this->doImportTest($csv, true, $expected, array());
    }

    private function stripPasswords($array)
    {
        foreach ($array as $user => $data) {
            unset($array[$user]['pass']);
        }
        return $array;
    }

    private function countPasswords($array)
    {
        $count = 0;
        foreach ($array as $user => $data) {
            if (!empty($data['pass'])) {
                $count++;
            }
        }
        return $count;
    }
}
