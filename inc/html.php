<?php
/**
 * HTML output functions
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 */

use dokuwiki\ChangeLog\MediaChangeLog;
use dokuwiki\ChangeLog\PageChangeLog;
use dokuwiki\Extension\AuthPlugin;
use dokuwiki\Extension\Event;
use dokuwiki\Search\FulltextSearch;
use dokuwiki\Search\MetadataIndex;
use dokuwiki\Utf8;

if (!defined('SEC_EDIT_PATTERN')) {
    define('SEC_EDIT_PATTERN', '#<!-- EDIT({.*?}) -->#');
}


/**
 * Convenience function to quickly build a wikilink
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 * @param string  $id      id of the target page
 * @param string  $name    the name of the link, i.e. the text that is displayed
 * @param string|array  $search  search string(s) that shall be highlighted in the target page
 * @return string the HTML code of the link
 */
function html_wikilink($id, $name = null, $search = '') {
    /** @var Doku_Renderer_xhtml $xhtml_renderer */
    static $xhtml_renderer = null;
    if (is_null($xhtml_renderer)) {
        $xhtml_renderer = p_get_renderer('xhtml');
    }

    return $xhtml_renderer->internallink($id,$name,$search,true,'navigation');
}

/**
 * The loginform
 *
 * @author   Andreas Gohr <andi@splitbrain.org>
 *
 * @param bool $svg Whether to show svg icons in the register and resendpwd links or not
 * @deprecated 2020-07-18
 */
function html_login($svg = false) {
    dbg_deprecated(\dokuwiki\Ui\Login::class .'::show()');
    (new dokuwiki\Ui\Login($svg))->show();
}


/**
 * Denied page content
 *
 * @return string html
 * @deprecated 2020-07-18 not called anymore, see inc/Action/Denied::tplContent()
 */
function html_denied() {
    dbg_deprecated(\dokuwiki\Action\Denied::class .'::showBanner()');
    (new dokuwiki\Action\Denied())->showBanner();
}

/**
 * inserts section edit buttons if wanted or removes the markers
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 *
 * @param string $text
 * @param bool   $show show section edit buttons?
 * @return string
 */
function html_secedit($text, $show = true) {
    global $INFO;

    if ((isset($INFO) && !$INFO['writable']) || !$show || (isset($INFO) && $INFO['rev'])) {
        return preg_replace(SEC_EDIT_PATTERN,'',$text);
    }

    return preg_replace_callback(SEC_EDIT_PATTERN,
                'html_secedit_button', $text);
}

/**
 * prepares section edit button data for event triggering
 * used as a callback in html_secedit
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 *
 * @param array $matches matches with regexp
 * @return string
 * @triggers HTML_SECEDIT_BUTTON
 */
function html_secedit_button($matches){
    $json = htmlspecialchars_decode($matches[1], ENT_QUOTES);
    $data = json_decode($json, true);
    if ($data == NULL) {
        return;
    }
    $data ['target'] = strtolower($data['target']);
    $data ['hid'] = strtolower($data['hid']);

    return Event::createAndTrigger('HTML_SECEDIT_BUTTON', $data,
                         'html_secedit_get_button');
}

/**
 * prints a section editing button
 * used as default action form HTML_SECEDIT_BUTTON
 *
 * @author Adrian Lang <lang@cosmocode.de>
 *
 * @param array $data name, section id and target
 * @return string html
 */
function html_secedit_get_button($data) {
    global $ID;
    global $INFO;

    if (!isset($data['name']) || $data['name'] === '') return '';

    $name = $data['name'];
    unset($data['name']);

    $secid = $data['secid'];
    unset($data['secid']);

    $params = array_merge(
           array('do'  => 'edit', 'rev' => $INFO['lastmod'], 'summary' => '['.$name.'] '),
           $data
    );

    $html = '<div class="secedit editbutton_'.$data['target'] .' editbutton_'.$secid .'">';
    $html.= html_btn('secedit', $ID, '', $params, 'post', $name);
    $html.= '</div>';
    return $html;
}

/**
 * Just the back to top button (in its own form)
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 *
 * @return string html
 */
