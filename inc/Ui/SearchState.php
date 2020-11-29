<?php

namespace dokuwiki\Ui;

use dokuwiki\Search\QueryParser;

class SearchState
{
    /**
     * @var array
     */
    protected $parsedQuery = [];

    /**
     * SearchState constructor.
     *
     * @param array $parsedQuery
     */
    public function __construct(array $parsedQuery)
    {
        global $INPUT;

        $this->parsedQuery = $parsedQuery;
        if (!isset($parsedQuery['after'])) {
            $this->parsedQuery['after'] = $INPUT->str('min');
        }
        if (!isset($parsedQuery['before'])) {
            $this->parsedQuery['before'] = $INPUT->str('max');
        }
        if (!isset($parsedQuery['sort'])) {
            $this->parsedQuery['sort'] = $INPUT->str('srt');
        }
    }

    /**
     * Get a search state for the current search limited to a new namespace
     *
     * @param string $ns the namespace to which to limit the search, falsy to remove the limitation
     * @param array  $notns
     *
     * @return SearchState
     */
    public function withNamespace($ns, array $notns = [])
    {
        $parsedQuery = $this->parsedQuery;
        $parsedQuery['ns'] = $ns ? [$ns] : [];
        $parsedQuery['notns'] = $notns;

        return new SearchState($parsedQuery);
    }

    /**
     * Get a search state for the current search with new search fragments and optionally phrases
     *
     * @param array $and
     * @param array $not
     * @param array $phrases
     *
     * @return SearchState
     */
    public function withFragments(array $and, array $not, array $phrases = [])
    {
        $parsedQuery = $this->parsedQuery;
        $parsedQuery['and'] = $and;
        $parsedQuery['not'] = $not;
        $parsedQuery['phrases'] = $phrases;

        return new SearchState($parsedQuery);
    }

    /**
     * Get a search state for the current search with with adjusted time limitations
     *
     * @param $after
     * @param $before
     *
     * @return SearchState
     */
    public function withTimeLimitations($after, $before)
    {
        $parsedQuery = $this->parsedQuery;
        $parsedQuery['after'] = $after;
        $parsedQuery['before'] = $before;

        return new SearchState($parsedQuery);
    }

    /**
     * Get a search state for the current search with adjusted sort preference
     *
     * @param $sort
     *
     * @return SearchState
     */
    public function withSorting($sort)
    {
        $parsedQuery = $this->parsedQuery;
        $parsedQuery['sort'] = $sort;

        return new SearchState($parsedQuery);
    }

    /**
     * Get a link that represents the current search state
     *
     * Note that this represents only a simplified version of the search state.
     * Grouping with braces and "OR" conditions are not supported.
     *
     * @param $label
     *
     * @return string
     */
    public function getSearchLink($label)
    {
        global $ID, $conf;
        $parsedQuery = $this->parsedQuery;

        $tagAttributes = [
            'target' => $conf['target']['wiki'],
        ];

        $newQuery = (new QueryParser)->revert(
            $parsedQuery['and'],
            $parsedQuery['not'],
            $parsedQuery['phrases'],
            $parsedQuery['ns'],
            $parsedQuery['notns']
        );
        $hrefAttributes = ['do' => 'search', 'sf' => '1', 'q' => $newQuery];
        if ($parsedQuery['after']) {
            $hrefAttributes['min'] = $parsedQuery['after'];
        }
        if ($parsedQuery['before']) {
            $hrefAttributes['max'] = $parsedQuery['before'];
        }
        if ($parsedQuery['sort']) {
            $hrefAttributes['srt'] = $parsedQuery['sort'];
        }

        $href = wl($ID, $hrefAttributes, false, '&');
        return "<a href='$href' " . buildAttributes($tagAttributes, true) . ">$label</a>";
    }
}
