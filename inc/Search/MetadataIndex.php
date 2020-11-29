<?php

namespace dokuwiki\Search;

use dokuwiki\Search\Exception\IndexAccessException;
use dokuwiki\Search\Exception\IndexLockException;
use dokuwiki\Search\Exception\IndexWriteException;

/**
 * Class DokuWiki Metadata Index (Singleton)
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 * @author Tom N Harris <tnharris@whoopdedo.org>
 */
class MetadataIndex extends AbstractIndex
{
    // numeric page id to be added to or deleted from the Metadata index
    protected $pageID;

    /**
     * MetadataIndex constructor
     *
     * @param string|int $page a page name or numeric page id
     */
    public function __construct($page = null)
    {
        if (isset($page)) {
            $this->pageID = is_int($page) ? $page : $this->getPID($page);
        }
    }

    /**
     * Return a list of pages containing the metadata key
     * Note: override parent class methods
     *
     * @param string    $key    list only pages containing the metadata key
     * @return array            list of page names
     *
     * @author Tom N Harris <tnharris@whoopdedo.org>
     */
    public function getPages($key = null)
    {
        $page_idx = $this->getIndex('page', '');
        if (is_null($key)) return $page_idx; // same as parent method

        // Special handling for titles
        if ($key == 'title') {
            $title_idx = $this->getIndex('title', '');
            array_splice($page_idx, count($title_idx));
            foreach ($title_idx as $i => $title) {
                if ($title === '') unset($page_idx[$i]);
            }
            return array_values($page_idx);
        }

        $metaname = $this->cleanName($key);
        $pages = array();
        $lines = $this->getIndex($metaname.'_i', '');
        foreach ($lines as $line) {
            $pages = array_merge($pages, $this->parseTuples($page_idx, $line));
        }
        return array_keys($pages);
    }

    /**
     * Add/update keys to/of the metadata index
     *
     * Adding new keys does not remove other keys for the page.
     * The $key parameter can be an array to add multiple keys. $value will not be used if $key is an array.
     * An empty value will remove the page from the metadata index.
     *
     * @param mixed $key a key string or array of key=>value pairs
     * @param mixed $value the value or list of values
     * @param bool $requireLock should be false only if the caller is resposible for index lock
     * @return bool  if the function completed successfully
     *
     * @throws IndexAccessException
     * @throws IndexLockException
     * @throws IndexWriteException
     * @author Michael Hamann <michael@content-space.de>
     * @author Tom N Harris <tnharris@whoopdedo.org>
     */
    public function addMetaKeys($key, $value = null, $requireLock = true)
    {
        // load known documents
        if (!isset($this->pageID)) {
            throw new IndexAccessException('Indexer: page unknown to addMetaKeys');
        } else {
            $pid = $this->pageID;
        }

        if (!is_array($key)) {
            $key = isset($value) ? array($key => $value) : array($key => '');
        }
        unset($key['']);

        if ($requireLock) $this->lock();

        // Special handling for titles so the index file is simpler
        if (array_key_exists('title', $key)) {
            $value = (string)(is_array($key['title']) ? $value[0] : $key['title']);
            $this->saveIndexKey('title', '', $pid, $value);
            unset($key['title']);
        }

        foreach ($key as $name => $values) {
            $metaname = $this->cleanName($name);
            if (empty($metaname)) continue;
            $this->addIndexKey('metadata', '', $metaname);
            $metaidx = $this->getIndex($metaname.'_i', '');
            $metawords = $this->getIndex($metaname.'_w', '');
            $addwords = false;

            $val_idx = $this->getIndexKey($metaname.'_p', '', $pid);
            if ($val_idx !== '') {
                $val_idx = explode(':', $val_idx);
                // -1 means remove, 0 keep, 1 add
                $val_idx = array_combine($val_idx, array_fill(0, count($val_idx), -1));
            } else {
                $val_idx = array();
            }

            if (!is_array($values)) $values = array($values);

            foreach ($values as $val) {
                $val = (string)$val;  // NULL is always converted to an empty string
                if ($val !== '') {
                    $id = array_search($val, $metawords, true);
                    if ($id === false) {
                        // not found $val, so we'll add it to the end of metawords
                        // and create a placeholder in metaidx
                        $id = count($metawords);
                        $metawords[$id] = $val;
                        $metaidx[$id] = '';
                        $addwords = true;
                    }
                    // test if value is already in the index
                    if (isset($val_idx[$id]) && $val_idx[$id] <= 0) {
                        $val_idx[$id] = 0;
                    } else { // else add it
                        $val_idx[$id] = 1;
                    }
                }
            }

            if ($addwords) {
                $this->saveIndex($metaname.'_w', '', $metawords);
            }
            $vals_changed = false;
            foreach ($val_idx as $id => $action) {
                if ($action == -1) {
                    $metaidx[$id] = $this->updateTuple($metaidx[$id], $pid, 0);
                    $vals_changed = true;
                    unset($val_idx[$id]);
                } elseif ($action == 1) {
                    $metaidx[$id] = $this->updateTuple($metaidx[$id], $pid, 1);
                    $vals_changed = true;
                }
            }

            if ($vals_changed) {
                $this->saveIndex($metaname.'_i', '', $metaidx);
                $val_idx = implode(':', array_keys($val_idx));
                $this->saveIndexKey($metaname.'_p', '', $pid, $val_idx);
            }

            unset($metaidx);
            unset($metawords);
        }

        if ($requireLock) $this->unlock();
        return true;
    }