function html_topbtn() {
    global $lang;

    $html = '<a class="nolink" href="#dokuwiki__top">'
        .'<button class="button" onclick="window.scrollTo(0, 0)" title="'. $lang['btn_top'] .'">'
        . $lang['btn_top']
        .'</button></a>';
    return $html;
}

/**
 * Displays a button (using its own form)
 * If tooltip exists, the access key tooltip is replaced.
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 *
 * @param string         $name
 * @param string         $id
 * @param string         $akey   access key
 * @param string[] $params key-value pairs added as hidden inputs
 * @param string         $method
 * @param string         $tooltip
 * @param bool|string    $label  label text, false: lookup btn_$name in localization
 * @param string         $svg (optional) svg code, inserted into the button
 * @return string
 */
function html_btn($name, $id, $akey, $params, $method = 'get', $tooltip = '', $label = false, $svg = null) {
    global $conf;
    global $lang;

    if (!$label)
        $label = $lang['btn_'.$name];

    //filter id (without urlencoding)
    $id = idfilter($id,false);

    //make nice URLs even for buttons
    if ($conf['userewrite'] == 2) {
        $script = DOKU_BASE.DOKU_SCRIPT.'/'.$id;
    } elseif ($conf['userewrite']) {
        $script = DOKU_BASE.$id;
    } else {
        $script = DOKU_BASE.DOKU_SCRIPT;
        $params['id'] = $id;
    }

    $html = '<form class="button btn_'.$name.'" method="'.$method.'" action="'.$script.'"><div class="no">';

    if (is_array($params)) {
        foreach ($params as $key => $val) {
            $html .= '<input type="hidden" name="'.$key.'" value="'.hsc($val).'" />';
        }
    }

    $tip = empty($tooltip) ? hsc($label) : hsc($tooltip);

    $html .= '<button type="submit" ';
    if ($akey) {
        $tip  .= ' ['.strtoupper($akey).']';
        $html .= 'accesskey="'.$akey.'" ';
    }
    $html .= 'title="'.$tip.'">';
    if ($svg) {
        $html .= '<span>'. hsc($label) .'</span>'. inlineSVG($svg);
    } else {
        $html .= hsc($label);
    }
    $html .= '</button>';
    $html .= '</div></form>';

    return $html;
}
/**
 * show a revision warning
 *
 * @author Szymon Olewniczak <dokuwiki@imz.re>
 * @deprecated 2020-07-18
 */
function html_showrev() {
    dbg_deprecated(\dokuwiki\Ui\PageView::class .'::showrev()');
}

/**
 * Show a wiki page
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 *
 * @param null|string $txt wiki text or null for showing $ID
 * @deprecated 2020-07-18
 */
function html_show($txt=null) {
    dbg_deprecated(\dokuwiki\Ui\PageView::class .'::show()');
    (new dokuwiki\Ui\PageView($txt))->show();
}

/**
 * ask the user about how to handle an exisiting draft
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 * @deprecated 2020-07-18
 */
function html_draft() {
    dbg_deprecated(\dokuwiki\Ui\Draft::class .'::show()');
    (new dokuwiki\Ui\Draft)->show();
}

/**
 * Highlights searchqueries in HTML code
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 * @author Harry Fuecks <hfuecks@gmail.com>
 *
 * @param string $html
 * @param array|string $phrases
 * @return string html
 */
function html_hilight($html, $phrases) {
    $FulltextSearch = new FulltextSearch();
    $phrases = (array) $phrases;
    $phrases = array_map('preg_quote_cb', $phrases);
    $phrases = array_map([$FulltextSearch, 'snippetRePreprocess'], $phrases);
    $phrases = array_filter($phrases);
    $regex = implode('|', $phrases);

    if ($regex === '') return $html;
    if (!Utf8\Clean::isUtf8($regex)) return $html;

    $html = @preg_replace_callback("/((<[^>]*)|$regex)/ui", function ($match) {
        $hlight = unslash($match[0]);
        if (!isset($match[2])) {
            $hlight = '<span class="search_hit">'.$hlight.'</span>';
        }
        return $hlight;
    }, $html);
    return $html;
}

/**
 * Display error on locked pages
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 * @deprecated 2020-07-18 not called anymore, see inc/Action/Locked::tplContent()
 */
