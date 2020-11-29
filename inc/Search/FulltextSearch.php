<?php

namespace dokuwiki\Search;

use dokuwiki\Extension\Event;
use dokuwiki\Utf8;

// create snippets for the first few results only
const FT_SNIPPET_NUMBER = 15;

/**
 * Class DokuWiki Fulltext Search
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 */
class FulltextSearch
{
    /**
     * The fulltext search
     *
     * Returns a list of matching documents for the given query
     *
     * refactored into pageSearch(), pageSearchCallBack() and trigger_event()
     *
     * @param string     $query
     * @param array      $highlight
     * @param string     $sort
     * @param int|string $after  only show results with mtime after this date,
     *                           accepts timestap or strtotime arguments
     * @param int|string $before only show results with mtime before this date,
     *                           accepts timestap or strtotime arguments
     *
     * @return array
     */
    public function pageSearch($query, &$highlight, $sort = null, $after = null, $before = null)
    {
        if ($sort === null) {
            $sort = 'hits';
        }
        $data = [
            'query' => $query,
            'sort' => $sort,
            'after' => $after,
            'before' => $before
        ];
        $data['highlight'] =& $highlight;
        $action = [$this, 'pageSearchCallBack'];
        return Event::createAndTrigger('SEARCH_QUERY_FULLPAGE', $data, $action);
    }

