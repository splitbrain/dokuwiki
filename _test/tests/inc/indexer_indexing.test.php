<?php

use dokuwiki\Search\Indexer;
use dokuwiki\Search\FulltextIndex;
use dokuwiki\Search\MetadataIndex;

/**
 * Tests the indexing functionality of the indexer
 *
 * @author Michael Hamann <michael@content-space.de>
 */
class indexer_indexing_test extends DokuWikiTest
{
    public function setUp()
    {
        parent::setUp();
        saveWikiText('testpage', 'Foo bar baz.', 'Test initialization');
        saveWikiText('notfound', 'Foon barn bazn.', 'Test initialization');
        (new Indexer('testpage'))->addPage();
        (new Indexer('notfound'))->addPage();
    }

    public function test_words()
    {
        $FulltextIndex = new FulltextIndex();
        $query = array('baz', 'foo');
        $this->assertEquals(array('baz' => array('testpage' => 1), 'foo' => array('testpage' => 1)), $FulltextIndex->lookupWords($query));
    }

    public function test_numerically_identical_words()
    {
        $FulltextIndex = new FulltextIndex();
        (new FulltextIndex('testpage'))->addWords('0x1 002');
        (new FulltextIndex('notfound'))->addWords('0x2');
        $query = array('001', '002');
        $this->assertEquals(array('001' => array(), '002' => array('testpage' => 1)), $FulltextIndex->lookupWords($query));
    }

    public function test_meta()
    {
        $MetadataIndex = new MetadataIndex();
        (new MetadataIndex('testpage'))->addMetaKeys('testkey', 'testvalue');
        (new MetadataIndex('notfound'))->addMetaKeys('testkey', 'notvalue');
        $query = 'testvalue';
        $this->assertEquals(array('testpage'), $MetadataIndex->lookupKey('testkey', $query));
    }

    public function test_numerically_identical_meta_values()
    {
        $MetadataIndex = new MetadataIndex();
        (new MetadataIndex('testpage'))->addMetaKeys('numkey', array('0001', '01'));
        (new MetadataIndex('notfound'))->addMetaKeys('numkey', array('00001', '000001'));
        $query = array('001', '01');
        $this->assertEquals(array('001' => array(), '01' => array('testpage')), $MetadataIndex->lookupKey('numkey', $query));
    }

    public function test_numeric_twice()
    {
        $FulltextIndex = new FulltextIndex();
        (new FulltextIndex('testpage'))->addWords('| 1010 | Dallas |');
        $query = array('1010');
        $this->assertEquals(array('1010' => array('testpage' => 1)), $FulltextIndex->lookupWords($query));
        (new FulltextIndex('notfound'))->addWords('| 1010 | Dallas |');
        $this->assertEquals(array('1010' => array('testpage' => 1, 'notfound' => 1)), $FulltextIndex->lookupWords($query));
    }

    public function test_numeric_twice_meta()
    {
        $MetadataIndex = new MetadataIndex();
        (new MetadataIndex('testpage'))->addMetaKeys('onezero', array('1010'));
        (new MetadataIndex('notfound'))->addMetaKeys('onezero', array('1010'));
        $query = '1010';
        $this->assertEquals(array('notfound', 'testpage'), $MetadataIndex->lookupKey('onezero', $query));
    }

    public function test_numeric_zerostring_meta()
    {
        $MetadataIndex = new MetadataIndex();
        (new MetadataIndex('zero1'))->addMetaKeys('zerostring', array('0'));
        (new MetadataIndex('zero2'))->addMetaKeys('zerostring', array('0'));
        (new MetadataIndex('0'))->addMetaKeys('zerostring', array('zero'));

        $query = '0';
        $result = $MetadataIndex->lookupKey('zerostring', $query);
        sort($result);
        $this->assertEquals(array('zero1', 'zero2'), $result);

        $query = 'zero';
        $result = $MetadataIndex->lookupKey('zerostring', $query);
        sort($result);
        $this->assertEquals(array('0'), $result);
    }
}
