<?php

namespace dokuwiki\Search;

use dokuwiki\Search\Exception\IndexAccessException;
use dokuwiki\Search\Exception\IndexLockException;
use dokuwiki\Search\Exception\IndexWriteException;
use dokuwiki\Utf8;

/**
 * Abstract Class DokuWiki Index
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 * @author Tom N Harris <tnharris@whoopdedo.org>
 */
abstract class AbstractIndex
{
    /* pages will be marked as deleted in page.idx */
    const INDEX_MARK_DELETED = '#deleted:';

    /** @var array $pidCache Cache for getPID() */
    protected static $pidCache = array();

    /**
     * Clean a name of a key for use as a file name.
     *
     * Romanizes non-latin characters, then strips away anything that's
     * not a letter, number, or underscore.
     *
     * @author Tom N Harris <tnharris@whoopdedo.org>
     *
     * @param string $name
     * @return string
     */
    protected function cleanName($name)
    {
        $name = Utf8\Clean::romanize(trim((string)$name));
        $name = preg_replace('#[ \./\\:-]+#', '_', $name);
        $name = preg_replace('/[^A-Za-z0-9_]/', '', $name);
        return strtolower($name);
    }

    /**
     * Get the numeric PID of a page
     *
     * Warning: The page may not exist in the filesystem.
     *
     * @param string $page The page to get the PID for
     * @return int  The numeric page id
     *
     * @throws IndexAccessException
     * @throws IndexLockException
     * @throws IndexWriteException
     */
    public function getPID($page)
    {
        if (!isset($page)) {
            throw new IndexAccessException('Indexer: invalid argument for getPID');
        }

        // return PID when it is in the cache
        // avoid expensive addIndexKey operation for the most recently
        // requested pages by using a cache
        if (isset(static::$pidCache[$page])) return static::$pidCache[$page];

        $this->lock();

        $index = $this->getIndex('page', '');
        $pid = array_search($page, $index, true);
        if ($pid !== false) {
            $flagSaveIndex = false;
        } else {
            $flagSaveIndex = true;
            // search old page entry that had marked as deleted
            $pid = array_search(self::INDEX_MARK_DELETED.$page, $index, true);
            if ($pid !== false) {
                $index[$pid] = $page;
            } else {
                $pid = count($index);
                $index[$pid] = $page;
            }
        }

        if ($flagSaveIndex) $this->saveIndex('page', '', $index);

        // limit cache to 10 entries by discarding the oldest element
        // as in DokuWiki usually only the most recently
        // added item will be requested again
        if (count(static::$pidCache) > 10) array_shift(static::$pidCache);
        static::$pidCache[$page] = $pid;

        $this->unlock();
        return $pid;
    }

    /**
     * Reset pidCache
     */
    protected function resetPIDCache()
    {
        static::$pidCache = array();
    }

    /**
     * Get the page id of a numeric PID
     *
     * @param int $pid The PID to get the page id for
     * @return string The page id
     */
    public function getPageFromPID($pid)
    {
        return $this->getIndexKey('page', '', $pid);
    }

    /**
     * Return a list of all pages
     * Warning: pages may not exist in the filesystem.
     *
     * @return array            list of page names
     */
    public function getPages()
    {
        return array_filter($this->getIndex('page', ''),
            function ($v) {
                return ($v[0] !== self::INDEX_MARK_DELETED[0]);
            }
        );
    }

    /**
     * Lock the indexer
     *
     * @return true
     * @throws IndexLockException
     * @author Tom N Harris <tnharris@whoopdedo.org>
     *
     */
    protected function lock()
    {
        global $conf;
        $run = 0;
        $lock = $conf['lockdir'].'/_indexer.lock';
        while (!@mkdir($lock, $conf['dmode'])) {
            usleep(50);
            if (is_dir($lock) && time() - @filemtime($lock) > 60*5) {
                // looks like a stale lock - remove it
                if (!@rmdir($lock)) {
                    throw new IndexLockException('Indexer: removing the stale lock failed');
                }
            } elseif ($run++ == 1000) {
                // we waited 5 seconds for that lock
                throw new IndexLockException('Indexer: time out to aquire lock');
            }
        }
        if (!empty($conf['dperm'])) {
            chmod($lock, $conf['dperm']);
        }
        return true;
    }

    /**
     * Release the indexer lock
     *
     * @return true
     * @throws IndexLockException
     * @author Tom N Harris <tnharris@whoopdedo.org>
     *
     */
    protected function unlock()
    {
        global $conf;
        if (!@rmdir($conf['lockdir'].'/_indexer.lock')) {
            throw new IndexLockException('Indexer: unlock failed');
        }
        return true;
    }

    /**
     * Retrieve the entire index
     *
     * The $suffix argument is for an index that is split into multiple parts.
     * Different index files should use different base names.
     *
     * @param string    $idx    name of the index
     * @param string    $suffix subpart identifier
     * @return array            list of lines without CR or LF
     *
     * @author Tom N Harris <tnharris@whoopdedo.org>
     */
    public function getIndex($idx, $suffix)
    {
        global $conf;
        $fn = $conf['indexdir'].'/'.$idx.$suffix.'.idx';
        if (!file_exists($fn)) return array();
        return file($fn, FILE_IGNORE_NEW_LINES);
    }

