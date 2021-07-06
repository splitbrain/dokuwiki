<?php
/**
 * The MetaData Renderer
 *
 * Metadata is additional information about a DokuWiki page that gets extracted mainly from the page's content
 * but also it's own filesystem data (like the creation time). All metadata is stored in the fields $meta and
 * $persistent.
 *
 * Some simplified rendering to $doc is done to gather the page's (text-only) abstract.
 *
 * @author Esther Brunner <wikidesign@gmail.com>
 */
class Doku_Renderer_metadata extends Doku_Renderer
{
    /** the approximate byte lenght to capture for the abstract */
    const ABSTRACT_LEN = 250;

    /** the maximum UTF8 character length for the abstract */
    const ABSTRACT_MAX = 500;

    /** @var array transient meta data, will be reset on each rendering */
    public $meta = array();

    /** @var array persistent meta data, will be kept until explicitly deleted */
    public $persistent = array();

    /** @var array the list of headers used to create unique link ids */
    protected $headers = array();

    /** @var string temporary $doc store */
    protected $store = '';

    /** @var string keeps the first image reference */
    protected $firstimage = '';

    /** @var bool whether or not data is being captured for the abstract, public to be accessible by plugins */
    public $capturing = true;

    /** @var bool determines if enough data for the abstract was collected, yet */
    public $capture = true;

    /** @var int number of bytes captured for abstract */
    protected $captured = 0;

    /**
     * Returns the format produced by this renderer.
     *
     * @return string always 'metadata'
     */
    public function getFormat()
    {
        return 'metadata';
    }

    /**
     * Initialize the document
     *
     * Sets up some of the persistent info about the page if it doesn't exist, yet.
     */
    public function document_start()
    {
        global $ID;

        $this->headers = array();

        // external pages are missing create date
        if (!isset($this->persistent['date']['created']) || !$this->persistent['date']['created']) {
            $this->persistent['date']['created'] = filectime(wikiFN($ID));
        }
        if (!isset($this->persistent['user'])) {
            $this->persistent['user'] = '';
        }
        if (!isset($this->persistent['creator'])) {
            $this->persistent['creator'] = '';
        }
        // reset metadata to persistent values
        $this->meta = $this->persistent;
    }

    /**
     * Finalize the document
     *
     * Stores collected data in the metadata
     */
    public function document_end()
    {
        global $ID;

        // store internal info in metadata (notoc,nocache)
        $this->meta['internal'] = $this->info;

        if (!isset($this->meta['description']['abstract'])) {
            // cut off too long abstracts
            $this->doc = trim($this->doc);
            if (strlen($this->doc) > self::ABSTRACT_MAX) {
                $this->doc = \dokuwiki\Utf8\PhpString::substr($this->doc, 0, self::ABSTRACT_MAX).'…';
            }
            $this->meta['description']['abstract'] = $this->doc;
        }

        $this->meta['relation']['firstimage'] = $this->firstimage;

        if (!isset($this->meta['date']['modified'])) {
            $this->meta['date']['modified'] = filemtime(wikiFN($ID));
        }
    }

    /**
     * Render plain text data
     *
     * This function takes care of the amount captured data and will stop capturing when
     * enough abstract data is available
     *
     * @param $text
     */
    public function cdata($text)
    {
        if (!$this->capture || !$this->capturing) {
            return;
        }

        $this->doc .= $text;

        $this->captured += strlen($text);
        if ($this->captured > self::ABSTRACT_LEN) {
            $this->capture = false;
        }
    }

    /**
     * Add an item to the TOC
     *
     * @param string $id       the hash link
     * @param string $text     the text to display
     * @param int    $level    the nesting level
     */
    public function toc_additem($id, $text, $level)
    {
        global $conf;

        //only add items within configured levels
        if ($level >= $conf['toptoclevel'] && $level <= $conf['maxtoclevel']) {
            // the TOC is one of our standard ul list arrays ;-)
            $this->meta['description']['tableofcontents'][] = array(
                'hid'   => $id,
                'title' => $text,
                'type'  => 'ul',
                'level' => $level - $conf['toptoclevel'] + 1
            );
        }
    }

    /**
     * Render a heading
     *
     * @param string $text  the text to display
     * @param int    $level header level
     * @param int    $pos   byte position in the original source
     */
    public function header($text, $level, $pos)
    {
        if (!isset($this->meta['title'])) {
            $this->meta['title'] = $text;
        }

        // add the header to the TOC
        $hid = $this->_headerToLink($text, true);
        $this->toc_additem($hid, $text, $level);

        // add to summary
        $this->cdata(DOKU_LF.$text.DOKU_LF);
    }

