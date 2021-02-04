<?php

/**
 * @group plugin_usermanager
 * @group admin_plugins
 * @group plugins
 * @group bundled_plugins
 */
require_once(dirname(__FILE__).'/mocks.class.php');

class plugin_usermanager_csv_export_test extends DokuWikiTest {

    protected  $usermanager;

    function setUp() : void {
        $this->usermanager = new admin_mock_usermanager();
        parent::setUp();
    }

    /**
     * based on standard test user/conf setup
     *
     * users per _test/conf/users.auth.php
     * expected to be: testuser:179ad45c6ce2cb97cf1029e212046e81:Arthur Dent:arthur@example.com
     */
    function test_export() {
        $expected = 'User,"Real Name",Email,Groups
testuser,"Arthur Dent",arthur@example.com,
';
        $this->assertEquals($expected, $this->usermanager->tryExport());
    }

    /**
     * when configured to use a different locale, the column headings in the first line of the
     * exported csv data should reflect the langauge strings of that locale
     */
    function test_export_withlocale(){
        global $conf;
        $old_conf = $conf;
        $conf['lang'] = 'de';

        $this->usermanager->localised = false;
        $this->usermanager->setupLocale();

        $conf = $old_conf;

        $expected = 'Benutzername,"Voller Name",E-Mail,Gruppen
testuser,"Arthur Dent",arthur@example.com,
';
        $this->assertEquals($expected, $this->usermanager->tryExport());
    }
/*
    function test_export_withfilter(){
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }
*/
}
