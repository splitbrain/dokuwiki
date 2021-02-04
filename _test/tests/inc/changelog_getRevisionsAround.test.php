<?php

use dokuwiki\ChangeLog\PageChangeLog;

/**
 * Tests for requesting revisions of a page with getRevisions()
 *
 * This class uses the files:
 * - data/pages/mailinglist.txt
 * - data/meta/mailinglist.changes
 */
class changelog_getrevisionsaround_test extends DokuWikiTest {

    /**
     * list of revisions in mailinglist.changes
     */
    private $revsexpected = array(
        1374261194, //current page
        1371579614, 1368622240,
        1368622195, 1368622152,
        1368612599, 1368612506,
        1368609772, 1368575634,
        1363436892, 1362527164,
        1362527046, 1362526861,
        1362526767, 1362526167,
        1362526119, 1362526039,
        1362525926, 1362525899,
        1362525359, 1362525145,
        1362524799, 1361901536,
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
        $rev1 = 1362526767;
        $rev2 = 1362527164;
        $max = 50;
        $id = 'nonexist';
        $revsexpected = array(array(), array());

        $pagelog = new PageChangeLog($id, $chunk_size = 8192);
        $revs = $pagelog->getRevisionsAround($rev1, $rev2, $max);
        $this->assertEquals($revsexpected, $revs);
    }

    /**
     * Surrounding revisions of rev1 and rev2 overlaps
     */
    function test_request_overlapping() {
        $rev1 = 1362526767;
        $rev2 = 1362527164;
        $max = 10;
        $revsexpected = array(
            array_slice($this->revsexpected, 8, 11),
            array_slice($this->revsexpected, 5, 11)
        );

        $pagelog = new PageChangeLog($this->pageid, $chunk_size = 8192);
        $revs = $pagelog->getRevisionsAround($rev1, $rev2, $max);
        $this->assertEquals($revsexpected, $revs);

        $pagelog = new PageChangeLog($this->pageid, $chunk_size = 512);
        $revs = $pagelog->getRevisionsAround($rev1, $rev2, $max);
        $this->assertEquals($revsexpected, $revs);

        $pagelog = new PageChangeLog($this->pageid, $chunk_size = 20);
        $revs = $pagelog->getRevisionsAround($rev1, $rev2, $max);
        $this->assertEquals($revsexpected, $revs);
    }

    /**
     * Surrounding revisions of rev1 and rev2 don't overlap.
     */
    function test_request_non_overlapping() {
        $rev1 = 1362525899;
        $rev2 = 1368612599;
        $max = 10;
        $revsexpected = array(
            array_slice($this->revsexpected, 13, 11),
            array_slice($this->revsexpected, 0, 11)
        );

        $pagelog = new PageChangeLog($this->pageid, $chunk_size = 8192);
        $revs = $pagelog->getRevisionsAround($rev1, $rev2, $max);
        $this->assertEquals($revsexpected, $revs);

        $pagelog = new PageChangeLog($this->pageid, $chunk_size = 512);
        $revs = $pagelog->getRevisionsAround($rev1, $rev2, $max);
        $this->assertEquals($revsexpected, $revs);

        $pagelog = new PageChangeLog($this->pageid, $chunk_size = 20);
        $revs = $pagelog->getRevisionsAround($rev1, $rev2, $max);
        $this->assertEquals($revsexpected, $revs);
    }

    /**
     * rev1 and rev2 are at start and end of the changelog.
     * Should return still a number of revisions equal to max
     */
    function test_request_first_last() {
        $rev1 = 1360110636;
        $rev2 = 1374261194;
        $max = 10;
        $revsexpected = array(
            array_slice($this->revsexpected, 13, 11),
            array_slice($this->revsexpected, 0, 11)
        );

        $pagelog = new PageChangeLog($this->pageid, $chunk_size = 8192);
        $revs = $pagelog->getRevisionsAround($rev1, $rev2, $max);
        $this->assertEquals($revsexpected, $revs);

        //todo: number of revisions on the left side is not (yet) completed until max number
        $revsexpected = array(
            array_slice($this->revsexpected, 18, 6),
            array_slice($this->revsexpected, 0, 11)
        );
        $pagelog = new PageChangeLog($this->pageid, $chunk_size = 512);
        $revs = $pagelog->getRevisionsAround($rev1, $rev2, $max);
        $this->assertEquals($revsexpected, $revs);

        $pagelog = new PageChangeLog($this->pageid, $chunk_size = 20);
        $revs = $pagelog->getRevisionsAround($rev1, $rev2, $max);
        $this->assertEquals($revsexpected, $revs);
    }


    /**
     * Number of requested revisions is larger than available revisions,
     * so returns whole log
     */
    function test_request_wholelog() {
        $rev1 = 1362525899;
        $rev2 = 1368612599;
        $max = 50;
        $revsexpected = array($this->revsexpected, $this->revsexpected);

        $pagelog = new PageChangeLog($this->pageid, $chunk_size = 8192);
        $revs = $pagelog->getRevisionsAround($rev1, $rev2, $max);
        $this->assertEquals($revsexpected, $revs);

        $pagelog = new PageChangeLog($this->pageid, $chunk_size = 512);
        $revs = $pagelog->getRevisionsAround($rev1, $rev2, $max);
        $this->assertEquals($revsexpected, $revs);

        $pagelog = new PageChangeLog($this->pageid, $chunk_size = 20);
        $revs = $pagelog->getRevisionsAround($rev1, $rev2, $max);
        $this->assertEquals($revsexpected, $revs);
    }

    /**
     * When rev1 > rev2, their order is changed
     */
    function test_request_wrong_order_revs() {
        $rev1 = 1362527164;
        $rev2 = 1362526767;
        $max = 10;
        $revsexpected = array(
            array_slice($this->revsexpected, 8, 11),
            array_slice($this->revsexpected, 5, 11)
        );

        $pagelog = new PageChangeLog($this->pageid, $chunk_size = 8192);
        $revs = $pagelog->getRevisionsAround($rev1, $rev2, $max);
        $this->assertEquals($revsexpected, $revs);

        $pagelog = new PageChangeLog($this->pageid, $chunk_size = 512);
        $revs = $pagelog->getRevisionsAround($rev1, $rev2, $max);
        $this->assertEquals($revsexpected, $revs);

        $pagelog = new PageChangeLog($this->pageid, $chunk_size = 20);
        $revs = $pagelog->getRevisionsAround($rev1, $rev2, $max);
        $this->assertEquals($revsexpected, $revs);
    }

}