    /**
     * Delete keys of the page from metadata index
     *
     * @param mixed $keys a key string or array of keys
     * @param bool $requireLock should be false only if the caller is resposible for index lock
     * @return bool  If renaming the value has been successful, false on error
     *
     * @throws IndexAccessException
     * @throws IndexLockException
     * @throws IndexWriteException
     * @author Satoshi Sahara <sahara.satoshi@gmail.com>
     * @author Tom N Harris <tnharris@whoopdedo.org>
     */
    public function deleteMetaKeys($keys = [], $requireLock = true)
    {
        // load known documents
        if (!isset($this->pageID)) {
            throw new IndexAccessException('Indexer: page unknown to deleteMetaKeys');
        } else {
            $pid = $this->pageID;
        }

        if ($requireLock) $this->lock();

        $knownKeys = $this->getIndex('metadata', '');
        $knownKeys[] = 'title';

        // remove all metadata keys of the page when $keys is empty
        $keys = (empty($keys)) ? $knownKeys : (array)$keys;

        foreach ($keys as $metaname) {
            if ($metaname == 'title') {
                // Special handling for titles so the index file is simpler
                $this->saveIndexKey('title', '', $pid, '');
            } elseif (in_array($metaname, $knownKeys)) {
                $meta_idx = $this->getIndex($metaname.'_i', '');
                $val_idx = explode(':', $this->getIndexKey($metaname.'_p', '', $pid));
                foreach ($val_idx as $id) {
                    if ($id === '') continue;
                    $meta_idx[$id] = $this->updateTuple($meta_idx[$id], $pid, 0);
                }
                $this->saveIndex($metaname.'_i', '', $meta_idx);
                $this->saveIndexKey($metaname.'_p', '', $pid, '');
            }
        }

        if ($requireLock) $this->unlock();
        return true;
    }

