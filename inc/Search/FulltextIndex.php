<?php

namespace dokuwiki\Search;

use dokuwiki\Search\Exception\IndexAccessException;
use dokuwiki\Search\Exception\IndexLockException;
use dokuwiki\Search\Exception\IndexWriteException;
use dokuwiki\Search\Tokenizer;
use dokuwiki\Utf8;

/**
 * Class DokuWiki Fulltext Index
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 * @author Tom N Harris <tnharris@whoopdedo.org>
 */
class FulltextIndex extends AbstractIndex
{
    // numeric page id to be added to or deleted from the Fulltext index
    protected $pageID;

    /**
     * FulltextIndex constructor
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
     * Measure the length of a string
     * Differs from strlen in handling of asian characters.
     *
     * @author Tom N Harris <tnharris@whoopdedo.org>
     *
     * @param string $w
     * @return int
     */
    public function wordlen($w)
    {
        $l = strlen($w);
        // If left alone, all chinese "words" will get put into w3.idx
        // So the "length" of a "word" is faked
        if (preg_match_all('/[\xE2-\xEF]/', $w, $leadbytes)) {
            foreach ($leadbytes[0] as $b) {
                $l += ord($b) - 0xE1;
            }
        }
        return $l;
    }

    /**
     * Adds the contents of a page to the fulltext index
     *
     * The added text replaces previous words for the same page.
     * An empty value erases the page.
     *
     * @param string $text the body of the page
     * @param bool $requireLock should be false only if the caller is resposible for index lock
     * @return bool  if the function completed successfully
     *
     * @throws IndexAccessException
     * @throws IndexLockException
     * @throws IndexWriteException
     * @author Andreas Gohr <andi@splitbrain.org>
     * @author Tom N Harris <tnharris@whoopdedo.org>
     */
    public function addWords($text, $requireLock = true)
    {
        // load known documents
        if (!isset($this->pageID)) {
            throw new IndexAccessException('Indexer: page unknown to addWords');
        } else {
            $pid = $this->pageID;
        }
 
        if ($requireLock) $this->lock();

        $pagewords = array();
        // get word usage in page
        $words = $this->getWords($text);

        foreach (array_keys($words) as $wlen) {
            $index = $this->getIndex('i', $wlen);
            foreach ($words[$wlen] as $wid => $freq) {
                $idx = ($wid < count($index)) ? $index[$wid] : '';
                $index[$wid] = $this->updateTuple($idx, $pid, $freq);
                $pagewords[] = "{$wlen}*{$wid}";
            }
            $this->saveIndex('i', $wlen, $index);
        }

        // Remove obsolete index entries
        $pageword_idx = $this->getIndexKey('pageword', '', $pid);
        if ($pageword_idx !== '') {
            $oldwords = explode(':', $pageword_idx);
            $delwords = array_diff($oldwords, $pagewords);
            $upwords = array();
            foreach ($delwords as $word) {
                if ($word != '') {
                    list($wlen, $wid) = explode('*', $word);
                    $wid = (int)$wid;
                    $upwords[$wlen][] = $wid;
                }
            }
            foreach ($upwords as $wlen => $widx) {
                $index = $this->getIndex('i', $wlen);
                foreach ($widx as $wid) {
                    $index[$wid] = $this->updateTuple($index[$wid], $pid, 0);
                }
                $this->saveIndex('i', $wlen, $index);
            }
        }
        // Save the reverse index
        $pageword_idx = implode(':', $pagewords);
        $this->saveIndexKey('pageword', '', $pid, $pageword_idx);

        if ($requireLock) $this->unlock();
        return true;
    }

