<?php
/**
 * Renderer output base class
 *
 * @author Harry Fuecks <hfuecks@gmail.com>
 * @author Andreas Gohr <andi@splitbrain.org>
 */

use dokuwiki\Extension\Plugin;
use dokuwiki\Extension\SyntaxPlugin;

/**
 * Allowed chars in $language for code highlighting
 * @see GeSHi::set_language()
 */
define('PREG_PATTERN_VALID_LANGUAGE', '#[^a-zA-Z0-9\-_]#');

/**
 * An empty renderer, produces no output
 *
 * Inherits from dokuwiki\Plugin\DokuWiki_Plugin for giving additional functions to render plugins
 *
 * The renderer transforms the syntax instructions created by the parser and handler into the
 * desired output format. For each instruction a corresponding method defined in this class will
 * be called. That method needs to produce the desired output for the instruction and add it to the
 * $doc field. When all instructions are processed, the $doc field contents will be cached by
 * DokuWiki and sent to the user.
 */
abstract class Doku_Renderer extends Plugin {
    /** @var array Settings, control the behavior of the renderer */
    public $info = array(
        'cache' => true, // may the rendered result cached?
        'toc'   => true, // render the TOC?
    );

    /** @var array contains the smiley configuration, set in p_render() */
    public $smileys = array();
    /** @var array contains the entity configuration, set in p_render() */
    public $entities = array();
    /** @var array contains the acronym configuration, set in p_render() */
    public $acronyms = array();
    /** @var array contains the interwiki configuration, set in p_render() */
    public $interwiki = array();

    /** @var array the list of headers used to create unique link ids */
    protected $headers = array();

    /**
     * @var string the rendered document, this will be cached after the renderer ran through
     */
    public $doc = '';

    /**
     * clean out any per-use values
     *
     * This is called before each use of the renderer object and should be used to
     * completely reset the state of the renderer to be reused for a new document
     */
    public function reset(){
        $this->headers = array();
        $this->doc           = '';
        $this->info['cache'] = true;
        $this->info['toc']   = true;
    }

    /**
     * Allow the plugin to prevent DokuWiki from reusing an instance
     *
     * Since most renderer plugins fail to implement Doku_Renderer::reset() we default
     * to reinstantiating the renderer here
     *
     * @return bool   false if the plugin has to be instantiated
     */
    public function isSingleton() {
        return false;
    }

    /**
     * Returns the format produced by this renderer.
     *
     * Has to be overidden by sub classes
     *
     * @return string
     */
    abstract public function getFormat();

    /**
     * Disable caching of this renderer's output
     */
    public function nocache() {
        $this->info['cache'] = false;
    }

    /**
     * Disable TOC generation for this renderer's output
     *
     * This might not be used for certain sub renderer
     */
    public function notoc() {
        $this->info['toc'] = false;
    }

    /**
     * Handle plugin rendering
     *
     * Most likely this needs NOT to be overwritten by sub classes
     *
     * @param string $name  Plugin name
     * @param mixed  $data  custom data set by handler
     * @param string $state matched state if any
     * @param string $match raw matched syntax
     */
    public function plugin($name, $data, $state = '', $match = '') {
        /** @var SyntaxPlugin $plugin */
        $plugin = plugin_load('syntax', $name);
        if($plugin != null) {
            $plugin->render($this->getFormat(), $this, $data);
        }
    }

    /**
     * handle nested render instructions
     * this method (and nest_close method) should not be overloaded in actual renderer output classes
     *
     * @param array $instructions
     */
    public function nest($instructions) {
        foreach($instructions as $instruction) {
            // execute the callback against ourself
            if(method_exists($this, $instruction[0])) {
                call_user_func_array(array($this, $instruction[0]), $instruction[1] ? $instruction[1] : array());
            }
        }
    }

    /**
     * dummy closing instruction issued by Doku_Handler_Nest
     *
     * normally the syntax mode should override this instruction when instantiating Doku_Handler_Nest -
     * however plugins will not be able to - as their instructions require data.
     */
    public function nest_close() {
    }

    #region Syntax modes - sub classes will need to implement them to fill $doc

    /**
     * Initialize the document
     */
    public function document_start() {
    }

    /**
     * Finalize the document
     */
    public function document_end() {
    }

    /**
     * Render the Table of Contents
     *
     * @return string
     */
    public function render_TOC() {
        return '';
    }

    /**
     * Add an item to the TOC
     *
     * @param string $id       the hash link
     * @param string $text     the text to display
     * @param int    $level    the nesting level
     */
    public function toc_additem($id, $text, $level) {
    }

