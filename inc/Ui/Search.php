<?php

namespace dokuwiki\Ui;

use dokuwiki\Extension\Event;
use dokuwiki\Form\Form;
use dokuwiki\Search\FulltextSearch;
use dokuwiki\Search\QueryParser;
use dokuwiki\Utf8\Sort;

use const dokuwiki\Search\FT_SNIPPET_NUMBER;


class Search extends Ui
{
    protected $query;
    protected $parsedQuery;
    protected $searchState;
    protected $pageLookupResults = array();
    protected $fullTextResults = array();
    protected $highlight = array();

    /**
     * Search constructor.
     *
     * @param array $pageLookupResults pagename lookup results in the form [pagename => pagetitle]
     * @param array $fullTextResults fulltext search results in the form [pagename => #hits]
     * @param array $highlight  array of strings to be highlighted
     */
    public function __construct(array $pageLookupResults, array $fullTextResults, $highlight)
    {
        global $QUERY;

        $this->query = $QUERY;
        $this->parsedQuery = (new QueryParser)->convert($QUERY);
        $this->searchState = new SearchState($this->parsedQuery);

        $this->pageLookupResults = $pageLookupResults;
        $this->fullTextResults = $fullTextResults;
        $this->highlight = $highlight;
    }

    /**
     * display the search result
     *
     * @return void
     */
    public function show()
    {
        $searchHTML = '';

        $searchHTML .= $this->getSearchIntroHTML($this->query);

        $searchHTML .= $this->getSearchFormHTML($this->query);

        $searchHTML .= $this->getPageLookupHTML($this->pageLookupResults);

        $searchHTML .= $this->getFulltextResultsHTML($this->fullTextResults, $this->highlight);

        echo $searchHTML;
    }

    /**
     * Get a form which can be used to adjust/refine the search
     *
     * @param string $query
     *
     * @return string
     */
    protected function getSearchFormHTML($query)
    {
        global $lang, $ID, $INPUT;

        $searchForm = (new Form(['method' => 'get'], true))->addClass('search-results-form');
        $searchForm->setHiddenField('do', 'search');
        $searchForm->setHiddenField('id', $ID);
        $searchForm->setHiddenField('sf', '1');
        if ($INPUT->has('min')) {
            $searchForm->setHiddenField('min', $INPUT->str('min'));
        }
        if ($INPUT->has('max')) {
            $searchForm->setHiddenField('max', $INPUT->str('max'));
        }
        if ($INPUT->has('srt')) {
            $searchForm->setHiddenField('srt', $INPUT->str('srt'));
        }
        $searchForm->addFieldsetOpen()->addClass('search-form');
        $searchForm->addTextInput('q')->val($query)->useInput(false);
        $searchForm->addButton('', $lang['btn_search'])->attr('type', 'submit');

        $this->addSearchAssistanceElements($searchForm);

        $searchForm->addFieldsetClose();

        return $searchForm->toHTML('Search');
    }

    /**
     * Add elements to adjust how the results are sorted
     *
     * @param Form $searchForm
     */
    protected function addSortTool(Form $searchForm)
    {
        global $INPUT, $lang;

        $options = [
            'hits' => [
                'label' => $lang['search_sort_by_hits'],
                'sort' => '',
            ],
            'mtime' => [
                'label' => $lang['search_sort_by_mtime'],
                'sort' => 'mtime',
            ],
        ];
        $activeOption = 'hits';

        if ($INPUT->str('srt') === 'mtime') {
            $activeOption = 'mtime';
        }

        $searchForm->addTagOpen('div')->addClass('toggle')->attr('aria-haspopup', 'true');
        // render current
        $currentWrapper = $searchForm->addTagOpen('div')->addClass('current');
        if ($activeOption !== 'hits') {
            $currentWrapper->addClass('changed');
        }
        $searchForm->addHTML($options[$activeOption]['label']);
        $searchForm->addTagClose('div');

        // render options list
        $searchForm->addTagOpen('ul')->attr('aria-expanded', 'false');

        foreach ($options as $key => $option) {
            $listItem = $searchForm->addTagOpen('li');

            if ($key === $activeOption) {
                $listItem->addClass('active');
                $searchForm->addHTML($option['label']);
            } else {
                $link = $this->searchState->withSorting($option['sort'])->getSearchLink($option['label']);
                $searchForm->addHTML($link);
            }
            $searchForm->addTagClose('li');
        }
        $searchForm->addTagClose('ul');

        $searchForm->addTagClose('div');

    }