    /**
     * Open a paragraph
     */
    public function p_open()
    {
        $this->cdata(DOKU_LF);
    }

    /**
     * Close a paragraph
     */
    public function p_close()
    {
        $this->cdata(DOKU_LF);
    }

    /**
     * Create a line break
     */
    public function linebreak()
    {
        $this->cdata(DOKU_LF);
    }

    /**
     * Create a horizontal line
     */
    public function hr()
    {
        $this->cdata(DOKU_LF.'----------'.DOKU_LF);
    }

    /**
     * Callback for footnote start syntax
     *
     * All following content will go to the footnote instead of
     * the document. To achieve this the previous rendered content
     * is moved to $store and $doc is cleared
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     */
    public function footnote_open()
    {
        if ($this->capture) {
            // move current content to store
            // this is required to ensure safe behaviour of plugins accessed within footnotes
            $this->store = $this->doc;
            $this->doc   = '';

            // disable capturing
            $this->capturing = false;
        }
    }

    /**
     * Callback for footnote end syntax
     *
     * All content rendered whilst within footnote syntax mode is discarded,
     * the previously rendered content is restored and capturing is re-enabled.
     *
     * @author Andreas Gohr
     */
    public function footnote_close()
    {
        if ($this->capture) {
            // re-enable capturing
            $this->capturing = true;
            // restore previously rendered content
            $this->doc   = $this->store;
            $this->store = '';
        }
    }

    /**
     * Open an unordered list
     */
    public function listu_open()
    {
        $this->cdata(DOKU_LF);
    }

    /**
     * Open an ordered list
     */
    public function listo_open()
    {
        $this->cdata(DOKU_LF);
    }

    /**
     * Open a list item
     *
     * @param int $level the nesting level
     * @param bool $node true when a node; false when a leaf
     */
    public function listitem_open($level, $node=false)
    {
        $this->cdata(str_repeat(DOKU_TAB, $level).'* ');
    }

    /**
     * Close a list item
     */
    public function listitem_close()
    {
        $this->cdata(DOKU_LF);
    }

    /**
     * Output preformatted text
     *
     * @param string $text
     */
    public function preformatted($text)
    {
        $this->cdata($text);
    }

    /**
     * Start a block quote
     */
    public function quote_open()
    {
        $this->cdata(DOKU_LF.DOKU_TAB.'"');
    }

    /**
     * Stop a block quote
     */
    public function quote_close()
    {
        $this->cdata('"'.DOKU_LF);
    }

    /**
     * Display text as file content, optionally syntax highlighted
     *
     * @param string $text text to show
     * @param string $lang programming language to use for syntax highlighting
     * @param string $file file path label
     */
    public function file($text, $lang = null, $file = null)
    {
        $this->cdata(DOKU_LF.$text.DOKU_LF);
    }

    /**
     * Display text as code content, optionally syntax highlighted
     *
     * @param string $text     text to show
     * @param string $language programming language to use for syntax highlighting
     * @param string $file     file path label
     */
    public function code($text, $language = null, $file = null)
    {
        $this->cdata(DOKU_LF.$text.DOKU_LF);
    }

    /**
     * Format an acronym
     *
     * Uses $this->acronyms
     *
     * @param string $acronym
     */
    public function acronym($acronym)
    {
        $this->cdata($acronym);
    }

    /**
     * Format a smiley
     *
     * Uses $this->smiley
     *
     * @param string $smiley
     */
    public function smiley($smiley)
    {
        $this->cdata($smiley);
    }

    /**
     * Format an entity
     *
     * Entities are basically small text replacements
     *
     * Uses $this->entities
     *
     * @param string $entity
     */
    public function entity($entity)
    {
        $this->cdata($entity);
    }

    /**
     * Typographically format a multiply sign
     *
     * Example: ($x=640, $y=480) should result in "640×480"
     *
     * @param string|int $x first value
     * @param string|int $y second value
     */
    public function multiplyentity($x, $y)
    {
        $this->cdata($x.'×'.$y);
    }

    /**
     * Render an opening single quote char (language specific)
     */
    public function singlequoteopening()
    {
        global $lang;
        $this->cdata($lang['singlequoteopening']);
    }

    /**
     * Render a closing single quote char (language specific)
     */
    public function singlequoteclosing()
    {
        global $lang;
        $this->cdata($lang['singlequoteclosing']);
    }