function html_locked() {
    dbg_deprecated(\dokuwiki\Action\Locked::class .'::showBanner()');
    (new dokuwiki\Action\Locked())->showBanner();
}

/**
 * list old revisions
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 * @author Ben Coburn <btcoburn@silicodon.net>
 * @author Kate Arzamastseva <pshns@ukr.net>
 *
 * @param int $first skip the first n changelog lines
 * @param bool|string $media_id id of media, or false for current page
 * @deprecated 2020-07-18
 */
function html_revisions($first=0, $media_id = false) {
    dbg_deprecated(\dokuwiki\Ui\Revisions::class .'::show()');
    (new dokuwiki\Ui\Revisions($first, $media_id))->show();
}

/**
 * display recent changes
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 * @author Matthias Grimm <matthiasgrimm@users.sourceforge.net>
 * @author Ben Coburn <btcoburn@silicodon.net>
 * @author Kate Arzamastseva <pshns@ukr.net>
 *
 * @param int $first
 * @param string $show_changes
 * @deprecated 2020-07-18
 */
function html_recent($first = 0, $show_changes = 'both') {
    dbg_deprecated(\dokuwiki\Ui\Recent::class .'::show()');
    (new dokuwiki\Ui\Recent($first, $show_changes))->show();
}

/**
 * Display page index
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 *
 * @param string $ns
 * @deprecated 2020-07-18
 */
function html_index($ns) {
    dbg_deprecated(\dokuwiki\Ui\Index::class .'::show()');
    (new dokuwiki\Ui\Index($ns))->show();
}

/**
 * Index tree item formatter for html_buildlist()
 *
 * User function for html_buildlist()
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 *
 * @param array $item
 * @return string
 * @deprecated 2020-07-18
 */
function html_list_index($item) {
    dbg_deprecated(\dokuwiki\Ui\Index::class .'::formatListItem()');
    return (new dokuwiki\Ui\Index)->formatListItem($item);
}

/**
 * Index list item formatter for html_buildlist()
 *
 * This user function is used in html_buildlist to build the
 * <li> tags for namespaces when displaying the page index
 * it gives different classes to opened or closed "folders"
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 *
 * @param array $item
 * @return string html
 * @deprecated 2020-07-18
 */
function html_li_index($item) {
    dbg_deprecated(\dokuwiki\Ui\Index::class .'::tagListItem()');
    return (new dokuwiki\Ui\Index)->tagListItem($item);
}

/**
 * Default list item formatter for html_buildlist()
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 *
 * @param array $item
 * @return string html
 * @deprecated 2020-07-18
 */
function html_li_default($item){
    return '<li class="level'.$item['level'].'">';
}

/**
 * Build an unordered list
 *
 * Build an unordered list from the given $data array
 * Each item in the array has to have a 'level' property
 * the item itself gets printed by the given $func user
 * function. The second and optional function is used to
 * print the <li> tag. Both user function need to accept
 * a single item.
 *
 * Both user functions can be given as array to point to
 * a member of an object.
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 *
 * @param array    $data  array with item arrays
 * @param string   $class class of ul wrapper
 * @param callable $func  callback to print an list item
 * @param callable $lifunc (optional) callback to the opening li tag
 * @param bool     $forcewrapper (optional) Trigger building a wrapper ul if the first level is
 *                               0 (we have a root object) or 1 (just the root content)
 * @return string html of an unordered list
 */
