<?php

use dokuwiki\ChangeLog\PageChangeLog;

/**
 * Tests for requesting revisioninfo of a revision of a page with getRevisionInfo()
 *
 * This class uses the files:
 * - data/pages/mailinglist.txt
 * - data/meta/mailinglist.changes
 */
class changelog_getrelativerevision_test extends DokuWikiTest {

    private $logline = "1362525899	127.0.0.1	E	mailinglist	pubcie	[Data entry] 	\n";
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
        $rev = 1362525899;
        $dir = 1;
        $id = 'nonexist';
        $revsexpected = false;

        $pagelog = new PageChangeLog($id, $chunk_size = 8192);
        $revs = $pagelog->getRelativeRevision($rev, $dir);
        $this->assertEquals($revsexpected, $revs);
    }

    /**
     * no nonexist.changes meta file available
     */
    function test_nodirection() {
        $rev = 1362525899;
        $dir = 0;
        $revsexpected = false;

        $pagelog = new PageChangeLog($this->pageid, $chunk_size = 8192);
        $revs = $pagelog->getRelativeRevision($rev, $dir);
        $this->assertEquals($revsexpected, $revs);
    }

    /**
     * start at exact current revision of mailinglist page
     *
     */
    function test_startatexactcurrentrev() {
        $rev = 1385051947;
        $dir = 1;
        $revsexpectedpos = false;
        $revsexpectedneg = 1374261194;

        //set a known timestamp
        touch(wikiFN($this->pageid), $rev);

        $pagelog = new PageChangeLog($this->pageid, $chunk_size = 8192);
        $revs = $pagelog->getRelativeRevision($rev, $dir);
        $this->assertEquals($revsexpectedpos, $revs);

        $revs = $pagelog->getRelativeRevision($rev, -$dir);
        $this->assertEquals($revsexpectedneg, $revs);
    }

    /**
     * start at exact last revision of mailinglist page
     *
     */
    function test_startatexactlastrev() {
        $rev = 1360110636;
        $dir = 1;
        $revsexpectedpos = 1361901536;
        $revsexpectedneg = false;

        $pagelog = new PageChangeLog($this->pageid, $chunk_size = 8192);
        $revs = $pagelog->getRelativeRevision($rev, $dir);
        $this->assertEquals($revsexpectedpos, $revs);

        $revs = $pagelog->getRelativeRevision($rev, -$dir);
        $this->assertEquals($revsexpectedneg, $revs);
    }

    /**
     * start at exact one before last revision of mailinglist page
     *
     */
    function test_requestlastrevisions() {
        $rev = 1361901536;
        $dir = -1;
        $revsexpectedlast = 1360110636;
        $revsexpectedbeforelast = false;

        $pagelog = new PageChangeLog($this->pageid, $chunk_size = 8192);
        $revs = $pagelog->getRelativeRevision($rev, $dir);
        $this->assertEquals($revsexpectedlast, $revs);

        $revs = $pagelog->getRelativeRevision($rev, 2 * $dir);
        $this->assertEquals($revsexpectedbeforelast, $revs);
    }

    /**
     * request existing rev and check cache
     */
    function test_requestrev_checkcache() {
        $rev = 1362525359;
        $dir = 1;
        $revexpected = 1362525899;
        $infoexpected = parseChangelogLine($this->logline);

        $pagelog = new PageChangeLog($this->pageid, $chunk_size = 8192);
        $revfound = $pagelog->getRelativeRevision($rev, $dir);
        $this->assertEquals($revexpected, $revfound);

        //checked info returned from cache
        $info = $pagelog->getRevisionInfo($revfound);
        $this->assertEquals($infoexpected, $info);
    }

    /**
     * request existing rev
     */
    function test_requestnextrev() {
        $rev = 1362525899;

        $pagelog = new PageChangeLog($this->pageid, $chunk_size = 8192);

        $dir = 1;
        $revexpected = 1362525926;
        $revfound = $pagelog->getRelativeRevision($rev, $dir);
        $this->assertEquals($revexpected, $revfound);

        $dir = 2;
        $revexpected = 1362526039;
        $revfound = $pagelog->getRelativeRevision($rev, $dir);
        $this->assertEquals($revexpected, $revfound);

        $dir = -1;
        $revexpected = 1362525359;
        $revfound = $pagelog->getRelativeRevision($rev, $dir);
        $this->assertEquals($revexpected, $revfound);

        $dir = -2;
        $revexpected = 1362525145;
        $revfound = $pagelog->getRelativeRevision($rev, $dir);
        $this->assertEquals($revexpected, $revfound);
    }

    /**
     * request existing rev with chucked reading
     */
    function test_requestnextrev_chuncked() {
        $rev = 1362525899;

        $pagelog = new PageChangeLog($this->pageid, $chunk_size = 512);

        $dir = 1;
        $revexpected = 1362525926;
        $revfound = $pagelog->getRelativeRevision($rev, $dir);
        $this->assertEquals($revexpected, $revfound);

        $dir = 2;
        $revexpected = 1362526039;
        $revfound = $pagelog->getRelativeRevision($rev, $dir);
        $this->assertEquals($revexpected, $revfound);

        $dir = -1;
        $revexpected = 1362525359;
        $revfound = $pagelog->getRelativeRevision($rev, $dir);
        $this->assertEquals($revexpected, $revfound);

        $dir = -2;
        $revexpected = 1362525145;
        $revfound = $pagelog->getRelativeRevision($rev, $dir);
        $this->assertEquals($revexpected, $revfound);
    }


    /**
     * request existing rev with chucked reading, chunk size smaller than line length
     */
    function test_requestnextrev_chunkshorterthanlines() {
        $rev = 1362525899;

        $pagelog = new PageChangeLog($this->pageid, $chunk_size = 20);

        $dir = 1;
        $revexpected = 1362525926;
        $revfound = $pagelog->getRelativeRevision($rev, $dir);
        $this->assertEquals($revexpected, $revfound);

        $dir = 2;
        $revexpected = 1362526039;
        $revfound = $pagelog->getRelativeRevision($rev, $dir);
        $this->assertEquals($revexpected, $revfound);

        $dir = -1;
        $revexpected = 1362525359;
        $revfound = $pagelog->getRelativeRevision($rev, $dir);
        $this->assertEquals($revexpected, $revfound);

        $dir = -2;
        $revexpected = 1362525145;
        $revfound = $pagelog->getRelativeRevision($rev, $dir);
        $this->assertEquals($revexpected, $revfound);
    }

    /**
     * request existing rev
     */
    function test_requestnextfifthrev() {
        $rev = 1362525899;
        $dir = 5;
        $revexpected = 1362526767;

        $pagelog = new PageChangeLog($this->pageid, $chunk_size = 8192);
        $revfound = $pagelog->getRelativeRevision($rev, $dir);
        $this->assertEquals($revexpected, $revfound);
    }

    /**
     * request existing rev with chucked reading
     */
    function test_requestnextfifthrev_chuncked() {
        $rev = 1362525899;
        $dir = 5;
        $revexpected = 1362526767;

        $pagelog = new PageChangeLog($this->pageid, $chunk_size = 512);
        $revfound = $pagelog->getRelativeRevision($rev, $dir);
        $this->assertEquals($revexpected, $revfound);
    }

    /**
     * request existing rev
     */
    function test_requestprevrev() {
        $rev = 1362525899;
        $dir1 = -1;
        $dir5 = -5;
        $revexpected1 = 1362525359;
        $revexpected5 = 1360110636;

        $pagelog = new PageChangeLog($this->pageid, $chunk_size = 8192);
        $revfound1 = $pagelog->getRelativeRevision($rev, $dir1);
        $this->assertEquals($revexpected1, $revfound1);

        $revfound5 = $pagelog->getRelativeRevision($rev, $dir5);
        $this->assertEquals($revexpected5, $revfound5);
    }

    /**
     * request existing rev with chucked reading
     */
    function test_requestprevrev_chuncked() {
        $rev = 1362525899;
        $dir1 = -1;
        $dir5 = -5;
        $revexpected1 = 1362525359;
        $revexpected5 = 1360110636;

        $pagelog = new PageChangeLog($this->pageid, $chunk_size = 512);
        $revfound1 = $pagelog->getRelativeRevision($rev, $dir1);
        $this->assertEquals($revexpected1, $revfound1);

        $revfound5 = $pagelog->getRelativeRevision($rev, $dir5);
        $this->assertEquals($revexpected5, $revfound5);
    }

    /**
     * request after recentest version in changelog
     */
    function test_requestrecentestlogline_next() {
        $rev = 1374261194;
        $dir = 1;
        $revexpected = false;

        $pagelog = new PageChangeLog($this->pageid, $chunk_size = 8192);
        $revfound = $pagelog->getRelativeRevision($rev, $dir);
        $this->assertEquals($revexpected, $revfound);
    }

    /**
     * request after recentest version in changelog, with chuncked reading
     */
    function test_requestrecentestlogline_next_chuncked() {
        $rev = 1374261194;
        $dir = 1;
        $revexpected = false;

        $pagelog = new PageChangeLog($this->pageid, $chunk_size = 512);
        $revfound = $pagelog->getRelativeRevision($rev, $dir);
        $this->assertEquals($revexpected, $revfound);
    }

    /**
     * request before current version
     */
    function test_requestrecentestlogline_prev() {
        $rev = 1374261194;
        $dir = -1;
        $revexpected = 1371579614;

        $pagelog = new PageChangeLog($this->pageid, $chunk_size = 8192);
        $revfound = $pagelog->getRelativeRevision($rev, $dir);
        $this->assertEquals($revexpected, $revfound);
    }

    /**
     * request before current version, with chuncked reading
     */
    function test_requestrecentestlogline_prev_chuncked() {
        $rev = 1374261194;
        $dir = -1;
        $revexpected = 1371579614;

        $pagelog = new PageChangeLog($this->pageid, $chunk_size = 512);
        $revfound = $pagelog->getRelativeRevision($rev, $dir);
        $this->assertEquals($revexpected, $revfound);
    }

    /**
     * Request negative revision
     * looks in positive direction, so it catches the oldest revision
     */
    function test_negativerev_posdir() {
        $rev = -10;
        $dir = 1;
        $revexpected = 1360110636;

        $pagelog = new PageChangeLog($this->pageid, $chunk_size = 8192);
        $revfound = $pagelog->getRelativeRevision($rev, $dir);
        $this->assertEquals($revexpected, $revfound);
    }

    /**
     * Request negative revision
     * looks in negative direction, but there is nothing
     */
    function test_negativerev_negdir() {
        $rev = -10;
        $dir = -1;
        $revexpected = false;

        $pagelog = new PageChangeLog($this->pageid, $chunk_size = 8192);
        $revfound = $pagelog->getRelativeRevision($rev, $dir);
        $this->assertEquals($revexpected, $revfound);
    }

    /**
     * Start at non existing revision somewhere between existing revisions
     */
    function test_startatnotexistingrev_next() {
        $rev = 1362525890;
        $dir = 1;
        $revexpected = 1362525899;

        $pagelog = new PageChangeLog($this->pageid, $chunk_size = 8192);
        $revfound = $pagelog->getRelativeRevision($rev, $dir);
        $this->assertEquals($revexpected, $revfound);
    }

    /**
     * Start at non existing revision somewhere between existing revisions
     */
    function test_startatnotexistingrev_prev() {
        $rev = 1362525890;
        $dir = -1;
        $revexpected = 1362525359;

        $pagelog = new PageChangeLog($this->pageid, $chunk_size = 8192);
        $revfound = $pagelog->getRelativeRevision($rev, $dir);
        $this->assertEquals($revexpected, $revfound);
    }

    function test_iscurrentpagerevision() {
        $rev = 1385051947;
        $currentexpected = true;

        //set a known timestamp
        touch(wikiFN($this->pageid), $rev);

        $pagelog = new PageChangeLog($this->pageid, $chunk_size = 8192);
        $current = $pagelog->isCurrentRevision($rev);
        $this->assertEquals($currentexpected, $current);
    }

    function test_isnotcurrentpagerevision() {
        $rev = 1385051947;
        $not_current_rev = $rev - 1;
        $currentexpected = false;

        //set a known timestamp
        touch(wikiFN($this->pageid), $rev);

        $pagelog = new PageChangeLog($this->pageid, $chunk_size = 8192);
        $current = $pagelog->isCurrentRevision($not_current_rev);
        $this->assertEquals($currentexpected, $current);
    }

    function test_notexistingcurrentpage() {
        $rev = 1385051947;
        $currentexpected = false;

        $pagelog = new PageChangeLog('nonexistingpage', $chunk_size = 8192);
        $current = $pagelog->isCurrentRevision($rev);
        $this->assertEquals($currentexpected, $current);
    }
}