    /**
     * Render a heading
     *
     * @param string $text  the text to display
     * @param int    $level header level
     * @param int    $pos   byte position in the original source
     */
    public function header($text, $level, $pos) {
    }

    /**
     * Open a new section
     *
     * @param int $level section level (as determined by the previous header)
     */
    public function section_open($level) {
    }

    /**
     * Close the current section
     */
    public function section_close() {
    }

    /**
     * Render plain text data
     *
     * @param string $text
     */
    public function cdata($text) {
    }

    /**
     * Open a paragraph
     */
    public function p_open() {
    }

    /**
     * Close a paragraph
     */
    public function p_close() {
    }

    /**
     * Create a line break
     */
    public function linebreak() {
    }

    /**
     * Create a horizontal line
     */
    public function hr() {
    }

    /**
     * Start strong (bold) formatting
     */
    public function strong_open() {
    }

    /**
     * Stop strong (bold) formatting
     */
    public function strong_close() {
    }

    /**
     * Start emphasis (italics) formatting
     */
    public function emphasis_open() {
    }

    /**
     * Stop emphasis (italics) formatting
     */
    public function emphasis_close() {
    }

    /**
     * Start underline formatting
     */
    public function underline_open() {
    }

    /**
     * Stop underline formatting
     */
    public function underline_close() {
    }

    /**
     * Start monospace formatting
     */
    public function monospace_open() {
    }

    /**
     * Stop monospace formatting
     */
    public function monospace_close() {
    }

    /**
     * Start a subscript
     */
    public function subscript_open() {
    }

    /**
     * Stop a subscript
     */
    public function subscript_close() {
    }

    /**
     * Start a superscript
     */
    public function superscript_open() {
    }

    /**
     * Stop a superscript
     */
    public function superscript_close() {
    }

    /**
     * Start deleted (strike-through) formatting
     */
    public function deleted_open() {
    }

    /**
     * Stop deleted (strike-through) formatting
     */
    public function deleted_close() {
    }

    /**
     * Start a footnote
     */
    public function footnote_open() {
    }

    /**
     * Stop a footnote
     */
    public function footnote_close() {
    }

    /**
     * Open an unordered list
     */
    public function listu_open() {
    }

    /**
     * Close an unordered list
     */
    public function listu_close() {
    }

    /**
     * Open an ordered list
     */
    public function listo_open() {
    }

    /**
     * Close an ordered list
     */
    public function listo_close() {
    }

    /**
     * Open a list item
     *
     * @param int $level the nesting level
     * @param bool $node true when a node; false when a leaf
     */
    public function listitem_open($level,$node=false) {
    }

    /**
     * Close a list item
     */
    public function listitem_close() {
    }

    /**
     * Start the content of a list item
     */
    public function listcontent_open() {
    }

    /**
     * Stop the content of a list item
     */
    public function listcontent_close() {
    }

    /**
     * Output unformatted $text
     *
     * Defaults to $this->cdata()
     *
     * @param string $text
     */
    public function unformatted($text) {
        $this->cdata($text);
    }

    /**
     * Output inline PHP code
     *
     * If $conf['phpok'] is true this should evaluate the given code and append the result
     * to $doc
     *
     * @param string $text The PHP code
     */
    public function php($text) {
    }

    /**
     * Output block level PHP code
     *
     * If $conf['phpok'] is true this should evaluate the given code and append the result
     * to $doc
     *
     * @param string $text The PHP code
     */
    public function phpblock($text) {
    }

    /**
     * Output raw inline HTML
     *
     * If $conf['htmlok'] is true this should add the code as is to $doc
     *
     * @param string $text The HTML
     */
    public function html($text) {
    }

    /**
     * Output raw block-level HTML
     *
     * If $conf['htmlok'] is true this should add the code as is to $doc
     *
     * @param string $text The HTML
     */
    public function htmlblock($text) {
    }

    /**
     * Output preformatted text
     *
     * @param string $text
     */
    public function preformatted($text) {
    }

    /**
     * Start a block quote
     */
    public function quote_open() {
    }

    /**
     * Stop a block quote
     */
    public function quote_close() {
    }

    /**
     * Display text as file content, optionally syntax highlighted
     *
     * @param string $text text to show
     * @param string $lang programming language to use for syntax highlighting
     * @param string $file file path label
     */
    public function file($text, $lang = null, $file = null) {
    }

    /**
     * Display text as code content, optionally syntax highlighted
     *
     * @param string $text text to show
     * @param string $lang programming language to use for syntax highlighting
     * @param string $file file path label
     */
    public function code($text, $lang = null, $file = null) {
    }