function html_buildlist($data, $class, $func, $lifunc = null, $forcewrapper = false) {
    if (count($data) === 0) {
        return '';
    }

    $firstElement = reset($data);
    $start_level = $firstElement['level'];
    $level = $start_level;
    $html  = '';
    $open  = 0;

    // set callback function to build the <li> tag, formerly defined as html_li_default()
    if (!is_callable($lifunc)) {
       $lifunc = function ($item) {
           return '<li class="level'.$item['level'].'">';
       };
    }

    foreach ($data as $item) {
        if ($item['level'] > $level) {
            //open new list
            for ($i = 0; $i < ($item['level'] - $level); $i++) {
                if ($i) $html .= '<li class="clear">';
                $html .= "\n".'<ul class="'.$class.'">'."\n";
                $open++;
            }
            $level = $item['level'];

        } elseif ($item['level'] < $level) {
            //close last item
            $html .= '</li>'."\n";
            while ($level > $item['level'] && $open > 0 ) {
                //close higher lists
                $html .= '</ul>'."\n".'</li>'."\n";
                $level--;
                $open--;
            }
        } elseif ($html !== '') {
            //close previous item
            $html .= '</li>'."\n";
        }

        //print item
        $html .= call_user_func($lifunc, $item);
        $html .= '<div class="li">';

        $html .= call_user_func($func, $item);
        $html .= '</div>';
    }

    //close remaining items and lists
    $html .= '</li>'."\n";
    while ($open-- > 0) {
        $html .= '</ul></li>'."\n";
    }

    if ($forcewrapper || $start_level < 2) {
        // Trigger building a wrapper ul if the first level is
        // 0 (we have a root object) or 1 (just the root content)
        $html = "\n".'<ul class="'.$class.'">'."\n".$html.'</ul>'."\n";
    }

    return $html;
}

/**
 * display backlinks
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 * @author Michael Klier <chi@chimeric.de>
 * @deprecated 2020-07-18
 */
function html_backlinks() {
    dbg_deprecated(\dokuwiki\Ui\Backlinks::class .'::show()');
    (new dokuwiki\Ui\Backlinks)->show();
}

/**
 * Get header of diff HTML
 *
 * @param string $l_rev   Left revisions
 * @param string $r_rev   Right revision
 * @param string $id      Page id, if null $ID is used
 * @param bool   $media   If it is for media files
 * @param bool   $inline  Return the header on a single line
 * @return string[] HTML snippets for diff header
 * @deprecated 2020-07-18
 */
function html_diff_head($l_rev, $r_rev, $id = null, $media = false, $inline = false) {
    dbg_deprecated('see '. \dokuwiki\Ui\Diff::class .'::diffHead()');
}

/**
 * Show diff
 * between current page version and provided $text
 * or between the revisions provided via GET or POST
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 * @param  string $text  when non-empty: compare with this text with most current version
 * @param  bool   $intro display the intro text
 * @param  string $type  type of the diff (inline or sidebyside)
 * @deprecated 2020-07-18
 */
function html_diff($text = '', $intro = true, $type = null) {
    dbg_deprecated(\dokuwiki\Ui\Diff::class .'::show()');
    (new dokuwiki\Ui\Diff($text, $intro, $type))->show();
}

/**
 * Create html for revision navigation
 *
 * @param PageChangeLog $pagelog changelog object of current page
 * @param string        $type    inline vs sidebyside
 * @param int           $l_rev   left revision timestamp
 * @param int           $r_rev   right revision timestamp
 * @return string[] html of left and right navigation elements
 * @deprecated 2020-07-18
 */
function html_diff_navigation($pagelog, $type, $l_rev, $r_rev) {
    dbg_deprecated('see '. \dokuwiki\Ui\Diff::class .'::diffNavigation()');
}

/**
 * Create html link to a diff defined by two revisions
 *
 * @param string $difftype display type
 * @param string $linktype
 * @param int $lrev oldest revision
 * @param int $rrev newest revision or null for diff with current revision
 * @return string html of link to a diff
 * @deprecated 2020-07-18
 */
function html_diff_navigationlink($difftype, $linktype, $lrev, $rrev = null) {
    dbg_deprecated('see '. \dokuwiki\Ui\Diff::class .'::diffViewlink()');
}

/**
 * Insert soft breaks in diff html
 *
 * @param string $diffhtml
 * @return string
 * @deprecated 2020-07-18
 */
function html_insert_softbreaks($diffhtml) {
    dbg_deprecated(\dokuwiki\Ui\Diff::class .'::insertSoftbreaks()');
    return (new dokuwiki\Ui\Diff())->insertSoftbreaks($diffhtml);
}

/**
 * show warning on conflict detection
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 *
 * @param string $text
 * @param string $summary
 * @deprecated 2020-07-18
 */
function html_conflict($text, $summary) {
    dbg_deprecated(\dokuwiki\Ui\Conflict::class .'::show()');
    (new dokuwiki\Ui\Conflict($text, $summary))->show();
}