    /**
     * Replace the contents of the index with an array
     *
     * @param string $idx name of the index
     * @param string $suffix subpart identifier
     * @param array $lines list of lines without LF
     * @return true
     *
     * @throws IndexWriteException
     * @author Tom N Harris <tnharris@whoopdedo.org>
     */
    protected function saveIndex($idx, $suffix, $lines)
    {
        global $conf;
        $fn = $conf['indexdir'].'/'.$idx.$suffix;
        $fh = @fopen($fn.'.tmp', 'w');
        if (!$fh) {
            throw new IndexWriteException("Failed to write {$idx}{$suffix} index");
        }
        fwrite($fh, implode("\n", $lines));
        if (!empty($lines)) {
            fwrite($fh, "\n");
        }
        fclose($fh);
        if ($conf['fperm']) {
            chmod($fn.'.tmp', $conf['fperm']);
        }
        io_rename($fn.'.tmp', $fn.'.idx');
        return true;
    }

    /**
     * Retrieve or insert a value in the index
     *
     * @param string $idx name of the index
     * @param string $suffix subpart identifier
     * @param string $value line to find in the index
     * @return int  line number of the value in the index
     *
     * @throws IndexWriteException
     * @author Tom N Harris <tnharris@whoopdedo.org>
     */
    protected function addIndexKey($idx, $suffix, $value)
    {
        $index = $this->getIndex($idx, $suffix);
        $id = array_search($value, $index, true);
        if ($id === false) {
            $id = count($index);
            $index[$id] = $value;
            $this->saveIndex($idx, $suffix, $index);
        }
        return (int) $id;
    }

    /**
     * Write a line into the index
     *
     * @param string $idx name of the index
     * @param string $suffix subpart identifier
     * @param int $id the line number
     * @param string $line line to write
     * @return true
     *
     * @throws IndexWriteException
     * @author Tom N Harris <tnharris@whoopdedo.org>
     */
    protected function saveIndexKey($idx, $suffix, $id, $line)
    {
        global $conf;
        if (substr($line, -1) !== "\n") {
            $line .= "\n";
        }
        $fn = $conf['indexdir'].'/'.$idx.$suffix;
        $fh = @fopen($fn.'.tmp', 'w');
        if (!$fh) {
            throw new IndexWriteException("Failed to write {$idx}{$suffix} index");
        }
        $ih = @fopen($fn.'.idx', 'r');
        if ($ih) {
            $ln = -1;
            while (($curline = fgets($ih)) !== false) {
                fwrite($fh, (++$ln == $id) ? $line : $curline);
            }
            if ($id > $ln) {
                while ($id > ++$ln) {
                    fwrite($fh, "\n");
                }
                fwrite($fh, $line);
            }
            fclose($ih);
        } else {
            $ln = -1;
            while ($id > ++$ln) {
                fwrite($fh, "\n");
            }
            fwrite($fh, $line);
        }
        fclose($fh);
        if ($conf['fperm']) {
            chmod($fn.'.tmp', $conf['fperm']);
        }
        io_rename($fn.'.tmp', $fn.'.idx');
        return true;
    }

    /**
     * Retrieve a line from the index
     *
     * @param string    $idx    name of the index
     * @param string    $suffix subpart identifier
     * @param int       $id     the line number
     * @return string           a line with trailing whitespace removed
     *
     * @author Tom N Harris <tnharris@whoopdedo.org>
     */
    protected function getIndexKey($idx, $suffix, $id)
    {
        global $conf;
        $fn = $conf['indexdir'].'/'.$idx.$suffix.'.idx';
        if (!file_exists($fn)) return '';
        $fh = @fopen($fn, 'r');
        if (!$fh) return '';
        $ln = -1;
        while (($line = fgets($fh)) !== false) {
            if (++$ln == $id) break;
        }
        fclose($fh);
        return rtrim((string)$line);
    }

    /**
     * Insert or replace a tuple in a line
     *
     * @author Tom N Harris <tnharris@whoopdedo.org>
     *
     * @param string     $line
     * @param int|string $id
     * @param int        $count
     * @return string
     */
    protected function updateTuple($line, $id, $count)
    {
        if ($line != '') {
            $line = preg_replace('/(^|:)'.preg_quote($id,'/').'\*\d*/', '', $line);
        }
        $line = trim($line, ':');
        if ($count) {
            if ($line) {
                return "{$id}*{$count}:".$line;
            } else {
                return "{$id}*{$count}";
            }
        }
        return $line;
    }

    /**
     * Split a line into an array of tuples
     *
     * @author Tom N Harris <tnharris@whoopdedo.org>
     * @author Andreas Gohr <andi@splitbrain.org>
     *
     * @param array      $keys
     * @param string     $line
     * @return array
     */
    protected function parseTuples($keys, $line)
    {
        $result = array();
        if ($line == '') return $result;
        $parts = explode(':', $line);
        foreach ($parts as $tuple) {
            if ($tuple === '') continue;
            list($key, $cnt) = explode('*', $tuple);
            if (!$cnt) continue;
            $key = $keys[$key];
            if ($key === false || is_null($key)) continue;
            $result[$key] = $cnt;
        }
        return $result;
    }

    /**
     * Sum the counts in a list of tuples
     *
     * @author Tom N Harris <tnharris@whoopdedo.org>
     *
     * @param string     $line
     * @return int
     */
    protected function countTuples($line)
    {
        $freq = 0;
        $parts = explode(':', $line);
        foreach ($parts as $tuple) {
            if ($tuple === '') continue;
            list(/* $pid */, $cnt) = explode('*', $tuple);
            $freq += (int)$cnt;
        }
        return $freq;
    }

    /**
     * Clear the whole index
     *
     * @return bool  If the index has been cleared successfully
     */
    abstract public function clear();
}