    /**
     * Split the words in a page and add them to the index
     *
     * @param string $text content of the page
     * @return array  list of word IDs and number of times used, false on errors
     *
     * @throws IndexWriteException
     * @author Andreas Gohr <andi@splitbrain.org>
     * @author Christopher Smith <chris@jalakai.co.uk>
     * @author Tom N Harris <tnharris@whoopdedo.org>
     */
    protected function getWords($text)
    {
        $tokens = Tokenizer::getWords($text);
        $tokens = array_count_values($tokens);  // count the frequency of each token

        $words = array();
        foreach ($tokens as $w => $c) {
            $l = $this->wordlen($w);
            if (isset($words[$l])) {
                $words[$l][$w] = $c + (isset($words[$l][$w]) ? $words[$l][$w] : 0);
            } else {
                $words[$l] = array($w => $c);
            }
        }

        // arrive here with $words = array(wordlen => array(word => frequency))
        $word_idx_modified = false;
        $index = array();   //resulting index
        foreach (array_keys($words) as $wlen) {
            $word_idx = $this->getIndex('w', $wlen);
            foreach ($words[$wlen] as $word => $freq) {
                $word = (string)$word;
                $wid = array_search($word, $word_idx, true);
                if ($wid === false) {
                    $wid = count($word_idx);
                    $word_idx[] = $word;
                    $word_idx_modified = true;
                }
                if (!isset($index[$wlen])) {
                    $index[$wlen] = array();
                }
                $index[$wlen][$wid] = $freq;
            }
            // save back the word index
            if ($word_idx_modified) $this->saveIndex('w', $wlen, $word_idx);
        }

        return $index;
    }

    /**
     * Delete the contents of a page to the fulltext index
     *
     * @param bool $requireLock should be false only if the caller is resposible for index lock
     * @return bool  If renaming the value has been successful, false on error
     *
     * @throws IndexAccessException
     * @throws IndexLockException
     * @throws IndexWriteException
     * @author Satoshi Sahara <sahara.satoshi@gmail.com>
     * @author Tom N Harris <tnharris@whoopdedo.org>
     */
    public function deleteWords($requireLock = true)
    {
        // load known documents
        if (!isset($this->pageID)) {
            throw new IndexAccessException('Indexer: page unknown to deleteWords');
        } else {
            $pid = $this->pageID;
        }

        if ($requireLock) $this->lock();

        // remove obsolete index entries
        $pageword_idx = $this->getIndexKey('pageword', '', $pid);
        if ($pageword_idx !== '') {
            $delwords = explode(':', $pageword_idx);
            $upwords = array();
            foreach ($delwords as $word) {
                if ($word != '') {
                    list($wlen, $wid) = explode('*', $word);
                    $wid = (int)$wid;
                    $upwords[$wlen][] = $wid;
                }
            }
            foreach ($upwords as $wlen => $widx) {
                $index = $this->getIndex('i', $wlen);
                foreach ($widx as $wid) {
                    $index[$wid] = $this->updateTuple($index[$wid], $pid, 0);
                }
                $this->saveIndex('i', $wlen, $index);
            }
        }
        // save the reverse index
        $this->saveIndexKey('pageword', '', $pid, '');

        if ($requireLock) $this->unlock();
        return true;
    }

    /**
     * Find pages in the fulltext index containing the words,
     *
     * The search words must be pre-tokenized, meaning only letters and
     * numbers with an optional wildcard
     *
     * The returned array will have the original tokens as key. The values
     * in the returned list is an array with the page names as keys and the
     * number of times that token appears on the page as value.
     *
     * @param array  $tokens list of words to search for
     * @return array         list of page names with usage counts
     *
     * @author Tom N Harris <tnharris@whoopdedo.org>
     * @author Andreas Gohr <andi@splitbrain.org>
     */
    public function lookupWords(&$tokens)
    {
        $result = array();
        $wids = $this->getIndexWords($tokens, $result);
        if (empty($wids)) return array();
        // load known words and documents
        $page_idx = $this->getIndex('page', '');
        $docs = array();
        foreach (array_keys($wids) as $wlen) {
            $wids[$wlen] = array_unique($wids[$wlen]);
            $index = $this->getIndex('i', $wlen);
            foreach ($wids[$wlen] as $ixid) {
                if ($ixid < count($index)) {
                    $docs["{$wlen}*{$ixid}"] = $this->parseTuples($page_idx, $index[$ixid]);
                }
            }
        }
        // merge found pages into final result array
        $final = array();
        foreach ($result as $word => $res) {
            $final[$word] = array();
            foreach ($res as $wid) {
                // handle the case when ($ixid < count($index)) has been false
                // and thus $docs[$wid] hasn't been set.
                if (!isset($docs[$wid])) continue;
                $hits =& $docs[$wid];
                foreach ($hits as $hitkey => $hitcnt) {
                    // make sure the document still exists
                    if (!page_exists($hitkey, '', false)) continue;
                    if (!isset($final[$word][$hitkey])) {
                        $final[$word][$hitkey] = $hitcnt;
                    } else {
                        $final[$word][$hitkey] += $hitcnt;
                    }
                }
            }
        }
        return $final;
    }