/**
 * Prints the global message array
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 */
function html_msgarea() {
    global $MSG, $MSG_shown;
    /** @var array $MSG */
    // store if the global $MSG has already been shown and thus HTML output has been started
    $MSG_shown = true;

    if (!isset($MSG)) return;

    $shown = array();
    foreach ($MSG as $msg) {
        $hash = md5($msg['msg']);
        if (isset($shown[$hash])) continue; // skip double messages
        if (info_msg_allowed($msg)) {
            print '<div class="'.$msg['lvl'].'">';
            print $msg['msg'];
            print '</div>';
        }
        $shown[$hash] = 1;
    }

    unset($GLOBALS['MSG']);
}

/**
 * Prints the registration form
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 * @deprecated 2020-07-18
 */
function html_register() {
    dbg_deprecated(\dokuwiki\Ui\UserRegister::class .'::show()');
    (new dokuwiki\Ui\UserRegister)->show();
}

/**
 * Print the update profile form
 *
 * @author Christopher Smith <chris@jalakai.co.uk>
 * @author Andreas Gohr <andi@splitbrain.org>
 * @deprecated 2020-07-18
 */
function html_updateprofile() {
    dbg_deprecated(\dokuwiki\Ui\UserProfile::class .'::show()');
    (new dokuwiki\Ui\UserProfile)->show();
}

/**
 * Preprocess edit form data
 *
 * @author   Andreas Gohr <andi@splitbrain.org>
 *
 * @deprecated 2020-07-18
 */
function html_edit() {
    dbg_deprecated(\dokuwiki\Ui\Editor::class .'::show()');
    (new dokuwiki\Ui\Editor)->show();
}

/**
 * Display the default edit form
 *
 * Is the default action for HTML_EDIT_FORMSELECTION.
 *
 * @param mixed[] $param
 * @deprecated 2020-07-18
 */
function html_edit_form($param) {
    dbg_deprecated(\dokuwiki\Ui\Editor::class .'::addTextarea()');
    return (new dokuwiki\Ui\Editor)->addTextarea($param);
}

/**
 * prints some debug info
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 */
function html_debug() {
    global $conf;
    global $lang;
    /** @var AuthPlugin $auth */
    global $auth;
    global $INFO;

    //remove sensitive data
    $cnf = $conf;
    debug_guard($cnf);
    $nfo = $INFO;
    debug_guard($nfo);
    $ses = $_SESSION;
    debug_guard($ses);

    print '<html><body>';

    print '<p>When reporting bugs please send all the following ';
    print 'output as a mail to andi@splitbrain.org ';
    print 'The best way to do this is to save this page in your browser</p>';

    print '<b>$INFO:</b><pre>';
    print_r($nfo);
    print '</pre>';

    print '<b>$_SERVER:</b><pre>';
    print_r($_SERVER);
    print '</pre>';

    print '<b>$conf:</b><pre>';
    print_r($cnf);
    print '</pre>';

    print '<b>DOKU_BASE:</b><pre>';
    print DOKU_BASE;
    print '</pre>';

    print '<b>abs DOKU_BASE:</b><pre>';
    print DOKU_URL;
    print '</pre>';

    print '<b>rel DOKU_BASE:</b><pre>';
    print dirname($_SERVER['PHP_SELF']).'/';
    print '</pre>';

    print '<b>PHP Version:</b><pre>';
    print phpversion();
    print '</pre>';

    print '<b>locale:</b><pre>';
    print setlocale(LC_ALL,0);
    print '</pre>';

    print '<b>encoding:</b><pre>';
    print $lang['encoding'];
    print '</pre>';

    if ($auth) {
        print '<b>Auth backend capabilities:</b><pre>';
        foreach ($auth->getCapabilities() as $cando) {
            print '   '.str_pad($cando,16) .' => '. (int)$auth->canDo($cando) . DOKU_LF;
        }
        print '</pre>';
    }

    print '<b>$_SESSION:</b><pre>';
    print_r($ses);
    print '</pre>';

    print '<b>Environment:</b><pre>';
    print_r($_ENV);
    print '</pre>';

    print '<b>PHP settings:</b><pre>';
    $inis = ini_get_all();
    print_r($inis);
    print '</pre>';

    if (function_exists('apache_get_version')) {
        $apache = array();
        $apache['version'] = apache_get_version();

        if (function_exists('apache_get_modules')) {
            $apache['modules'] = apache_get_modules();
        }
        print '<b>Apache</b><pre>';
        print_r($apache);
        print '</pre>';
    }

    print '</body></html>';
}

