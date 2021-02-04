<?php

use dokuwiki\ChangeLog\PageChangeLog;

/**
 * Tests for requesting revisions of a page with getRevisions()
 *
 * This class uses the files:
 * - data/pages/mailinglist.txt
 * - data/meta/mailinglist.changes
 */
class changelog_getrevisions_test extends DokuWikiTest {

    /**
     * $first counts inclusive zero, after the current page
     */
    private $revsexpected = array(
        1374261194, //current page
        1371579614, 1368622240, // revisions, corresponds to respectively $first = 0 and 1
        1368622195, 1368622152,
        1368612599, 1368612506,
        1368609772, 1368575634,
        1363436892, 1362527164,
        1362527046, 1362526861, //10 and 11
        1362526767, 1362526167,
        1362526119, 1362526039,
        1362525926, 1362525899,
        1362525359, 1362525145,
        1362524799, 1361901536, //20 and 21
        1360110636
    );
    private $pageid = 'mailinglist';

    function setup() : void {
        parent::setup();
        global $cache_revinfo;
        $cache =& $cache_revinfo;
        if(isset($cache['nonexist'])) {
            unset($cache['nonexist']);
        }
        if(isset($cache['mailinglist'])) {
            unset($cache['mailinglist']);
        }
    }

    /**
     * no nonexist.changes meta file available
     */
    function test_changemetadatanotexists() {
        $first = 0;
        $num = 1;
        $id = 'nonexist';
        $revsexpected = array();

        $pagelog = new PageChangeLog($id, $chunk_size = 8192);
        $revs = $pagelog->getRevisions($first, $num);
        $this->assertEquals($revsexpected, $revs);
    }

    /**
     * request first recentest revision
     * (so skips first line which belongs to the current existing page)
     */
    function test_requestlastrev() {
        $first = 0;
        $num = 1;
        $revsexpected = array($this->revsexpected[1]);

        $pagelog = new PageChangeLog($this->pageid, $chunk_size = 8192);
        $revs = $pagelog->getRevisions($first, $num);
        $this->assertEquals($revsexpected, $revs);

        $pagelog = new PageChangeLog($this->pageid, $chunk_size = 512);
        $revs = $pagelog->getRevisions($first, $num);
        $this->assertEquals($revsexpected, $revs);

        $pagelog = new PageChangeLog($this->pageid, $chunk_size = 20);
        $revs = $pagelog->getRevisions($first, $num);
        $this->assertEquals($revsexpected, $revs);
    }

    /**
     * request first recentest revision
     * (so skips first line which belongs to the current existing page)
     */
    function test_requestonebutlastrev() {
        $first = 1;
        $num = 1;
        $revsexpected = array($this->revsexpected[2]);

        $pagelog = new PageChangeLog($this->pageid, $chunk_size = 8192);
        $revs = $pagelog->getRevisions($first, $num);
        $this->assertEquals($revsexpected, $revs);

        $pagelog = new PageChangeLog($this->pageid, $chunk_size = 512);
        $revs = $pagelog->getRevisions($first, $num);
        $this->assertEquals($revsexpected, $revs);

        $pagelog = new PageChangeLog($this->pageid, $chunk_size = 20);
        $revs = $pagelog->getRevisions($first, $num);
        $this->assertEquals($revsexpected, $revs);
    }

    /**
     * request first recentest revision
     * (so skips first line of current existing page)
     */
    function test_requestrevswithoffset() {
        $first = 10;
        $num = 5;
        $revsexpected = array_slice($this->revsexpected, $first + 1, $num);

        $pagelog = new PageChangeLog($this->pageid, $chunk_size = 8192);
        $revs = $pagelog->getRevisions($first, $num);
        $this->assertEquals($revsexpected, $revs);

        $pagelog = new PageChangeLog($this->pageid, $chunk_size = 512);
        $revs = $pagelog->getRevisions($first, $num);
        $this->assertEquals($revsexpected, $revs);

        $pagelog = new PageChangeLog($this->pageid, $chunk_size = 20);
        $revs = $pagelog->getRevisions($first, $num);
        $this->assertEquals($revsexpected, $revs);
    }

    /**
     * first = -1 requests recentest logline, without skipping
     */
    function test_requestrecentestlogline() {
        $first = -1;
        $num = 1;
        $revsexpected = array($this->revsexpected[0]);

        $pagelog = new PageChangeLog($this->pageid, $chunk_size = 8192);
        $revs = $pagelog->getRevisions($first, $num);
        $this->assertEquals($revsexpected, $revs);

        $pagelog = new PageChangeLog($this->pageid, $chunk_size = 512);
        $revs = $pagelog->getRevisions($first, $num);
        $this->assertEquals($revsexpected, $revs);
    }

    /**
     * chunck size = 0 skips chuncked loading
     */
    function test_wholefile() {
        $first = 0;
        $num = 1000;
        $revsexpected = array_slice($this->revsexpected, 1);

        $pagelog = new PageChangeLog($this->pageid, $chunk_size = 0);
        $revs = $pagelog->getRevisions($first, $num);
        $this->assertEquals($revsexpected, $revs);
    }

    /**
     * Negative range returns no result
     */
    function test_negativenum() {
        $first = 0;
        $num = -10;
        $revsexpected = array();

        $pagelog = new PageChangeLog($this->pageid, $chunk_size = 8192);
        $revs = $pagelog->getRevisions($first, $num);
        $this->assertEquals($revsexpected, $revs);

        $pagelog = new PageChangeLog($this->pageid, $chunk_size = 512);
        $revs = $pagelog->getRevisions($first, $num);
        $this->assertEquals($revsexpected, $revs);
    }

    /**
     * Negative range returns no result
     */
    function test_negativennumoffset() {
        $first = 2;
        $num = -10;
        $revsexpected = array();

        $pagelog = new PageChangeLog($this->pageid, $chunk_size = 8192);
        $revs = $pagelog->getRevisions($first, $num);
        $this->assertEquals($revsexpected, $revs);

        $pagelog = new PageChangeLog($this->pageid, $chunk_size = 512);
        $revs = $pagelog->getRevisions($first, $num);
        $this->assertEquals($revsexpected, $revs);
    }

    /**
     * zero range returns no result
     */
    function test_zeronum() {
        $first = 5;
        $num = 0;
        $revsexpected = array();

        $pagelog = new PageChangeLog($this->pageid, $chunk_size = 8192);
        $revs = $pagelog->getRevisions($first, $num);
        $this->assertEquals($revsexpected, $revs);

        $pagelog = new PageChangeLog($this->pageid, $chunk_size = 512);
        $revs = $pagelog->getRevisions($first, $num);
        $this->assertEquals($revsexpected, $revs);
    }

    /**
     * get oldest revisions
     */
    function test_requestlargeoffset() {
        $first = 22;
        $num = 50;
        $revsexpected = array_slice($this->revsexpected, $first + 1);

        $pagelog = new PageChangeLog($this->pageid, $chunk_size = 8192);
        $revs = $pagelog->getRevisions($first, $num);
        $this->assertEquals($revsexpected, $revs);
    }

    /**
     * request with too large offset and range
     */
    function test_requesttoolargenumberrevs() {
        $first = 50;
        $num = 50;
        $revsexpected = array();

        $pagelog = new PageChangeLog($this->pageid, $chunk_size = 8192);
        $revs = $pagelog->getRevisions($first, $num);
        $this->assertEquals($revsexpected, $revs);
    }

}
