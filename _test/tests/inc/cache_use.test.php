<?php

use dokuwiki\Cache\CacheRenderer;

/**
 * Class cache_use_test
 *
 * Tests if caching can actually be used
 *
 * @todo tests marked as flaky until Ticket #694 has been fixed
 */
class cache_use_test extends DokuWikiTest {
    /** @var CacheRenderer $cache */
    private $cache;

    function setUp() : void {
        global $ID, $conf;
        parent::setUp();

        $ID = 'cached';
        $file = wikiFN($ID);
        $conf['cachetime'] = 0;  // ensure the value is not -1, which disables caching

        saveWikiText($ID, 'Content', 'Created');

        $this->cache = new CacheRenderer($ID, $file, 'xhtml');
        $this->cache->storeCache('Test');

        // set the modification times explicitly (overcome Issue #694)
        $time = time();
        touch($file, $time-1);
        touch($this->cache->cache, $time);
    }

    /**
     * In all the following tests the cache should not be usable
     * as such, they are meaningless if test_use didn't pass.
     *
     * @group flaky
     */
    function test_purge() {
        /* @var Input $INPUT */
        global $INPUT;
        $INPUT->set('purge',1);

        $this->assertFalse($this->cache->useCache());
        $this->assertNotEmpty($this->cache->depends['purge']);
    }

    /**
     * @group flaky
     */
    function test_filedependency() {
        // give the dependent src file the same mtime as the cache
        touch($this->cache->file, filemtime($this->cache->cache));
        $this->assertFalse($this->cache->useCache());
    }

    /**
     * @group flaky
     */
    function test_age() {
        // need to age both our source file & the cache
        $age = 10;
        $time = time() - $age - 1;  // older than age

        touch($this->cache->file, $time - 1);
        touch($this->cache->cache, $time);

        $this->assertFalse($this->cache->useCache(array('age' => $age)));
    }

    /**
     * @group flaky
     */
    function test_confnocaching() {
        global $conf;
        $conf['cachetime'] = -1;   // disables renderer caching

        $this->assertFalse($this->cache->useCache());
        $this->assertNotEmpty($this->cache->isNoCache());
    }
}