/**
 * Form to request a new password for an existing account
 *
 * @author Benoit Chesneau <benoit@bchesneau.info>
 * @author Andreas Gohr <gohr@cosmocode.de>
 * @deprecated 2020-07-18
 */
function html_resendpwd() {
    dbg_deprecated(\dokuwiki\Ui\UserResendPwd::class .'::show()');
    (new dokuwiki\Ui\UserResendPwd)->show();
}

/**
 * Return the TOC rendered to XHTML
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 *
 * @param array $toc
 * @return string html
 */
function html_TOC($toc) {
    if (!count($toc)) return '';
    global $lang;
    $out  = '<!-- TOC START -->'.DOKU_LF;
    $out .= '<div id="dw__toc" class="dw__toc">'.DOKU_LF;
    $out .= '<h3 class="toggle">';
    $out .= $lang['toc'];
    $out .= '</h3>'.DOKU_LF;
    $out .= '<div>'.DOKU_LF;
    $out .= html_buildlist($toc, 'toc', 'html_list_toc', null, true);
    $out .= '</div>'.DOKU_LF.'</div>'.DOKU_LF;
    $out .= '<!-- TOC END -->'.DOKU_LF;
    return $out;
}

/**
 * Callback for html_buildlist
 *
 * @param array $item
 * @return string html
 */
function html_list_toc($item) {
    if (isset($item['hid'])){
        $link = '#'.$item['hid'];
    } else {
        $link = $item['link'];
    }

    return '<a href="'.$link.'">'.hsc($item['title']).'</a>';
}

/**
 * Helper function to build TOC items
 *
 * Returns an array ready to be added to a TOC array
 *
 * @param string $link  - where to link (if $hash set to '#' it's a local anchor)
 * @param string $text  - what to display in the TOC
 * @param int    $level - nesting level
 * @param string $hash  - is prepended to the given $link, set blank if you want full links
 * @return array the toc item
 */
function html_mktocitem($link, $text, $level, $hash='#') {
    return  array(
            'link'  => $hash.$link,
            'title' => $text,
            'type'  => 'ul',
            'level' => $level
    );
}

/**
 * Output a Doku_Form object.
 * Triggers an event with the form name: HTML_{$name}FORM_OUTPUT
 *
 * @author Tom N Harris <tnharris@whoopdedo.org>
 *
 * @param string     $name The name of the form
 * @param Doku_Form  $form The form
 * @return void
 * @deprecated 2020-07-18
 */
function html_form($name, $form) {
    dbg_deprecated('use dokuwiki\Form\Form instead of Doku_Form');
    // Safety check in case the caller forgets.
    $form->endFieldset();
    Event::createAndTrigger('HTML_'.strtoupper($name).'FORM_OUTPUT', $form, 'html_form_output', false);
}

/**
 * Form print function.
 * Just calls printForm() on the form object.
 *
 * @param Doku_Form $form The form
 * @return void
 * @deprecated 2020-07-18
 */
function html_form_output($form) {
    dbg_deprecated('use dokuwiki\Form\Form::toHTML()');
    $form->printForm();
}

/**
 * Embed a flash object in HTML
 *
 * This will create the needed HTML to embed a flash movie in a cross browser
 * compatble way using valid XHTML
 *
 * The parameters $params, $flashvars and $atts need to be associative arrays.
 * No escaping needs to be done for them. The alternative content *has* to be
 * escaped because it is used as is. If no alternative content is given
 * $lang['noflash'] is used.
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 * @link   http://latrine.dgx.cz/how-to-correctly-insert-a-flash-into-xhtml
 *
 * @param string $swf      - the SWF movie to embed
 * @param int $width       - width of the flash movie in pixels
 * @param int $height      - height of the flash movie in pixels
 * @param array $params    - additional parameters (<param>)
 * @param array $flashvars - parameters to be passed in the flashvar parameter
 * @param array $atts      - additional attributes for the <object> tag
 * @param string $alt      - alternative content (is NOT automatically escaped!)
 * @return string         - the XHTML markup
 */
