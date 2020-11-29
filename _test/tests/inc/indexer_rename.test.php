<?php

use dokuwiki\Search\Indexer;
use dokuwiki\Search\FulltextIndex;
use dokuwiki\Search\MetadataIndex;

/**
 * Test cases for the Indexer::renamePage and MetadataIndex::renameMetaValue methods
 */
class indexer_rename_test extends DokuWikiTest
{
    private $old_id = 'old_testid';

    public function setUp()
    {
        parent::setUp();
        (new Indexer)->clear();

        saveWikiText($this->old_id, 'Old test content', 'Created old test page for indexer rename test');
        (new Indexer($this->old_id))->addPage();
    }

    public function test_rename_to_new_page()
    {
        $Indexer = new Indexer();

        $newid = 'new_id_1';

        $oldpid = $Indexer->getPID($this->old_id);

        $this->assertTrue($Indexer->renamePage($this->old_id, $newid), 'Renaming the page to a new id failed');
        io_rename(wikiFN($this->old_id), wikiFN($newid));

        $this->assertNotEquals($Indexer->getPID($this->old_id), $oldpid, 'PID for the old page unchanged after rename.');
        $this->assertEquals($Indexer->getPID($newid), $oldpid, 'New page has not the old pid.');
        $query = array('old');
        $this->assertEquals(array('old' => array($newid => 1)), (new FulltextIndex())->lookupWords($query), '"Old" doesn\'t find the new page');
    }

    public function test_rename_to_existing_page()
    {
        $Indexer = new Indexer();

        $newid = 'existing_page';
        saveWikiText($newid, 'Existing content', 'Created page for move_to_existing_page');
        (new Indexer($newid))->addPage();

        $oldpid = $Indexer->getPID($this->old_id);
        $existingpid = $Indexer->getPID($newid);

        $this->assertTrue($Indexer->renamePage($this->old_id, $newid), 'Renaming the page to an existing id failed');

        $this->assertNotEquals($Indexer->getPID($this->old_id), $oldpid, 'PID for old page unchanged after rename.');
        $this->assertNotEquals($Indexer->getPID($this->old_id), $existingpid, 'PID for old page is now PID of the existing page.');
        $this->assertEquals($Indexer->getPID($newid), $oldpid, 'New page has not the old pid.');
        $query = array('existing');
        $this->assertEquals(array('existing' => array()), (new FulltextIndex())->lookupWords($query), 'Existing page hasn\'t been deleted from the index.');
        $query = array('old');
        $this->assertEquals(array('old' => array($newid => 1)), (new FulltextIndex())->lookupWords($query), '"Old" doesn\'t find the new page');
    }

    public function test_meta_rename_to_new_value()
    {
        $MetadataIndex = new MetadataIndex();

        (new MetadataIndex($this->old_id))->addMetaKeys(array('mkey' => 'old_value'));

        $this->assertTrue($MetadataIndex->renameMetaValue('mkey', 'old_value', 'new_value'), 'Meta value rename to new value failed.');
        $query = 'old_value';
        $this->assertEquals(array(), $MetadataIndex->lookupKey('mkey', $query), 'Page can still be found under old value.');
        $query = 'new_value';
        $this->assertEquals(array($this->old_id), $MetadataIndex->lookupKey('mkey', $query), 'Page can\'t be found under new value.');
    }

    public function test_meta_rename_to_existing_value()
    {
        $MetadataIndex = new MetadataIndex();

        (new MetadataIndex($this->old_id))->addMetaKeys(array('mkey' => array('old_value', 'new_value')));

        saveWikiText('newvalue', 'Test page', '');
        (new Indexer('newvalue'))->addPage();
        (new MetadataIndex('newvalue'))->addMetaKeys(array('mkey' => array('new_value')));

        saveWikiText('oldvalue', 'Test page', '');
        (new Indexer('oldvalue'))->addPage();
        (new MetadataIndex('oldvalue'))->addMetaKeys(array('mkey' => array('old_value')));

        $this->assertTrue($MetadataIndex->renameMetaValue('mkey', 'old_value', 'new_value'), 'Meta value rename to existing value failed');
        $query = 'old_value';
        $this->assertEquals(array(), $MetadataIndex->lookupKey('mkey', $query), 'Page can still be found under old value.');
        $query = 'new_value';
        $result = $MetadataIndex->lookupKey('mkey', $query);
        $this->assertContains($this->old_id, $result, 'Page with both values can\'t be found anymore');
        $this->assertContains('newvalue', $result, 'Page with new value can\'t be found anymore');
        $this->assertContains('oldvalue', $result, 'Page with only the old value can\'t be found anymore');
    }
}
