<?php
/**
 * Test cases for the Doku_Indexer::renamePage and Doku_Indexer::renameMetaValue methods
 */
class indexer_rename_test extends DokuWikiTest {
    /** @var \dokuwiki\Search\Indexer $indexer */
    private $indexer;

    private $old_id = 'old_testid';

    function setUp() : void {
        parent::setUp();
        $this->indexer = idx_get_indexer();
        $this->indexer->clear();

        saveWikiText($this->old_id, 'Old test content', 'Created old test page for indexer rename test');
        idx_addPage($this->old_id);
    }

    function test_rename_to_new_page() {
        $newid = 'new_id_1';

        $oldpid = $this->indexer->getPID($this->old_id);

        $this->assertTrue($this->indexer->renamePage($this->old_id, $newid), 'Renaming the page to a new id failed');
        io_rename(wikiFN($this->old_id), wikiFN($newid));

        $this->assertNotEquals($this->indexer->getPID($this->old_id), $oldpid, 'PID for the old page unchanged after rename.');
        $this->assertEquals($this->indexer->getPID($newid), $oldpid, 'New page has not the old pid.');
        $query = array('old');
        $this->assertEquals(array('old' => array($newid => 1)), $this->indexer->lookup($query), '"Old" doesn\'t find the new page');
    }

    function test_rename_to_existing_page() {
        $newid = 'existing_page';
        saveWikiText($newid, 'Existing content', 'Created page for move_to_existing_page');
        idx_addPage($newid);

        $oldpid = $this->indexer->getPID($this->old_id);
        $existingpid = $this->indexer->getPID($newid);

        $this->assertTrue($this->indexer->renamePage($this->old_id, $newid), 'Renaming the page to an existing id failed');

        $this->assertNotEquals($this->indexer->getPID($this->old_id), $oldpid, 'PID for old page unchanged after rename.');
        $this->assertNotEquals($this->indexer->getPID($this->old_id), $existingpid, 'PID for old page is now PID of the existing page.');
        $this->assertEquals($this->indexer->getPID($newid), $oldpid, 'New page has not the old pid.');
        $query = array('existing');
        $this->assertEquals(array('existing' => array()), $this->indexer->lookup($query), 'Existing page hasn\'t been deleted from the index.');
        $query = array('old');
        $this->assertEquals(array('old' => array($newid => 1)), $this->indexer->lookup($query), '"Old" doesn\'t find the new page');
    }

    function test_meta_rename_to_new_value() {
        $this->indexer->addMetaKeys($this->old_id, array('mkey' => 'old_value'));

        $this->assertTrue($this->indexer->renameMetaValue('mkey', 'old_value', 'new_value'), 'Meta value rename to new value failed.');
        $query = 'old_value';
        $this->assertEquals(array(), $this->indexer->lookupKey('mkey', $query), 'Page can still be found under old value.');
        $query = 'new_value';
        $this->assertEquals(array($this->old_id), $this->indexer->lookupKey('mkey', $query), 'Page can\'t be found under new value.');
    }

    function test_meta_rename_to_existing_value() {
        $this->indexer->addMetaKeys($this->old_id, array('mkey' => array('old_value', 'new_value')));

        saveWikiText('newvalue', 'Test page', '');
        idx_addPage('newvalue');
        $this->indexer->addMetaKeys('newvalue', array('mkey' => array('new_value')));

        saveWikiText('oldvalue', 'Test page', '');
        idx_addPage('oldvalue');
        $this->indexer->addMetaKeys('oldvalue', array('mkey' => array('old_value')));

        $this->assertTrue($this->indexer->renameMetaValue('mkey', 'old_value', 'new_value'), 'Meta value rename to existing value failed');
        $query = 'old_value';
        $this->assertEquals(array(), $this->indexer->lookupKey('mkey', $query), 'Page can still be found under old value.');
        $query = 'new_value';
        $result = $this->indexer->lookupKey('mkey', $query);
        $this->assertContains($this->old_id, $result, 'Page with both values can\'t be found anymore');
        $this->assertContains('newvalue', $result, 'Page with new value can\'t be found anymore');
        $this->assertContains('oldvalue', $result, 'Page with only the old value can\'t be found anymore');
    }
}