function html_flashobject($swf,$width,$height,$params=null,$flashvars=null,$atts=null,$alt=''){
    global $lang;

    $out = '';

    // prepare the object attributes
    if(is_null($atts)) $atts = array();
    $atts['width']  = (int) $width;
    $atts['height'] = (int) $height;
    if(!$atts['width'])  $atts['width']  = 425;
    if(!$atts['height']) $atts['height'] = 350;

    // add object attributes for standard compliant browsers
    $std = $atts;
    $std['type'] = 'application/x-shockwave-flash';
    $std['data'] = $swf;

    // add object attributes for IE
    $ie  = $atts;
    $ie['classid'] = 'clsid:D27CDB6E-AE6D-11cf-96B8-444553540000';

    // open object (with conditional comments)
    $out .= '<!--[if !IE]> -->'.NL;
    $out .= '<object '.buildAttributes($std).'>'.NL;
    $out .= '<!-- <![endif]-->'.NL;
    $out .= '<!--[if IE]>'.NL;
    $out .= '<object '.buildAttributes($ie).'>'.NL;
    $out .= '    <param name="movie" value="'.hsc($swf).'" />'.NL;
    $out .= '<!--><!-- -->'.NL;

    // print params
    if(is_array($params)) foreach($params as $key => $val){
        $out .= '  <param name="'.hsc($key).'" value="'.hsc($val).'" />'.NL;
    }

    // add flashvars
    if(is_array($flashvars)){
        $out .= '  <param name="FlashVars" value="'.buildURLparams($flashvars).'" />'.NL;
    }

    // alternative content
    if($alt){
        $out .= $alt.NL;
    }else{
        $out .= $lang['noflash'].NL;
    }

    // finish
    $out .= '</object>'.NL;
    $out .= '<!-- <![endif]-->'.NL;

    return $out;
}

/**
 * Prints HTML code for the given tab structure
 *
 * @param array  $tabs        tab structure
 * @param string $current_tab the current tab id
 * @return void
 */
function html_tabs($tabs, $current_tab = null) {
    echo '<ul class="tabs">'.NL;

    foreach ($tabs as $id => $tab) {
        html_tab($tab['href'], $tab['caption'], $id === $current_tab);
    }

    echo '</ul>'.NL;
}

/**
 * Prints a single tab
 *
 * @author Kate Arzamastseva <pshns@ukr.net>
 * @author Adrian Lang <mail@adrianlang.de>
 *
 * @param string $href - tab href
 * @param string $caption - tab caption
 * @param boolean $selected - is tab selected
 * @return void
 */

function html_tab($href, $caption, $selected = false) {
    $tab = '<li>';
    if ($selected) {
        $tab .= '<strong>';
    } else {
        $tab .= '<a href="' . hsc($href) . '">';
    }
    $tab .= hsc($caption)
         .  '</' . ($selected ? 'strong' : 'a') . '>'
         .  '</li>'.NL;
    echo $tab;
}

/**
 * Display size change
 *
 * @param int $sizechange - size of change in Bytes
 * @param Doku_Form $form - (optional) form to add elements to
 * @return void|string
 */
function html_sizechange($sizechange, $form = null) {
    if (isset($sizechange)) {
        $class = 'sizechange';
        $value = filesize_h(abs($sizechange));
        if ($sizechange > 0) {
            $class .= ' positive';
            $value = '+' . $value;
        } elseif ($sizechange < 0) {
            $class .= ' negative';
            $value = '-' . $value;
        } else {
            $value = '±' . $value;
        }
        if (!isset($form)) {
            return '<span class="'.$class.'">'.$value.'</span>';
        } else { // Doku_Form
            $form->addElement(form_makeOpenTag('span', array('class' => $class)));
            $form->addElement($value);
            $form->addElement(form_makeCloseTag('span'));
        }
    }
}
