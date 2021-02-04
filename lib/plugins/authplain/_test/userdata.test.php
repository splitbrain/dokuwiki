<?php

/**
 * Class userdata_test
 *
 * Test group retrieval
 *
 * @group plugins
 */
class userdata_test extends DokuWikiTest
{
    /** @var  auth_plugin_authplain */
    protected $auth;

    /**
     * Load auth with test conf
     * @throws Exception
     */
    public function setUp() : void
    {
        parent::setUp();
        global $config_cascade;
        $config_cascade['plainauth.users']['default'] = __DIR__ . '/conf/auth.users.php';
        $this->auth = new auth_plugin_authplain();
    }

    /**
     * Test that all groups are retrieved in the correct order, without duplicates
     */
    public function test_retrieve_groups()
    {
        $expected = ['user', 'first_group', 'second_group', 'third_group', 'fourth_group', 'fifth_group'];
        $actual = $this->auth->retrieveGroups();
        $this->assertEquals($expected, $actual);
    }

    /**
     * Test with small and large limits
     */
    public function test_retrieve_groups_limit()
    {
        $expected = ['user', 'first_group'];
        $actual = $this->auth->retrieveGroups(0, 2);
        $this->assertEquals($expected, $actual);

        $expected = ['user', 'first_group', 'second_group', 'third_group', 'fourth_group', 'fifth_group'];
        $actual = $this->auth->retrieveGroups(0, 20);
        $this->assertEquals($expected, $actual);
    }

    /**
     * Test with small and large offsets
     */
    public function test_retrieve_groups_offset()
    {
        $expected = ['third_group', 'fourth_group', 'fifth_group'];
        $actual = $this->auth->retrieveGroups(3,10);
        $this->assertEquals($expected, $actual);

        $expected = [];
        $actual = $this->auth->retrieveGroups(10,3);
        $this->assertEquals($expected, $actual);
    }
}
