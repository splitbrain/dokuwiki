<?php

use dokuwiki\Search\MetadataIndex;

/**
 * Tests the histogram function of the indexer.
 *
 * @author Michael Hamann <michael@content-space.de>
 */
class indexer_histogram_test extends DokuWikiTest
{
    public function test_minlength()
    {
        $MetadataIndex = new MetadataIndex();
        (new MetadataIndex('histo1'))->addMetaKeys('testkey', array('foo', 'bar', 'foobar'));
        (new MetadataIndex('histo2'))->addMetaKeys('testkey', array('bar', 'testing'));
        (new MetadataIndex('histo3'))->addMetaKeys('testkey', array('foo', 'foobar'));

        $histogram4 = $MetadataIndex->histogram(1, 0, 4, 'testkey');
        $this->assertEquals(array('foobar' => 2, 'testing' => 1), $histogram4);
        $histogram2 = $MetadataIndex->histogram(1, 0, 2, 'testkey');
        $this->assertEquals(array('foobar' => 2, 'testing' => 1, 'foo' => 2, 'bar' => 2), $histogram2);
    }
}
