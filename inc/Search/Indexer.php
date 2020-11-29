<?php

namespace dokuwiki\Search;

use dokuwiki\Extension\Event;
use dokuwiki\Search\Exception\IndexAccessException;
use dokuwiki\Search\Exception\IndexLockException;
use dokuwiki\Search\Exception\IndexWriteException;

// Version tag used to force rebuild on upgrade
const INDEXER_VERSION = 8;

/**
 * Class DokuWiki Indexer
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 * @author Tom N Harris <tnharris@whoopdedo.org>
 */
class Indexer extends AbstractIndex
{
    // page to be indexed
    protected $page;

    /**
     * Indexer constructor
     *
     * @param string $page name of the page to index
     */
    public function __construct($page = null)
    {
        if (isset($page)) $this->page = $page;
    }

    /**
     * Dispatch Indexing request for the page, called by TaskRunner::runIndexer()
     *
     * @param bool $verbose print status messages
     * @param bool $force force reindexing even when the index is up to date
     * @return bool  If the function completed successfully
     *
     * @throws IndexAccessException
     * @throws IndexLockException
     * @throws IndexWriteException
     * @author Satoshi Sahara <sahara.satoshi@gmail.com>
     * @author Tom N Harris <tnharris@whoopdedo.org>
     */
    public function dispatch($verbose = false, $force = false)
    {
        if (!isset($this->page)) {
            throw new IndexAccessException('Indexer: unknow page name');
        }

        // check if page was deleted but is still in the index
        if (!page_exists($this->page)) {
            return $this->deletePage($verbose, $force);
        }

        // update search index
        return $this->addPage($verbose, $force);
    }

    /**
     * Version of the indexer taking into consideration the external tokenizer.
     * The indexer is only compatible with data written by the same version.
     *
     * @triggers INDEXER_VERSION_GET
     * Plugins that modify what gets indexed should hook this event and
     * add their version info to the event data like so:
     *     $data[$plugin_name] = $plugin_version;
     *
     * @author Tom N Harris <tnharris@whoopdedo.org>
     * @author Michael Hamann <michael@content-space.de>
     *
     * @return int|string
     */
    public function getVersion()
    {
        static $indexer_version = null;
        if ($indexer_version == null) {
            $version = INDEXER_VERSION;

            // DokuWiki version is included for the convenience of plugins
            $data = array('dokuwiki' => $version);
            Event::createAndTrigger('INDEXER_VERSION_GET', $data, null, false);
            unset($data['dokuwiki']); // this needs to be first
            ksort($data);
            foreach ($data as $plugin => $vers) {
                $version .= '+'.$plugin.'='.$vers;
            }
            $indexer_version = $version;
        }
        return $indexer_version;
    }

    /**
     * Adds/updates the search index for the given page
     *
     * Locking is handled internally.
     *
     * @param bool $verbose print status messages
     * @param bool $force force reindexing even when the index is up to date
     * @return bool  If the function completed successfully
     *
     * @throws IndexAccessException
     * @throws IndexLockException
     * @throws IndexWriteException
     * @author Satoshi Sahara <sahara.satoshi@gmail.com>
     * @author Tom N Harris <tnharris@whoopdedo.org>
     */
    public function addPage($verbose = false, $force = false)
    {
        if (!isset($this->page)) {
            throw new IndexAccessException('Indexer: invalid page name in addePage');
        } else {
            $page = $this->page;
        }

        // check if indexing needed for the existing page (full text and/or metadata indexing)
        $idxtag = metaFN($page,'.indexed');
        if (!$force && file_exists($idxtag)) {
            if (trim(io_readFile($idxtag)) == $this->getVersion()) {
                $last = @filemtime($idxtag);
                if ($last > @filemtime(wikiFN($page))) {
                    if ($verbose) dbglog("Indexer: index for {$page} up to date");
                    return true;
                }
            }
        }

        // register the page to the page.idx file, $pid is always integer
        $pid = $this->getPID($page);

        // prepare metadata indexing
        $metadata = array();
        $metadata['title'] = p_get_metadata($page, 'title', METADATA_RENDER_UNLIMITED);

        $references = p_get_metadata($page, 'relation references', METADATA_RENDER_UNLIMITED);
        $metadata['relation_references'] = ($references !== null) ?
                array_keys($references) : array();

        $media = p_get_metadata($page, 'relation media', METADATA_RENDER_UNLIMITED);
        $metadata['relation_media'] = ($media !== null) ?
                array_keys($media) : array();

        // check if full text indexing allowed
        $indexenabled = p_get_metadata($page, 'internal index', METADATA_RENDER_UNLIMITED);
        if ($indexenabled !== false) $indexenabled = true;
        $metadata['internal_index'] = $indexenabled;

        $body = '';
        $data = compact('page', 'body', 'metadata', 'pid');
        $event = new Event('INDEXER_PAGE_ADD', $data);
        if ($event->advise_before()) $data['body'] = $data['body'].' '.rawWiki($page);
        $event->advise_after();
        unset($event);
        extract($data);
        $indexenabled = $metadata['internal_index'];
        unset($metadata['internal_index']);

        // Access to Metadata Index
        $result = (new MetadataIndex($pid))->addMetaKeys($metadata);
        if ($verbose) dbglog("Indexer: addMetaKeys({$page}) ".($result ? 'done' : 'failed'));
        if (!$result) {
            return false;
        }

        // Access to Fulltext Index
        if ($indexenabled) {
            $result = (new FulltextIndex($pid))->addWords($body);
            if ($verbose) dbglog("Indexer: addWords() for {$page} done");
            if (!$result) {
                return false;
            }
        } else {
            if ($verbose) dbglog("Indexer: full text indexing disabled for {$page}");
            // ensure the page content deleted from the Fulltext index
            $result = (new FulltextIndex($page))->deleteWords();
            if ($verbose) dbglog("Indexer: deleteWords() for {$page} done");
            if (!$result) {
                return false;
            }
        }

        // update index tag file
        io_saveFile($idxtag, $this->getVersion());
        if ($verbose) dbglog("Indexer: finished");

        return $result;
    }

