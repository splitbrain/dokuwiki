<?php

/**
 * @group integration
 */
class TestingDummyPluginIntegrationTest extends DokuWikiTest {

    function setUp() : void {
        $this->pluginsEnabled = array(
            'testing'
        );

        parent::setUp();
    }

    function testTestingPluginEnabled() {
        global $EVENT_HANDLER;

        $request = new TestRequest();
        $hookTriggered = false;

        $EVENT_HANDLER->register_hook('TESTING_PLUGIN_INSTALLED', 'AFTER', null,
            function() use (&$hookTriggered) {
                $hookTriggered = true;
            }
        );

        $request->execute();

        $this->assertTrue($hookTriggered, 'Testing plugin did not trigger!');
    }
}