    /**
     * Returns a list of matching documents for the given query
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     * @author Kazutaka Miyasaka <kazmiya@gmail.com>
     *
     * @param array $data  event data
     * @return array       matching documents
     */
    public function pageSearchCallBack(&$data)
    {
        // parse the given query
        $q = (new QueryParser)->convert($data['query']);
        $data['highlight'] = $q['highlight'];

        if (empty($q['parsed_ary'])) return array();

        // lookup all words found in the query
        $FulltextIndex = new FulltextIndex();
        $lookup = $FulltextIndex->lookupWords($q['words']);

        // get all pages in this dokuwiki site (!: includes nonexistent pages)
        $pages_all = array();
        foreach ($FulltextIndex->getPages() as $id) {
            $pages_all[$id] = 0; // base: 0 hit
        }

        // process the query
        $stack = array();
        foreach ($q['parsed_ary'] as $token) {
            switch (substr($token, 0, 3)) {
                case 'W+:':
                case 'W-:':
                case 'W_:': // word
                    $word    = substr($token, 3);
                    $stack[] = (array) $lookup[$word];
                    break;
                case 'P+:':
                case 'P-:': // phrase
                    $phrase = substr($token, 3);
                    // since phrases are always parsed as ((W1)(W2)...(P)),
                    // the end($stack) always points the pages that contain
                    // all words in this phrase
                    $pages  = end($stack);
                    $pages_matched = array();
                    foreach (array_keys($pages) as $id) {
                        $evdata = array(
                            'id' => $id,
                            'phrase' => $phrase,
                            'text' => rawWiki($id)
                        );
                        $event = new Event('FULLTEXT_PHRASE_MATCH', $evdata);
                        if ($event->advise_before() && $event->result !== true) {
                            $text = Utf8\PhpString::strtolower($evdata['text']);
                            if (strpos($text, $phrase) !== false) {
                                $event->result = true;
                            }
                        }
                        $event->advise_after();
                        if ($event->result === true) {
                            $pages_matched[$id] = 0; // phrase: always 0 hit
                        }
                    }
                    $stack[] = $pages_matched;
                    break;
                case 'N+:':
                case 'N-:': // namespace
                    $ns = cleanID(substr($token, 3)) . ':';
                    $pages_matched = array();
                    foreach (array_keys($pages_all) as $id) {
                        if (strpos($id, $ns) === 0) {
                            $pages_matched[$id] = 0; // namespace: always 0 hit
                        }
                    }
                    $stack[] = $pages_matched;
                    break;
                case 'AND': // and operation
                    list($pages1, $pages2) = array_splice($stack, -2);
                    $stack[] = $this->resultCombine(array($pages1, $pages2));
                    break;
                case 'OR':  // or operation
                    list($pages1, $pages2) = array_splice($stack, -2);
                    $stack[] = $this->resultUnite(array($pages1, $pages2));
                    break;
                case 'NOT': // not operation (unary)
                    $pages   = array_pop($stack);
                    $stack[] = $this->resultComplement(array($pages_all, $pages));
                    break;
            }
        }
        $docs = array_pop($stack);

        if (empty($docs)) return array();

        // check: settings, acls, existence
        foreach (array_keys($docs) as $id) {
            if (isHiddenPage($id)
                || auth_quickaclcheck($id) < AUTH_READ
                || !page_exists($id, '', false)
            ) {
                unset($docs[$id]);
            }
        }

        $docs = $this->filterResultsByTime($docs, $data['after'], $data['before']);

        if ($data['sort'] === 'mtime') {
            uksort($docs, [$this, 'pagemtimesorter']);
        } else {
            // sort docs by count
            arsort($docs);
        }

        return $docs;
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

    /**
     * Sort pages by their mtime, from newest to oldest
     *
     * @param string $a
     * @param string $b
     *
     * @return int Returns < 0 if $a is newer than $b, > 0 if $b is newer than $a
     *             and 0 if they are of the same age
     */
    protected function pagemtimesorter($a, $b)
    {
        $mtimeA = filemtime(wikiFN($a));
        $mtimeB = filemtime(wikiFN($b));
        return $mtimeB - $mtimeA;
    }

    /**
     * Creates a snippet extract
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     * @triggers FULLTEXT_SNIPPET_CREATE
     *
     * @param string $id page id
     * @param array $highlight
     * @return mixed
     */
    public function snippet($id, $highlight)
    {
        $text = rawWiki($id);
        $text = str_replace("\xC2\xAD",'',$text); // remove soft-hyphens
        $evdata = array(
            'id'        => $id,
            'text'      => &$text,
            'highlight' => &$highlight,
            'snippet'   => '',
        );

        $evt = new Event('FULLTEXT_SNIPPET_CREATE', $evdata);
        if ($evt->advise_before()) {
            $match = array();
            $snippets = array();
            $utf8_offset = $offset = $end = 0;
            $len = Utf8\PhpString::strlen($text);

            // build a regexp from the phrases to highlight
            $re1 = '(' .
                join(
                    '|',
                    array_map(
                        [$this, 'snippetRePreprocess'],
                        array_map(
                            'preg_quote_cb',
                            array_filter((array) $highlight)
                        )
                    )
                ) .
                ')';
            $re2 = "$re1.{0,75}(?!\\1)$re1";
            $re3 = "$re1.{0,45}(?!\\1)$re1.{0,45}(?!\\1)(?!\\2)$re1";

            for ($cnt=4; $cnt--;) {
                if (0) {
                } elseif (preg_match('/'.$re3.'/iu', $text, $match, PREG_OFFSET_CAPTURE, $offset)) {
                } elseif (preg_match('/'.$re2.'/iu', $text, $match, PREG_OFFSET_CAPTURE, $offset)) {
                } elseif (preg_match('/'.$re1.'/iu', $text, $match, PREG_OFFSET_CAPTURE, $offset)) {
                } else {
                    break;
                }

                list($str, $idx) = $match[0];

                // convert $idx (a byte offset) into a utf8 character offset
                $utf8_idx = Utf8\PhpString::strlen(substr($text, 0, $idx));
                $utf8_len = Utf8\PhpString::strlen($str);

                // establish context, 100 bytes surrounding the match string
                // first look to see if we can go 100 either side,
                // then drop to 50 adding any excess if the other side can't go to 50,
                $pre = min($utf8_idx - $utf8_offset, 100);
                $post = min($len - $utf8_idx - $utf8_len, 100);

                if ($pre > 50 && $post > 50) {
                    $pre = $post = 50;
                } elseif ($pre > 50) {
                    $pre = min($pre, 100 - $post);
                } elseif ($post > 50) {
                    $post = min($post, 100 - $pre);
                } elseif ($offset == 0) {
                    // both are less than 50, means the context is the whole string
                    // make it so and break out of this loop - there is no need for the
                    // complex snippet calculations
                    $snippets = array($text);
                    break;
                }

                // establish context start and end points, try to append to previous
                // context if possible
                $start = $utf8_idx - $pre;
                $append = ($start < $end) ? $end : false;  // still the end of the previous context snippet
                $end = $utf8_idx + $utf8_len + $post;      // now set it to the end of this context

                if ($append) {
                    $snippets[count($snippets)-1] .= Utf8\PhpString::substr($text, $append, $end-$append);
                } else {
                    $snippets[] = Utf8\PhpString::substr($text, $start, $end-$start);
                }

                // set $offset for next match attempt
                // continue matching after the current match
                // if the current match is not the longest possible match starting at the current offset
                // this prevents further matching of this snippet but for possible matches of length
                // smaller than match length + context (at least 50 characters) this match is part of the context
                $utf8_offset = $utf8_idx + $utf8_len;
                $offset = $idx + strlen(Utf8\PhpString::substr($text, $utf8_idx, $utf8_len));
                $offset = Utf8\Clean::correctIdx($text, $offset);
            }

            $m = "\1";
            $snippets = preg_replace('/'.$re1.'/iu', $m.'$1'.$m, $snippets);
            $snippet = preg_replace(
                '/' . $m . '([^' . $m . ']*?)' . $m . '/iu',
                '<strong class="search_hit">$1</strong>',
                hsc(join('... ', $snippets))
            );

            $evdata['snippet'] = $snippet;
        }
        $evt->advise_after();
        unset($evt);

        return $evdata['snippet'];
    }

    /**
     * Wraps a search term in regex boundary checks.
     *
     * @param string $term
     * @return string
     */
    public function snippetRePreprocess($term)
    {
        // do not process asian terms where word boundaries are not explicit
        if (Utf8\Asian::isAsianWords($term)) return $term;

        if (UTF8_PROPERTYSUPPORT) {
            // unicode word boundaries
            // see http://stackoverflow.com/a/2449017/172068
            $BL = '(?<!\pL)';
            $BR = '(?!\pL)';
        } else {
            // not as correct as above, but at least won't break
            $BL = '\b';
            $BR = '\b';
        }

        if (substr($term, 0, 2) == '\\*') {
            $term = substr($term, 2);
        } else {
            $term = $BL.$term;
        }

        if (substr($term, -2, 2) == '\\*') {
            $term = substr($term, 0, -2);
        } else {
            $term = $term.$BR;
        }

        if ($term == $BL || $term == $BR || $term == $BL.$BR) {
            $term = '';
        }
        return $term;
    }

    /**
     * Combine found documents and sum up their scores
     *
     * This function is used to combine searched words with a logical
     * AND. Only documents available in all arrays are returned.
     *
     * based upon PEAR's PHP_Compat function for array_intersect_key()
     *
     * @param array $args An array of page arrays
     * @return array
     */
    protected function resultCombine($args)
    {
        $array_count = count($args);
        if ($array_count == 1) {
            return $args[0];
        }

        $result = array();
        if ($array_count > 1) {
            foreach ($args[0] as $key => $value) {
                $result[$key] = $value;
                for ($i = 1; $i !== $array_count; $i++) {
                    if (!isset($args[$i][$key])) {
                        unset($result[$key]);
                        break;
                    }
                    $result[$key] += $args[$i][$key];
                }
            }
        }
        return $result;
    }

    /**
     * Unites found documents and sum up their scores
     * based upon resultCombine() method
     *
     * @param array $args An array of page arrays
     * @return array
     *
     * @author Kazutaka Miyasaka <kazmiya@gmail.com>
     */
    protected function resultUnite($args)
    {
        $array_count = count($args);
        if ($array_count === 1) {
            return $args[0];
        }

        $result = $args[0];
        for ($i = 1; $i !== $array_count; $i++) {
            foreach (array_keys($args[$i]) as $id) {
                $result[$id] += $args[$i][$id];
            }
        }
        return $result;
    }

    /**
     * Computes the difference of documents using page id for comparison
     * nearly identical to PHP5's array_diff_key()
     *
     * @param array $args An array of page arrays
     * @return array
     *
     * @author Kazutaka Miyasaka <kazmiya@gmail.com>
     */
    protected function resultComplement($args)
    {
        $array_count = count($args);
        if ($array_count === 1) {
            return $args[0];
        }

        $result = $args[0];
        foreach (array_keys($result) as $id) {
            for ($i = 1; $i !== $array_count; $i++) {
                if (isset($args[$i][$id])) unset($result[$id]);
            }
        }
        return $result;
    }
}