    /**
     * Find pages containing a metadata key
     *
     * The metadata values are compared as case-sensitive strings. Pass a
     * callback function that returns true or false to use a different
     * comparison function. The function will be called with the $value being
     * searched for as the first argument, and the word in the index as the
     * second argument. The function preg_match can be used directly if the
     * values are regexes.
     *
     * @param string    $key    name of the metadata key to look for
     * @param string    $value  search term to look for, must be a string or array of strings
     * @param callback  $func   comparison function
     * @return array            lists with page names, keys are query values if $value is array
     *
     * @author Tom N Harris <tnharris@whoopdedo.org>
     * @author Michael Hamann <michael@content-space.de>
     */
    public function lookupKey($key, &$value, $func = null)
    {
        if (!is_array($value)) {
            $value_array = array($value);
        } else {
            $value_array =& $value;
        }

        // the matching ids for the provided value(s)
        $value_ids = array();

        $metaname = $this->cleanName($key);

        // get all words in order to search the matching ids
        if ($key == 'title') {
            $words = $this->getIndex('title', '');
        } else {
            $words = $this->getIndex($metaname.'_w', '');
        }

        if (!is_null($func)) {
            foreach ($value_array as $val) {
                foreach ($words as $i => $word) {
                    if (call_user_func_array($func, array($val, $word))) {
                        $value_ids[$i][] = $val;
                    }
                }
            }
        } else {
            foreach ($value_array as $val) {
                $xval = $val;
                $caret = '^';
                $dollar = '$';
                // check for wildcards
                if (substr($xval, 0, 1) == '*') {
                    $xval = substr($xval, 1);
                    $caret = '';
                }
                if (substr($xval, -1, 1) == '*') {
                    $xval = substr($xval, 0, -1);
                    $dollar = '';
                }
                if (!$caret || !$dollar) {
                    $re = $caret.preg_quote($xval, '/').$dollar;
                    foreach (array_keys(preg_grep('/'.$re.'/', $words)) as $i) {
                        $value_ids[$i][] = $val;
                    }
                } else {
                    if (($i = array_search($val, $words, true)) !== false) {
                        $value_ids[$i][] = $val;
                    }
                }
            }
        }

        unset($words); // free the used memory

        // initialize the result so it won't be null
        $result = array();
        foreach ($value_array as $val) {
            $result[$val] = array();
        }

        $page_idx = $this->getIndex('page', '');

        // Special handling for titles
        if ($key == 'title') {
            foreach ($value_ids as $pid => $val_list) {
                $page = $page_idx[$pid];
                foreach ($val_list as $val) {
                    $result[$val][] = $page;
                }
            }
        } else {
            // load all lines and pages so the used lines can be taken
            // and matched with the pages
            $lines = $this->getIndex($metaname.'_i', '');

            foreach ($value_ids as $value_id => $val_list) {
                // parse the tuples of the form page_id*1:page2_id*1 and so on,
                // return value is an array with page_id => 1, page2_id => 1 etc.
                // so take the keys only
                $pages = array_keys($this->parseTuples($page_idx, $lines[$value_id]));
                foreach ($val_list as $val) {
                    $result[$val] = array_merge($result[$val], $pages);
                }
            }
        }
        if (!is_array($value)) $result = $result[$value];
        return $result;
    }

    /**
     * Renames a meta value in the index
     * This doesn't change the meta value in the pages, it assumes that
     * all pages will be updated.
     *
     * @param string $key The metadata key of which a value shall be changed
     * @param string $oldvalue The old value that shall be renamed
     * @param string $newvalue The new value to which the old value shall be renamed,
     *                          if exists values will be merged
     * @return bool  If renaming the value has been successful, false on error
     *
     * @throws IndexLockException
     * @throws IndexWriteException
     */
    public function renameMetaValue($key, $oldvalue, $newvalue)
    {
        $this->lock();

        // change the relation references index
        $metavalues = $this->getIndex($key, '_w');
        $oldid = array_search($oldvalue, $metavalues, true);
        if ($oldid !== false) {
            $newid = array_search($newvalue, $metavalues, true);
            if ($newid !== false) {
                // free memory
                unset($metavalues);

                // okay, now we have two entries for the same value. we need to merge them.
                $indexline = $this->getIndexKey($key.'_i', '', $oldid);
                if ($indexline != '') {
                    $newindexline = $this->getIndexKey($key.'_i', '', $newid);
                    $pagekeys     = $this->getIndex($key.'_p', '');
                    $parts = explode(':', $indexline);
                    foreach ($parts as $part) {
                        list($id, $count) = explode('*', $part);
                        $newindexline = $this->updateTuple($newindexline, $id, $count);

                        $keyline = explode(':', $pagekeys[$id]);
                        // remove old meta value
                        $keyline = array_diff($keyline, array($oldid));
                        // add new meta value when not already present
                        if (!in_array($newid, $keyline)) {
                            array_push($keyline, $newid);
                        }
                        $pagekeys[$id] = implode(':', $keyline);
                    }
                    $this->saveIndex($key.'_p', '', $pagekeys);
                    unset($pagekeys);
                    $this->saveIndexKey($key.'_i', '', $oldid, '');
                    $this->saveIndexKey($key.'_i', '', $newid, $newindexline);
                }
            } else {
                $metavalues[$oldid] = $newvalue;
                $this->saveIndex($key.'_w', '', $metavalues);
            }
        }

        $this->unlock();
        return true;
    }