    /**
     * Render an apostrophe char (language specific)
     */
    public function apostrophe()
    {
        global $lang;
        $this->cdata($lang['apostrophe']);
    }

    /**
     * Render an opening double quote char (language specific)
     */
    public function doublequoteopening()
    {
        global $lang;
        $this->cdata($lang['doublequoteopening']);
    }

    /**
     * Render an closinging double quote char (language specific)
     */
    public function doublequoteclosing()
    {
        global $lang;
        $this->cdata($lang['doublequoteclosing']);
    }

    /**
     * Render a CamelCase link
     *
     * @param string $link The link name
     * @see http://en.wikipedia.org/wiki/CamelCase
     */
    public function camelcaselink($link)
    {
        $this->internallink($link, $link);
    }

    /**
     * Render a page local link
     *
     * @param string $hash hash link identifier
     * @param string $name name for the link
     */
    public function locallink($hash, $name = null)
    {
        if (is_array($name)) {
            $this->_firstimage($name['src']);
            if ($name['type'] == 'internalmedia') {
                $this->_recordMediaUsage($name['src']);
            }
        }
    }

    /**
     * keep track of internal links in $this->meta['relation']['references']
     *
     * @param string            $id   page ID to link to. eg. 'wiki:syntax'
     * @param string|array|null $name name for the link, array for media file
     */
    public function internallink($id, $name = null)
    {
        global $ID;

        if (is_array($name)) {
            $this->_firstimage($name['src']);
            if ($name['type'] == 'internalmedia') {
                $this->_recordMediaUsage($name['src']);
            }
        }

        $parts = explode('?', $id, 2);
        if (count($parts) === 2) {
            $id = $parts[0];
        }

        $default = $this->_simpleTitle($id);

        // first resolve and clean up the $id
        resolve_pageid(getNS($ID), $id, $exists);
        @list($page) = explode('#', $id, 2);

        // set metadata
        $this->meta['relation']['references'][$page] = $exists;
        // $data = array('relation' => array('isreferencedby' => array($ID => true)));
        // p_set_metadata($id, $data);

        // add link title to summary
        if ($this->capture) {
            $name = $this->_getLinkTitle($name, $default, $id);
            $this->doc .= $name;
        }
    }

    /**
     * Render an external link
     *
     * @param string            $url  full URL with scheme
     * @param string|array|null $name name for the link, array for media file
     */
    public function externallink($url, $name = null)
    {
        if (is_array($name)) {
            $this->_firstimage($name['src']);
            if ($name['type'] == 'internalmedia') {
                $this->_recordMediaUsage($name['src']);
            }
        }

        if ($this->capture) {
            $this->doc .= $this->_getLinkTitle($name, '<'.$url.'>');
        }
    }

    /**
     * Render an interwiki link
     *
     * You may want to use $this->_resolveInterWiki() here
     *
     * @param string       $match     original link - probably not much use
     * @param string|array $name      name for the link, array for media file
     * @param string       $wikiName  indentifier (shortcut) for the remote wiki
     * @param string       $wikiUri   the fragment parsed from the original link
     */
    public function interwikilink($match, $name, $wikiName, $wikiUri)
    {
        if (is_array($name)) {
            $this->_firstimage($name['src']);
            if ($name['type'] == 'internalmedia') {
                $this->_recordMediaUsage($name['src']);
            }
        }

        if ($this->capture) {
            list($wikiUri) = explode('#', $wikiUri, 2);
            $name = $this->_getLinkTitle($name, $wikiUri);
            $this->doc .= $name;
        }
    }

    /**
     * Link to windows share
     *
     * @param string       $url  the link
     * @param string|array $name name for the link, array for media file
     */
    public function windowssharelink($url, $name = null)
    {
        if (is_array($name)) {
            $this->_firstimage($name['src']);
            if ($name['type'] == 'internalmedia') {
                $this->_recordMediaUsage($name['src']);
            }
        }

        if ($this->capture) {
            if ($name) {
                $this->doc .= $name;
            } else {
                $this->doc .= '<'.$url.'>';
            }
        }
    }