    /**
     * Check if the query is simple enough to modify its namespace limitations without breaking the rest of the query
     *
     * @param array $parsedQuery
     *
     * @return bool
     */
    protected function isNamespaceAssistanceAvailable(array $parsedQuery) {
        if (preg_match('/[\(\)\|]/', $parsedQuery['query']) === 1) {
            return false;
        }

        return true;
    }

    /**
     * Check if the query is simple enough to modify the fragment search behavior without breaking the rest of the query
     *
     * @param array $parsedQuery
     *
     * @return bool
     */
    protected function isFragmentAssistanceAvailable(array $parsedQuery) {
        if (preg_match('/[\(\)\|]/', $parsedQuery['query']) === 1) {
            return false;
        }

        if (!empty($parsedQuery['phrases'])) {
            return false;
        }

        return true;
    }

    /**
     * Add the elements to be used for search assistance
     *
     * @param Form $searchForm
     */
    protected function addSearchAssistanceElements(Form $searchForm)
    {
        $searchForm->addTagOpen('div')
            ->addClass('advancedOptions')
            ->attr('style', 'display: none;')
            ->attr('aria-hidden', 'true');

        $this->addFragmentBehaviorLinks($searchForm);
        $this->addNamespaceSelector($searchForm);
        $this->addDateSelector($searchForm);
        $this->addSortTool($searchForm);

        $searchForm->addTagClose('div');
    }

    /**
     *  Add the elements to adjust the fragment search behavior
     *
     * @param Form $searchForm
     */
    protected function addFragmentBehaviorLinks(Form $searchForm)
    {
        if (!$this->isFragmentAssistanceAvailable($this->parsedQuery)) {
            return;
        }
        global $lang;

        $options = [
            'exact' => [
                'label' => $lang['search_exact_match'],
                'and' => array_map(function ($term) {
                    return trim($term, '*');
                }, $this->parsedQuery['and']),
                'not' => array_map(function ($term) {
                    return trim($term, '*');
                }, $this->parsedQuery['not']),
            ],
            'starts' => [
                'label' => $lang['search_starts_with'],
                'and' => array_map(function ($term) {
                    return trim($term, '*') . '*';
                }, $this->parsedQuery['and']),
                'not' => array_map(function ($term) {
                    return trim($term, '*') . '*';
                }, $this->parsedQuery['not']),
            ],
            'ends' => [
                'label' => $lang['search_ends_with'],
                'and' => array_map(function ($term) {
                    return '*' . trim($term, '*');
                }, $this->parsedQuery['and']),
                'not' => array_map(function ($term) {
                    return '*' . trim($term, '*');
                }, $this->parsedQuery['not']),
            ],
            'contains' => [
                'label' => $lang['search_contains'],
                'and' => array_map(function ($term) {
                    return '*' . trim($term, '*') . '*';
                }, $this->parsedQuery['and']),
                'not' => array_map(function ($term) {
                    return '*' . trim($term, '*') . '*';
                }, $this->parsedQuery['not']),
            ]
        ];

        // detect current
        $activeOption = 'custom';
        foreach ($options as $key => $option) {
            if ($this->parsedQuery['and'] === $option['and']) {
                $activeOption = $key;
            }
        }
        if ($activeOption === 'custom') {
            $options = array_merge(['custom' => [
                'label' => $lang['search_custom_match'],
            ]], $options);
        }

        $searchForm->addTagOpen('div')->addClass('toggle')->attr('aria-haspopup', 'true');
        // render current
        $currentWrapper = $searchForm->addTagOpen('div')->addClass('current');
        if ($activeOption !== 'exact') {
            $currentWrapper->addClass('changed');
        }
        $searchForm->addHTML($options[$activeOption]['label']);
        $searchForm->addTagClose('div');

        // render options list
        $searchForm->addTagOpen('ul')->attr('aria-expanded', 'false');

        foreach ($options as $key => $option) {
            $listItem = $searchForm->addTagOpen('li');

            if ($key === $activeOption) {
                $listItem->addClass('active');
                $searchForm->addHTML($option['label']);
            } else {
                $link = $this->searchState
                    ->withFragments($option['and'], $option['not'])
                    ->getSearchLink($option['label'])
                ;
                $searchForm->addHTML($link);
            }
            $searchForm->addTagClose('li');
        }
        $searchForm->addTagClose('ul');

        $searchForm->addTagClose('div');

        // render options list
    }

