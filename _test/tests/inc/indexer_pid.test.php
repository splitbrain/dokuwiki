<?php

use dokuwiki\Search\Indexer;

/**
 * Tests the pid functions of the indexer.
 *
 * @author Michael Hamann <michael@content-space.de>
 */
class indexer_pid_test extends DokuWikiTest
{
    public function test_pid()
    {
        $Indexer = new Indexer();
        $syntaxPID = $Indexer->getPID('wiki:syntax');
        $this->assertEquals('wiki:syntax', $Indexer->getPageFromPID($syntaxPID), 'getPageFromPID(getPID(\'wiki:syntax\')) != \'wiki:syntax\'');
        $dokuwikiPID = $Indexer->getPID('wiki:dokuwiki');
        $this->assertEquals('wiki:syntax', $Indexer->getPageFromPID($syntaxPID), 'getPageFromPID(getPID(\'wiki:syntax\')) != \'wiki:syntax\' after getting the PID for wiki:dokuwiki');
        $this->assertEquals($syntaxPID, $Indexer->getPID('wiki:syntax'), 'getPID(\'wiki:syntax\') didn\'t returned different PIDs when called twice');
        $this->assertNotEquals($syntaxPID, $dokuwikiPID, 'Same PID returned for different pages');
        $this->assertTrue(is_numeric($syntaxPID) && is_numeric($dokuwikiPID), 'PIDs are not numeric');
    }
}