    /**
     * Render a linked E-Mail Address
     *
     * Should honor $conf['mailguard'] setting
     *
     * @param string       $address Email-Address
     * @param string|array $name    name for the link, array for media file
     */
    public function emaillink($address, $name = null)
    {
        if (is_array($name)) {
            $this->_firstimage($name['src']);
            if ($name['type'] == 'internalmedia') {
                $this->_recordMediaUsage($name['src']);
            }
        }

        if ($this->capture) {
            if ($name) {
                $this->doc .= $name;
            } else {
                $this->doc .= '<'.$address.'>';
            }
        }
    }

    /**
     * Render an internal media file
     *
     * @param string $src     media ID
     * @param string $title   descriptive text
     * @param string $align   left|center|right
     * @param int    $width   width of media in pixel
     * @param int    $height  height of media in pixel
     * @param string $cache   cache|recache|nocache
     * @param string $linking linkonly|detail|nolink
     * @param string $videoAtts controls, autoplay, loop, muted
     */
    public function internalmedia($src, $title = null, $align = null, $width = null,
                           $height = null, $cache = null, $linking = null, $videoAtts = null) {
        if ($this->capture && $title) {
            $this->doc .= '['.$title.']';
        }
        $this->_firstimage($src);
        $this->_recordMediaUsage($src);
    }

    /**
     * Render an external media file
     *
     * @param string $src     full media URL
     * @param string $title   descriptive text
     * @param string $align   left|center|right
     * @param int    $width   width of media in pixel
     * @param int    $height  height of media in pixel
     * @param string $cache   cache|recache|nocache
     * @param string $linking linkonly|detail|nolink
     * @param string $videoAtts controls, autoplay, loop, muted
     */
    public function externalmedia($src, $title = null, $align = null, $width = null,
                           $height = null, $cache = null, $linking = null, $videoAtts = null)
    {
        if ($this->capture && $title) {
            $this->doc .= '['.$title.']';
        }
        $this->_firstimage($src);
    }

    /**
     * Render the output of an RSS feed
     *
     * @param string $url    URL of the feed
     * @param array  $params Finetuning of the output
     */
    public function rss($url, $params)
    {
        $this->meta['relation']['haspart'][$url] = true;

        $this->meta['date']['valid']['age'] =
            isset($this->meta['date']['valid']['age']) ?
                min($this->meta['date']['valid']['age'], $params['refresh']) :
                $params['refresh'];
    }

    #region Utils

    /**
     * Removes any Namespace from the given name but keeps
     * casing and special chars
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     *
     * @param string $name
     *
     * @return mixed|string
     */
    public function _simpleTitle($name)
    {
        global $conf;

        if (is_array($name)) {
            return '';
        }

        if ($conf['useslash']) {
            $nssep = '[:;/]';
        } else {
            $nssep = '[:;]';
        }
        $name = preg_replace('!.*'.$nssep.'!', '', $name);
        //if there is a hash we use the anchor name only
        $name = preg_replace('!.*#!', '', $name);
        return $name;
    }

    /**
     * Construct a title and handle images in titles
     *
     * @author Harry Fuecks <hfuecks@gmail.com>
     * @param string|array|null $title    either string title or media array
     * @param string            $default  default title if nothing else is found
     * @param null|string       $id       linked page id (used to extract title from first heading)
     * @return string title text
     */
    public function _getLinkTitle($title, $default, $id = null)
    {
        if (is_array($title)) {
            if ($title['title']) {
                return '['.$title['title'].']';
            } else {
                return $default;
            }
        } elseif (is_null($title) || trim($title) == '') {
            if (useHeading('content') && $id) {
                $heading = p_get_first_heading($id, METADATA_DONT_RENDER);
                if ($heading) {
                    return $heading;
                }
            }
            return $default;
        } else {
            return $title;
        }
    }

    /**
     * Remember first image
     *
     * @param string $src image URL or ID
     */
    protected function _firstimage($src)
    {
        global $ID;

        if ($this->firstimage) {
            return;
        }

        list($src) = explode('#', $src, 2);
        if (!media_isexternal($src)) {
            resolve_mediaid(getNS($ID), $src, $exists);
        }
        if (preg_match('/.(jpe?g|gif|png)$/i', $src)) {
            $this->firstimage = $src;
        }
    }

    /**
     * Store list of used media files in metadata
     *
     * @param string $src media ID
     */
    protected function _recordMediaUsage($src)
    {
        global $ID;

        list ($src) = explode('#', $src, 2);
        if (media_isexternal($src)) {
            return;
        }
        resolve_mediaid(getNS($ID), $src, $exists);
        $this->meta['relation']['media'][$src] = $exists;
    }

    #endregion
}

//Setup VIM: ex: et ts=4 :