    /**
     * Find the index ID of each search term
     *
     * The query terms should only contain valid characters, with a '*' at
     * either the beginning or end of the word (or both).
     * The $result parameter can be used to merge the index locations with
     * the appropriate query term.
     *
     * @param array  $words  The query terms.
     * @param array  $result Set to word => array("length*id" ...)
     * @return array         Set to length => array(id ...)
     *
     * @author Tom N Harris <tnharris@whoopdedo.org>
     */
    protected function getIndexWords(&$words, &$result)
    {
        $tokens = array();
        $tokenlength = array();
        $tokenwild = array();
        foreach ($words as $word) {
            $result[$word] = array();
            $caret = '^';
            $dollar = '$';
            $xword = $word;
            $wlen = $this->wordlen($word);

            // check for wildcards
            if (substr($xword, 0, 1) == '*') {
                $xword = substr($xword, 1);
                $caret = '';
                $wlen -= 1;
            }
            if (substr($xword, -1, 1) == '*') {
                $xword = substr($xword, 0, -1);
                $dollar = '';
                $wlen -= 1;
            }
            if ($wlen < Tokenizer::getMinWordLength()
                && $caret && $dollar && !is_numeric($xword)
            ) {
                continue;
            }
            if (!isset($tokens[$xword])) {
                $tokenlength[$wlen][] = $xword;
            }
            if (!$caret || !$dollar) {
                $re = $caret.preg_quote($xword, '/').$dollar;
                $tokens[$xword][] = array($word, '/'.$re.'/');
                if (!isset($tokenwild[$xword])) {
                    $tokenwild[$xword] = $wlen;
                }
            } else {
                $tokens[$xword][] = array($word, null);
            }
        }
        asort($tokenwild);
        // $tokens = array( base word => array( [ query term , regexp ] ... ) ... )
        // $tokenlength = array( base word length => base word ... )
        // $tokenwild = array( base word => base word length ... )
        $length_filter = empty($tokenwild) ? $tokenlength : min(array_keys($tokenlength));
        $indexes_known = $this->getIndexLengths($length_filter);
        if (!empty($tokenwild)) sort($indexes_known);
        // get word IDs
        $wids = array();
        foreach ($indexes_known as $ixlen) {
            $word_idx = $this->getIndex('w', $ixlen);
            // handle exact search
            if (isset($tokenlength[$ixlen])) {
                foreach ($tokenlength[$ixlen] as $xword) {
                    $wid = array_search($xword, $word_idx, true);
                    if ($wid !== false) {
                        $wids[$ixlen][] = $wid;
                        foreach ($tokens[$xword] as $w)
                            $result[$w[0]][] = "{$ixlen}*{$wid}";
                    }
                }
            }
            // handle wildcard search
            foreach ($tokenwild as $xword => $wlen) {
                if ($wlen >= $ixlen) break;
                foreach ($tokens[$xword] as $w) {
                    if (is_null($w[1])) continue;
                    foreach (array_keys(preg_grep($w[1], $word_idx)) as $wid) {
                        $wids[$ixlen][] = $wid;
                        $result[$w[0]][] = "{$ixlen}*{$wid}";
                    }
                }
            }
        }
        return $wids;
    }