    /**
     * Return a list of words or frequency sorted by number of times used
     *
     * @param int       $min    bottom frequency threshold
     * @param int       $max    upper frequency limit. No limit if $max<$min
     * @param int       $minlen minimum length of words to count
     * @param string    $key    metadata key to list. Uses the fulltext index if not given
     * @return array            list of words as the keys and frequency as values
     *
     * @author Tom N Harris <tnharris@whoopdedo.org>
     */
    public function histogram($min=1, $max=0, $minlen=3, $key=null)
    {
        if ($min < 1)    $min = 1;
        if ($max < $min) $max = 0;

        $result = array();

        if ($key == 'title') {
            $index = $this->getIndex('title', '');
            $index = array_count_values($index);
            foreach ($index as $val => $cnt) {
                if ($cnt >= $min && (!$max || $cnt <= $max) && strlen($val) >= $minlen) {
                    $result[$val] = $cnt;
                }
            }
        } elseif (!is_null($key)) {
            $metaname = $this->cleanName($key);
            $index = $this->getIndex($metaname.'_i', '');
            $val_idx = array();
            foreach ($index as $wid => $line) {
                $freq = $this->countTuples($line);
                if ($freq >= $min && (!$max || $freq <= $max)) {
                    $val_idx[$wid] = $freq;
                }
            }
            if (!empty($val_idx)) {
                $words = $this->getIndex($metaname.'_w', '');
                foreach ($val_idx as $wid => $freq) {
                    if (strlen($words[$wid]) >= $minlen) {
                        $result[$words[$wid]] = $freq;
                    }
                }
            }
        } else {
            $lengths = (new FulltextIndex)->listIndexLengths();
            foreach ($lengths as $length) {
                if ($length < $minlen) continue;
                $index = $this->getIndex('i', $length);
                $words = null;
                foreach ($index as $wid => $line) {
                    $freq = $this->countTuples($line);
                    if ($freq >= $min && (!$max || $freq <= $max)) {
                        if ($words === null) {
                            $words = $this->getIndex('w', $length);
                        }
                        $result[$words[$wid]] = $freq;
                    }
                }
            }
        }

        arsort($result);
        return $result;
    }

    /**
     * Clear the Metadata Index
     *
     * @param bool $requireLock should be false only if the caller is resposible for index lock
     * @return bool  If the index has been cleared successfully
     * @throws Exception\IndexLockException
     */
    public function clear($requireLock = true)
    {
        global $conf;

        if ($requireLock) $this->lock();

        $knownKeys = $this->getIndex('metadata', '');
        foreach ($knownKeys as $metaname) {
            @unlink($conf['indexdir'].'/'.$metaname.'_w.idx');
            @unlink($conf['indexdir'].'/'.$metaname.'_i.idx');
            @unlink($conf['indexdir'].'/'.$metaname.'_p.idx');
        }
        @unlink($conf['indexdir'].'/title.idx');
        @unlink($conf['indexdir'].'/metadata.idx');

        if ($requireLock) $this->unlock();
        return true;
    }

    /**
     * Returns the backlinks for a given page
     *
     * Uses the metadata index.
     *
     * @param string $id           The id for which links shall be returned
     * @param bool   $ignore_perms Ignore the fact that pages are hidden or read-protected
     * @return array The pages that contain links to the given page
     *
     * @author     Andreas Gohr <andi@splitbrain.org>
     */
    public function backlinks($id, $ignore_perms = false)
    {
        $result = $this->lookupKey('relation_references', $id);

        if (!count($result)) return $result;

        // check ACL permissions
        foreach (array_keys($result) as $idx) {
            if (($ignore_perms !== true
                && (isHiddenPage($result[$idx]) || auth_quickaclcheck($result[$idx]) < AUTH_READ)
                ) || !page_exists($result[$idx], '', false)
            ) {
                unset($result[$idx]);
            }
        }

        sort($result);
        return $result;
    }

    /**
     * Returns the pages that use a given media file
     *
     * Uses the relation media metadata property and the metadata index.
     *
     * Note that before 2013-07-31 the second parameter was the maximum number
     * of results and permissions were ignored. That's why the parameter is now
     * checked to be explicitely set to true (with type bool) in order to be
     * compatible with older uses of the function.
     *
     * @param string $id           The media id to look for
     * @param bool   $ignore_perms Ignore hidden pages and acls (optional, default: false)
     * @return array A list of pages that use the given media file
     *
     * @author     Andreas Gohr <andi@splitbrain.org>
     */
    public function mediause($id, $ignore_perms = false)
    {
        $result = $this->lookupKey('relation_media', $id);

        if (!count($result)) return $result;

        // check ACL permissions
        foreach (array_keys($result) as $idx) {
            if (($ignore_perms !== true
                && (isHiddenPage($result[$idx]) || auth_quickaclcheck($result[$idx]) < AUTH_READ)
                ) || !page_exists($result[$idx], '', false)
            ) {
                unset($result[$idx]);
            }
        }

        sort($result);
        return $result;
    }
}