    /**
     * Add the elements for the namespace selector
     *
     * @param Form $searchForm
     */
    protected function addNamespaceSelector(Form $searchForm)
    {
        if (!$this->isNamespaceAssistanceAvailable($this->parsedQuery)) {
            return;
        }

        global $lang;

        $baseNS = empty($this->parsedQuery['ns']) ? '' : $this->parsedQuery['ns'][0];
        $extraNS = $this->getAdditionalNamespacesFromResults($baseNS);

        $searchForm->addTagOpen('div')->addClass('toggle')->attr('aria-haspopup', 'true');
        // render current
        $currentWrapper = $searchForm->addTagOpen('div')->addClass('current');
        if ($baseNS) {
            $currentWrapper->addClass('changed');
            $searchForm->addHTML('@' . $baseNS);
        } else {
            $searchForm->addHTML($lang['search_any_ns']);
        }
        $searchForm->addTagClose('div');

        // render options list
        $searchForm->addTagOpen('ul')->attr('aria-expanded', 'false');

        $listItem = $searchForm->addTagOpen('li');
        if ($baseNS) {
            $listItem->addClass('active');
            $link = $this->searchState->withNamespace('')->getSearchLink($lang['search_any_ns']);
            $searchForm->addHTML($link);
        } else {
            $searchForm->addHTML($lang['search_any_ns']);
        }
        $searchForm->addTagClose('li');

        foreach ($extraNS as $ns => $count) {
            $listItem = $searchForm->addTagOpen('li');
            $label = $ns . ($count ? " <bdi>($count)</bdi>" : '');

            if ($ns === $baseNS) {
                $listItem->addClass('active');
                $searchForm->addHTML($label);
            } else {
                $link = $this->searchState->withNamespace($ns)->getSearchLink($label);
                $searchForm->addHTML($link);
            }
            $searchForm->addTagClose('li');
        }
        $searchForm->addTagClose('ul');

        $searchForm->addTagClose('div');

    }

    /**
     * Parse the full text results for their top namespaces below the given base namespace
     *
     * @param string $baseNS the namespace within which was searched, empty string for root namespace
     *
     * @return array an associative array with namespace => #number of found pages, sorted descending
     */
    protected function getAdditionalNamespacesFromResults($baseNS)
    {
        $namespaces = [];
        $baseNSLength = strlen($baseNS);
        foreach ($this->fullTextResults as $page => $numberOfHits) {
            $namespace = getNS($page);
            if (!$namespace) {
                continue;
            }
            if ($namespace === $baseNS) {
                continue;
            }
            $firstColon = strpos((string)$namespace, ':', $baseNSLength + 1) ?: strlen($namespace);
            $subtopNS = substr($namespace, 0, $firstColon);
            if (empty($namespaces[$subtopNS])) {
                $namespaces[$subtopNS] = 0;
            }
            $namespaces[$subtopNS] += 1;
        }
        Sort::ksort($namespaces);
        arsort($namespaces);
        return $namespaces;
    }