    /**
     * Format an acronym
     *
     * Uses $this->acronyms
     *
     * @param string $acronym
     */
    public function acronym($acronym) {
    }

    /**
     * Format a smiley
     *
     * Uses $this->smiley
     *
     * @param string $smiley
     */
    public function smiley($smiley) {
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
    public function entity($entity) {
    }

    /**
     * Typographically format a multiply sign
     *
     * Example: ($x=640, $y=480) should result in "640×480"
     *
     * @param string|int $x first value
     * @param string|int $y second value
     */
    public function multiplyentity($x, $y) {
    }

    /**
     * Render an opening single quote char (language specific)
     */
    public function singlequoteopening() {
    }

    /**
     * Render a closing single quote char (language specific)
     */
    public function singlequoteclosing() {
    }

    /**
     * Render an apostrophe char (language specific)
     */
    public function apostrophe() {
    }

    /**
     * Render an opening double quote char (language specific)
     */
    public function doublequoteopening() {
    }

    /**
     * Render an closinging double quote char (language specific)
     */
    public function doublequoteclosing() {
    }

    /**
     * Render a CamelCase link
     *
     * @param string $link The link name
     * @see http://en.wikipedia.org/wiki/CamelCase
     */
    public function camelcaselink($link) {
    }

    /**
     * Render a page local link
     *
     * @param string $hash hash link identifier
     * @param string $name name for the link
     */
    public function locallink($hash, $name = null) {
    }

    /**
     * Render a wiki internal link
     *
     * @param string       $link  page ID to link to. eg. 'wiki:syntax'
     * @param string|array $title name for the link, array for media file
     */
    public function internallink($link, $title = null) {
    }

    /**
     * Render an external link
     *
     * @param string       $link  full URL with scheme
     * @param string|array $title name for the link, array for media file
     */
    public function externallink($link, $title = null) {
    }

    /**
     * Render the output of an RSS feed
     *
     * @param string $url    URL of the feed
     * @param array  $params Finetuning of the output
     */
    public function rss($url, $params) {
    }

    /**
     * Render an interwiki link
     *
     * You may want to use $this->_resolveInterWiki() here
     *
     * @param string       $link     original link - probably not much use
     * @param string|array $title    name for the link, array for media file
     * @param string       $wikiName indentifier (shortcut) for the remote wiki
     * @param string       $wikiUri  the fragment parsed from the original link
     */
    public function interwikilink($link, $title, $wikiName, $wikiUri) {
    }

    /**
     * Link to file on users OS
     *
     * @param string       $link  the link
     * @param string|array $title name for the link, array for media file
     */
    public function filelink($link, $title = null) {
    }

    /**
     * Link to windows share
     *
     * @param string       $link  the link
     * @param string|array $title name for the link, array for media file
     */
    public function windowssharelink($link, $title = null) {
    }

    /**
     * Render a linked E-Mail Address
     *
     * Should honor $conf['mailguard'] setting
     *
     * @param string $address Email-Address
     * @param string|array $name name for the link, array for media file
     */
    public function emaillink($address, $name = null) {
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
     */
    public function externalmedia($src, $title = null, $align = null, $width = null,
                           $height = null, $cache = null, $linking = null, $videoAtts = null) {
    }

    /**
     * Render a link to an internal media file
     *
     * @param string $src     media ID
     * @param string $title   descriptive text
     * @param string $align   left|center|right
     * @param int    $width   width of media in pixel
     * @param int    $height  height of media in pixel
     * @param string $cache   cache|recache|nocache
     */
    public function internalmedialink($src, $title = null, $align = null,
                               $width = null, $height = null, $cache = null) {
    }

    /**
     * Render a link to an external media file
     *
     * @param string $src     media ID
     * @param string $title   descriptive text
     * @param string $align   left|center|right
     * @param int    $width   width of media in pixel
     * @param int    $height  height of media in pixel
     * @param string $cache   cache|recache|nocache
     */
    public function externalmedialink($src, $title = null, $align = null,
                               $width = null, $height = null, $cache = null) {
    }

    /**
     * Start a table
     *
     * @param int $maxcols maximum number of columns
     * @param int $numrows NOT IMPLEMENTED
     * @param int $pos     byte position in the original source
     */
    public function table_open($maxcols = null, $numrows = null, $pos = null) {
    }

    /**
     * Close a table
     *
     * @param int $pos byte position in the original source
     */
    public function table_close($pos = null) {
    }

    /**
     * Open a table header
     */
    public function tablethead_open() {
    }

    /**
     * Close a table header
     */
    public function tablethead_close() {
    }

    /**
     * Open a table body
     */
    public function tabletbody_open() {
    }

    /**
     * Close a table body
     */
    public function tabletbody_close() {
    }

    /**
     * Open a table footer
     */
    public function tabletfoot_open() {
    }

    /**
     * Close a table footer
     */
    public function tabletfoot_close() {
    }

    /**
     * Open a table row
     */
    public function tablerow_open() {
    }

    /**
     * Close a table row
     */
    public function tablerow_close() {
    }

    /**
     * Open a table header cell
     *
     * @param int    $colspan
     * @param string $align left|center|right
     * @param int    $rowspan
     */
    public function tableheader_open($colspan = 1, $align = null, $rowspan = 1) {
    }

    /**
     * Close a table header cell
     */
    public function tableheader_close() {
    }

    /**
     * Open a table cell
     *
     * @param int    $colspan
     * @param string $align left|center|right
     * @param int    $rowspan
     */
    public function tablecell_open($colspan = 1, $align = null, $rowspan = 1) {
    }

    /**
     * Close a table cell
     */
    public function tablecell_close() {
    }

    #endregion

    #region util functions, you probably won't need to reimplement them

    /**
     * Creates a linkid from a headline
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     * @param string  $title   The headline title
     * @param boolean $create  Create a new unique ID?
     * @return string
     */
    public function _headerToLink($title, $create = false) {
        if($create) {
            return sectionID($title, $this->headers);
        } else {
            $check = false;
            return sectionID($title, $check);
        }
    }

    /**
     * Removes any Namespace from the given name but keeps
     * casing and special chars
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     *
     * @param string $name
     * @return string
     */
    public function _simpleTitle($name) {
        global $conf;

        //if there is a hash we use the ancor name only
        @list($name, $hash) = explode('#', $name, 2);
        if($hash) return $hash;

        if($conf['useslash']) {
            $name = strtr($name, ';/', ';:');
        } else {
            $name = strtr($name, ';', ':');
        }

        return noNSorNS($name);
    }

    /**
     * Resolve an interwikilink
     *
     * @param string    $shortcut  identifier for the interwiki link
     * @param string    $reference fragment that refers the content
     * @param null|bool $exists    reference which returns if an internal page exists
     * @return string interwikilink
     */
    public function _resolveInterWiki(&$shortcut, $reference, &$exists = null) {
        //get interwiki URL
        if(isset($this->interwiki[$shortcut])) {
            $url = $this->interwiki[$shortcut];
        }elseif(isset($this->interwiki['default'])) {
            $shortcut = 'default';
            $url = $this->interwiki[$shortcut];
        }else{
            // not parsable interwiki outputs '' to make sure string manipluation works
            $shortcut = '';
            $url      = '';
        }

        //split into hash and url part
        $hash = strrchr($reference, '#');
        if($hash) {
            $reference = substr($reference, 0, -strlen($hash));
            $hash = substr($hash, 1);
        }

        //replace placeholder
        if(preg_match('#\{(URL|NAME|SCHEME|HOST|PORT|PATH|QUERY)\}#', $url)) {
            //use placeholders
            $url    = str_replace('{URL}', rawurlencode($reference), $url);
            //wiki names will be cleaned next, otherwise urlencode unsafe chars
            $url    = str_replace('{NAME}', ($url[0] === ':') ? $reference :
                                  preg_replace_callback('/[[\\\\\]^`{|}#%]/', function($match) {
                                    return rawurlencode($match[0]);
                                  }, $reference), $url);
            $parsed = parse_url($reference);
            if (empty($parsed['scheme'])) $parsed['scheme'] = '';
            if (empty($parsed['host'])) $parsed['host'] = '';
            if (empty($parsed['port'])) $parsed['port'] = 80;
            if (empty($parsed['path'])) $parsed['path'] = '';
            if (empty($parsed['query'])) $parsed['query'] = '';
            $url = strtr($url,[
                '{SCHEME}' => $parsed['scheme'],
                '{HOST}' => $parsed['host'],
                '{PORT}' => $parsed['port'],
                '{PATH}' => $parsed['path'],
                '{QUERY}' => $parsed['query'] ,
            ]);
        } else if($url != '') {
            // make sure when no url is defined, we keep it null
            // default
            $url = $url.rawurlencode($reference);
        }
        //handle as wiki links
        if($url && $url[0] === ':') {
            $urlparam = null;
            $id = $url;
            if (strpos($url, '?') !== false) {
                list($id, $urlparam) = explode('?', $url, 2);
            }
            $url    = wl(cleanID($id), $urlparam);
            $exists = page_exists($id);
        }
        if($hash) $url .= '#'.rawurlencode($hash);

        return $url;
    }

    #endregion
}


//Setup VIM: ex: et ts=4 :
