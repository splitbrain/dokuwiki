<?php

namespace dokuwiki\Action;

use dokuwiki\Action\Exception\ActionAbort;
use dokuwiki\Search\FulltextSearch;
use dokuwiki\Search\MetadataSearch;
use dokuwiki\Search\QueryParser;

/**
 * Class Search
 *
 * Search for pages and content
 *
 * @package dokuwiki\Action
 */
class Search extends AbstractAction {

    protected $pageLookupResults = array();
    protected $fullTextResults = array();
    protected $highlight = array();

    /** @inheritdoc */
    public function minimumPermission() {
        return AUTH_NONE;
    }

    /**
     * we only search if a search word was given
     *
     * @inheritdoc
     */
    public function checkPreconditions() {
        parent::checkPreconditions();
    }

    public function preProcess()
    {
        global $QUERY, $ID, $conf, $INPUT;
        $s = cleanID($QUERY);

        if ($ID !== $conf['start'] && !$INPUT->has('q')) {
            parse_str($INPUT->server->str('QUERY_STRING'), $urlParts);
            $urlParts['q'] = $urlParts['id'];
            unset($urlParts['id']);
            $url = wl($ID, $urlParts, true, '&');
            send_redirect($url);
        }

        if ($s === '') throw new ActionAbort();
        $this->adjustGlobalQuery();
    }

    /** @inheritdoc */
    public function tplContent()
    {
        $this->execute();

        $search = new \dokuwiki\Ui\Search($this->pageLookupResults, $this->fullTextResults, $this->highlight);
        $search->show();
    }


    /**
     * run the search
     */
    protected function execute()
    {
        global $INPUT, $QUERY;
        $after = $INPUT->str('min');
        $before = $INPUT->str('max');
        $this->pageLookupResults = (new MetadataSearch)->pageLookup(
                $QUERY, true, useHeading('navigation'), $after, $before
        );
        $this->fullTextResults = (new FulltextSearch)->pageSearch(
                $QUERY, $highlight, $INPUT->str('srt'), $after, $before
        );
        $this->highlight = $highlight;
    }

    /**
     * Adjust the global query accordingly to the config search_nslimit and search_fragment
     *
     * This will only do something if the search didn't originate from the form on the searchpage itself
     */
    protected function adjustGlobalQuery()
    {
        global $conf, $INPUT, $QUERY, $ID;

        if ($INPUT->bool('sf')) {
            return;
        }

        $parsedQuery = (new QueryParser)->convert($QUERY);

        if (empty($parsedQuery['ns']) && empty($parsedQuery['notns'])) {
            if ($conf['search_nslimit'] > 0) {
                if (getNS($ID) !== false) {
                    $nsParts = explode(':', getNS($ID));
                    $ns = implode(':', array_slice($nsParts, 0, $conf['search_nslimit']));
                    $QUERY .= " @$ns";
                }
            }
        }

        if ($conf['search_fragment'] !== 'exact') {
            if (empty(array_diff($parsedQuery['words'], $parsedQuery['and']))) {
                if (strpos($QUERY, '*') === false) {
                    $queryParts = explode(' ', $QUERY);
                    $queryParts = array_map(function ($part) {
                        if (strpos($part, '@') === 0) {
                            return $part;
                        }
                        if (strpos($part, 'ns:') === 0) {
                            return $part;
                        }
                        if (strpos($part, '^') === 0) {
                            return $part;
                        }
                        if (strpos($part, '-ns:') === 0) {
                            return $part;
                        }

                        global $conf;

                        if ($conf['search_fragment'] === 'starts_with') {
                            return $part . '*';
                        }
                        if ($conf['search_fragment'] === 'ends_with') {
                            return '*' . $part;
                        }

                        return '*' . $part . '*';

                    }, $queryParts);
                    $QUERY = implode(' ', $queryParts);
                }
            }
        }
    }
}