    /**
     * @ToDo: custom date input
     *
     * @param Form $searchForm
     */
    protected function addDateSelector(Form $searchForm)
    {
        global $INPUT, $lang;

        $options = [
            'any' => [
                'before' => false,
                'after' => false,
                'label' => $lang['search_any_time'],
            ],
            'week' => [
                'before' => false,
                'after' => '1 week ago',
                'label' => $lang['search_past_7_days'],
            ],
            'month' => [
                'before' => false,
                'after' => '1 month ago',
                'label' => $lang['search_past_month'],
            ],
            'year' => [
                'before' => false,
                'after' => '1 year ago',
                'label' => $lang['search_past_year'],
            ],
        ];
        $activeOption = 'any';
        foreach ($options as $key => $option) {
            if ($INPUT->str('min') === $option['after']) {
                $activeOption = $key;
                break;
            }
        }

        $searchForm->addTagOpen('div')->addClass('toggle')->attr('aria-haspopup', 'true');
        // render current
        $currentWrapper = $searchForm->addTagOpen('div')->addClass('current');
        if ($INPUT->has('max') || $INPUT->has('min')) {
            $currentWrapper->addClass('changed');
        }
        $searchForm->addHTML($options[$activeOption]['label']);
        $searchForm->addTagClose('div');

        // render options list
        $searchForm->addTagOpen('ul')->attr('aria-expanded', 'false');

        foreach ($options as $key => $option) {
            $listItem = $searchForm->addTagOpen('li');

            if ($key === $activeOption) {
                $listItem->addClass('active');
                $searchForm->addHTML($option['label']);
            } else {
                $link = $this->searchState
                    ->withTimeLimitations($option['after'], $option['before'])
                    ->getSearchLink($option['label'])
                ;
                $searchForm->addHTML($link);
            }
            $searchForm->addTagClose('li');
        }
        $searchForm->addTagClose('ul');

        $searchForm->addTagClose('div');
    }


    /**
     * Build the intro text for the search page
     *
     * @param string $query the search query
     *
     * @return string
     */
    protected function getSearchIntroHTML($query)
    {
        global $lang;

        $intro = p_locale_xhtml('searchpage');

        $queryPagename = $this->createPagenameFromQuery($this->parsedQuery);
        $createQueryPageLink = html_wikilink($queryPagename . '?do=edit', $queryPagename);

        $pagecreateinfo = '';
        if (auth_quickaclcheck($queryPagename) >= AUTH_CREATE) {
            $pagecreateinfo = sprintf($lang['searchcreatepage'], $createQueryPageLink);
        }
        $intro = str_replace(
            array('@QUERY@', '@SEARCH@', '@CREATEPAGEINFO@'),
            array(hsc(rawurlencode($query)), hsc($query), $pagecreateinfo),
            $intro
        );

        return $intro;
    }

    /**
     * Create a pagename based the parsed search query
     *
     * @param array $parsedQuery
     *
     * @return string pagename constructed from the parsed query
     */
    public function createPagenameFromQuery($parsedQuery)
    {
        $cleanedQuery = cleanID($parsedQuery['query']); // already strtolowered
        if ($cleanedQuery === \dokuwiki\Utf8\PhpString::strtolower($parsedQuery['query'])) {
            return ':' . $cleanedQuery;
        }
        $pagename = '';
        if (!empty($parsedQuery['ns'])) {
            $pagename .= ':' . cleanID($parsedQuery['ns'][0]);
        }
        $pagename .= ':' . cleanID(implode(' ' , $parsedQuery['highlight']));
        return $pagename;
    }