    /**
     * Get the word lengths that have been indexed
     *
     * Reads the index directory and returns an array of lengths
     * that there are indices for.
     *
     * @author YoBoY <yoboy.leguesh@gmail.com>
     *
     * @param array|int $filter
     * @return array
     */
    public function getIndexLengths($filter)
    {
        global $conf;
        $idx = array();
        if (is_array($filter)) {
            // testing if index files exist only
            $path = $conf['indexdir']."/i";
            foreach ($filter as $key => $value) {
                if (file_exists($path.$key.'.idx')) {
                    $idx[] = $key;
                }
            }
        } else {
            $lengths = $this->listIndexLengths();
            foreach ($lengths as $key => $length) {
                // keep all the values equal or superior
                if ((int)$length >= (int)$filter) {
                    $idx[] = $length;
                }
            }
        }
        return $idx;
    }

    /**
     * Get the list of lengths indexed in the wiki
     *
     * Read the index directory or a cache file and returns
     * a sorted array of lengths of the words used in the wiki.
     *
     * @author YoBoY <yoboy.leguesh@gmail.com>
     *
     * @return array
     */
    public function listIndexLengths()
    {
        global $conf;
        $lengthsFile = $conf['indexdir'].'/lengths.idx';

        // testing what we have to do, create a cache file or not.
        if ($conf['readdircache'] == 0) {
            $docache = false;
        } else {
            clearstatcache();
            if (file_exists($lengthsFile)
                && (time() < @filemtime($lengthsFile) + $conf['readdircache'])
            ) {
                $lengths = @file($lengthsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if ($lengths !== false) {
                    $idx = array();
                    foreach ($lengths as $length) {
                        $idx[] = (int)$length;
                    }
                    return $idx;
                }
            }
            $docache = true;
        }

        if ($conf['readdircache'] == 0 || $docache) {
            $dir = @opendir($conf['indexdir']);
            if ($dir === false) return array();
            $idx = array();
            while (($f = readdir($dir)) !== false) {
                if (substr($f, 0, 1) == 'i' && substr($f, -4) == '.idx') {
                    $i = substr($f, 1, -4);
                    if (is_numeric($i)) $idx[] = (int)$i;
                }
            }
            closedir($dir);
            sort($idx);
            // save this in a file
            if ($docache) {
                $handle = @fopen($lengthsFile, 'w');
                @fwrite($handle, implode("\n", $idx));
                @fclose($handle);
            }
            return $idx;
        }
        return array();
    }

    /**
     * Return a list of words sorted by number of times used
     *
     * @param int       $min    bottom frequency threshold
     * @param int       $max    upper frequency limit. No limit if $max<$min
     * @param int       $minlen minimum length of words to count
     * @return array            list of words as the keys and frequency as value
     *
     * @author Tom N Harris <tnharris@whoopdedo.org>
     */
    public function histogram($min=1, $max=0, $minlen=3)
    {
        return (new MetadataIndex())->histogram($min, $max, $minlen);
    }

    /**
     * Clear the Fulltext Index
     *
     * @param bool $requireLock should be false only if the caller is resposible for index lock
     * @return bool  If the index has been cleared successfully
     * @throws Exception\IndexLockException
     */
    public function clear($requireLock = true)
    {
        global $conf;

        if ($requireLock) $this->lock();

        $lengths = $this->listIndexLengths();
        foreach ($lengths as $length) {
            @unlink($conf['indexdir'].'/i'.$length.'.idx');
            @unlink($conf['indexdir'].'/w'.$length.'.idx');
        }
        @unlink($conf['indexdir'].'/lengths.idx');
        @unlink($conf['indexdir'].'/pageword.idx');

        if ($requireLock) $this->unlock();
        return true;
    }
}
