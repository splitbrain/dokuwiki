<?php

namespace dokuwiki\Search;

use dokuwiki\Extension\Event;
use dokuwiki\Search\MetadataIndex;
use dokuwiki\Search\QueryParser;

/**
 * Class DokuWiki Metadata Search
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 */
class MetadataSearch
{
    /**
     * Quicksearch for pagenames
     *
     * By default it only matches the pagename and ignores the namespace.
     * This can be changed with the second parameter.
     * The third parameter allows to search in titles as well.
     *
     * The function always returns titles as well
     *
     * @triggers SEARCH_QUERY_PAGELOOKUP
     * @author   Andreas Gohr <andi@splitbrain.org>
     * @author   Adrian Lang <lang@cosmocode.de>
     *
     * @param string     $id       page id
     * @param bool       $in_ns    match against namespace as well?
     * @param bool       $in_title search in title?
     * @param int|string $after    only show results with mtime after this date,
     *                             accepts timestap or strtotime arguments
     * @param int|string $before   only show results with mtime before this date,
     *                             accepts timestap or strtotime arguments
     *
     * @return string[]
     */
    public function pageLookup($id, $in_ns = false, $in_title = false, $after = null, $before = null)
    {
        $data = [
            'id' => $id,
            'in_ns' => $in_ns,
            'in_title' => $in_title,
            'after' => $after,
            'before' => $before
        ];
        $data['has_titles'] = true; // for plugin backward compatibility check
        $action = [$this, 'pageLookupCallBack'];
        return Event::createAndTrigger('SEARCH_QUERY_PAGELOOKUP', $data, $action);
    }

    /**
     * Returns list of pages as array(pageid => First Heading)
     *
     * @param array $data  event data
     * @return string[]
     */
    public function pageLookupCallBack(&$data)
    {
        // split out original parameters
        $id = $data['id'];
        $parsedQuery = (new QueryParser)->convert($id);

        if (count($parsedQuery['ns']) > 0) {
            $ns = cleanID($parsedQuery['ns'][0]) . ':';
            $id = implode(' ', $parsedQuery['highlight']);
        }

        $in_ns    = $data['in_ns'];
        $in_title = $data['in_title'];
        $cleaned = cleanID($id);

        $pages = array();
        if ($id !== '' && $cleaned !== '') {
            $MetadataIndex = new MetadataIndex();
            $page_idx = $MetadataIndex->getPages();
            foreach ($page_idx as $p_id) {
                if ((strpos($in_ns ? $p_id : noNSorNS($p_id), $cleaned) !== false)) {
                    if (!isset($pages[$p_id])) {
                        $pages[$p_id] = p_get_first_heading($p_id, METADATA_DONT_RENDER);
                    }
                }
            }
            if ($in_title) {
                $func = [$this, 'pageLookupTitleCompare'];
                foreach ($MetadataIndex->lookupKey('title', $id, $func) as $p_id) {
                    if (!isset($pages[$p_id])) {
                        $pages[$p_id] = p_get_first_heading($p_id, METADATA_DONT_RENDER);
                    }
                }
            }
        }

        if (isset($ns)) {
            foreach (array_keys($pages) as $p_id) {
                if (strpos($p_id, $ns) !== 0) {
                    unset($pages[$p_id]);
                }
            }
        }

        // discard hidden pages
        // discard nonexistent pages
        // check ACL permissions
        foreach (array_keys($pages) as $idx) {
            if (!isVisiblePage($idx) || !page_exists($idx) || auth_quickaclcheck($idx) < AUTH_READ) {
                unset($pages[$idx]);
            }
        }

        $pages = $this->filterResultsByTime($pages, $data['after'], $data['before']);

        uksort($pages, [$this, 'pagesorter']);
        return $pages;
    }

    /**
     * Tiny helper function for comparing the searched title with the title
     * from the search index. This function is a wrapper around stripos with
     * adapted argument order and return value.
     *
     * @param string $search searched title
     * @param string $title  title from index
     * @return bool
     */
    protected function pageLookupTitleCompare($search, $title)
    {
        return stripos($title, $search) !== false;
    }

    /**
     * Sort pages based on their namespace level first, then on their string
     * values. This makes higher hierarchy pages rank higher than lower hierarchy
     * pages.
     *
     * @param string $a
     * @param string $b
     * @return int Returns < 0 if $a is less than $b; > 0 if $a is greater than $b,
     *             and 0 if they are equal.
     */
    protected function pagesorter($a, $b)
    {
        $ac = count(explode(':',$a));
        $bc = count(explode(':',$b));
        if ($ac < $bc) {
            return -1;
        } elseif ($ac > $bc) {
            return 1;
        }
        return strcmp ($a,$b);
    }

    /**
     * @param array      $results search results in the form pageid => value
     * @param int|string $after   only returns results with mtime after this date,
     *                            accepts timestap or strtotime arguments
     * @param int|string $before  only returns results with mtime after this date,
     *                            accepts timestap or strtotime arguments
     *
     * @return array
     */
    protected function filterResultsByTime(array $results, $after, $before)
    {
        if ($after || $before) {
            $after = is_int($after) ? $after : strtotime($after);
            $before = is_int($before) ? $before : strtotime($before);

            foreach ($results as $id => $value) {
                $mTime = filemtime(wikiFN($id));
                if ($after && $after > $mTime) {
                    unset($results[$id]);
                    continue;
                }
                if ($before && $before < $mTime) {
                    unset($results[$id]);
                }
            }
        }
        return $results;
    }
}