    /**
     * Build HTML for a list of pages with matching pagenames
     *
     * @param array $data search results
     *
     * @return string
     */
    protected function getPageLookupHTML($data)
    {
        if (empty($data)) {
            return '';
        }

        global $lang;

        $html = '<div class="search_quickresult">';
        $html .= '<h2>' . $lang['quickhits'] . ':</h2>';
        $html .= '<ul class="search_quickhits">';
        foreach ($data as $id => $title) {
            $name = null;
            if (!useHeading('navigation') && $ns = getNS($id)) {
                $name = shorten(noNS($id), ' (' . $ns . ')', 30);
            }
            $link = html_wikilink(':' . $id, $name);
            $eventData = [
                'listItemContent' => [$link],
                'page' => $id,
            ];
            Event::createAndTrigger('SEARCH_RESULT_PAGELOOKUP', $eventData);
            $html .= '<li>' . implode('', $eventData['listItemContent']) . '</li>';
        }
        $html .= '</ul> ';
        //clear float (see http://www.complexspiral.com/publications/containing-floats/)
        $html .= '<div class="clearer"></div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Build HTML for fulltext search results or "no results" message
     *
     * @param array $data      the results of the fulltext search
     * @param array $highlight the terms to be highlighted in the results
     *
     * @return string
     */
    protected function getFulltextResultsHTML($data, $highlight)
    {
        global $lang;

        if (empty($data)) {
            return '<div class="nothing">' . $lang['nothingfound'] . '</div>';
        }

        $html = '<div class="search_fulltextresult">';
        $html .= '<h2>' . $lang['search_fullresults'] . ':</h2>';

        $html .= '<dl class="search_results">';
        $num = 0;
        $position = 0;
        $FulltextSearch = new FulltextSearch();

        foreach ($data as $id => $cnt) {
            $position += 1;
            $resultLink = html_wikilink(':' . $id, null, $highlight);

            $resultHeader = [$resultLink];


            $restrictQueryToNSLink = $this->restrictQueryToNSLink(getNS($id));
            if ($restrictQueryToNSLink) {
                $resultHeader[] = $restrictQueryToNSLink;
            }

            $resultBody = [];
            $mtime = filemtime(wikiFN($id));
            $lastMod = '<span class="lastmod">' . $lang['lastmod'] . '</span> ';
            $lastMod .= '<time datetime="' . date_iso8601($mtime) . '" title="' . dformat($mtime) . '">' .
                dformat($mtime, '%f') .
                '</time>';
            $resultBody['meta'] = $lastMod;
            if ($cnt !== 0) {
                $num++;
                $hits = '<span class="hits">' . $cnt . ' ' . $lang['hits'] . '</span>, ';
                $resultBody['meta'] = $hits . $resultBody['meta'];
                if ($num <= FT_SNIPPET_NUMBER) { // create snippets for the first number of matches only
                    $resultBody['snippet'] = $FulltextSearch->snippet($id, $highlight);
                }
            }

            $eventData = [
                'resultHeader' => $resultHeader,
                'resultBody' => $resultBody,
                'page' => $id,
                'position' => $position,
            ];
            Event::createAndTrigger('SEARCH_RESULT_FULLPAGE', $eventData);
            $html .= '<div class="search_fullpage_result">';
            $html .= '<dt>' . implode(' ', $eventData['resultHeader']) . '</dt>';
            foreach ($eventData['resultBody'] as $class => $htmlContent) {
                $html .= "<dd class=\"$class\">$htmlContent</dd>";
            }
            $html .= '</div>';
        }
        $html .= '</dl>';

        $html .= '</div>';

        return $html;
    }

    /**
     * create a link to restrict the current query to a namespace
     *
     * @param false|string $ns the namespace to which to restrict the query
     *
     * @return false|string
     */
    protected function restrictQueryToNSLink($ns)
    {
        if (!$ns) {
            return false;
        }
        if (!$this->isNamespaceAssistanceAvailable($this->parsedQuery)) {
            return false;
        }
        if (!empty($this->parsedQuery['ns']) && $this->parsedQuery['ns'][0] === $ns) {
            return false;
        }

        $name = '@' . $ns;
        return $this->searchState->withNamespace($ns)->getSearchLink($name);
    }
}