    /**
     * Remove a page from the index
     *
     * Erases entries in all known indexes. Locking is handled internally.
     *
     * @param bool $verbose print status messages
     * @param bool $force force reindexing even when the index is up to date
     * @return bool  If the function completed successfully
     *
     * @throws IndexAccessException
     * @throws IndexLockException
     * @throws IndexWriteException
     * @author Satoshi Sahara <sahara.satoshi@gmail.com>
     * @author Tom N Harris <tnharris@whoopdedo.org>
     */
    public function deletePage($verbose = false, $force = false)
    {
        if (!isset($this->page)) {
            throw new IndexAccessException('Indexer: invalid page name in deletePage');
        } else {
            $page = $this->page;
        }

        $idxtag = metaFN($page,'.indexed');
        if (!$force && !file_exists($idxtag)) {
            if ($verbose) dbglog("Indexer: {$page}.indexed file does not exist, ignoring");
            return true;
        }

        // retrieve pid from the page.idx file, $pid is always integer
        $pid = $this->getPID($page);

        // remove obsoleted content from Fulltext index
        $result = (new FulltextIndex($pid))->deleteWords();
        if ($verbose) dbglog("Indexer: deleteWords() for {$page} done");
        if (!$result) {
            return false;
        }

        // delete all keys of the page from metadata index
        $result = (new MetadataIndex($pid))->deleteMetaKeys();
        if ($verbose) dbglog("Indexer: deleteMetaKeys() for {$page} done");
        if (!$result) {
            return false;
        }

        // mark the page as deleted in the page.idx
        $this->lock();
        $this->saveIndexKey('page', '', $pid, self::INDEX_MARK_DELETED.$page);
        if ($verbose) dbglog("Indexer: {$page} has marked as deleted in page.idx");
        $this->unlock();

        unset(static::$pidCache[$pid]);
        @unlink($idxtag);
        return $result;
    }

    /**
     * Rename a page in the search index without changing the indexed content.
     * This function doesn't check if the old or new name exists in the filesystem.
     * It returns an error if the old page isn't in the page list of the indexer
     * and it deletes all previously indexed content of the new page.
     *
     * @param string $oldpage The old page name
     * @param string $newpage The new page name
     * @return bool  If the page was successfully renamed
     * @throws IndexLockException
     * @throws IndexWriteException
     */
    public function renamePage($oldpage, $newpage)
    {
        $index = $this->getIndex('page', '');
        // check if oldpage found in page.idx
        $oldPid = array_search($oldpage, $index, true);
        if ($oldPid === false) return false;

        // check if newpage found in page.idx
        $newPid = array_search($newpage, $index, true);
        if ($newPid !== false) {
            $result = (new Indexer($newpage))->deletePage();
            if (!$result) return false;
            // Note: $index is no longer valid after deletePage()!
            unset($index);
        }

        // update page.idx
        $this->lock();
        $this->saveIndexKey('page', '', $oldPid, $newpage);
        $this->unlock();

        // reset the pid cache
        $this->resetPIDCache();

        return true;
    }

    /**
     * Clear the Page Index
     *
     * @param bool $requireLock should be false only if the caller is resposible for index lock
     * @return bool  If the index has been cleared successfully
     * @throws Exception\IndexLockException
     */
    public function clear($requireLock = true)
    {
        global $conf;

        if ($requireLock) $this->lock();

        // clear Metadata Index
        (new MetadataIndex())->clear(false);

        // clear Fulltext Index
        (new FulltextIndex())->clear(false);

        @unlink($conf['indexdir'].'/page.idx');

        // clear the pid cache
        $this->resetPIDCache();

        if ($requireLock) $this->unlock();
        return true;
    }

}
