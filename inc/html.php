<?php
/**
 * HTML output functions
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 */

if(!defined('DOKU_INC')) die('meh.');
if(!defined('NL')) define('NL',"\n");
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
function html_wikilink($id,$name=null,$search=''){
    /** @var Doku_Renderer_xhtml $xhtml_renderer */
    static $xhtml_renderer = null;
    if(is_null($xhtml_renderer)){
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
 */
function html_login($svg = false){
    global $lang;
    global $conf;
    global $ID;
    global $INPUT;

    print p_locale_xhtml('login');
    print '<div class="centeralign">'.NL;
    $form = new Doku_Form(array('id' => 'dw__login'));
    $form->startFieldset($lang['btn_login']);
    $form->addHidden('id', $ID);
    $form->addHidden('do', 'login');
    $form->addElement(form_makeTextField('u', ((!$INPUT->bool('http_credentials')) ? $INPUT->str('u') : ''), $lang['user'], 'focus__this', 'block'));
    $form->addElement(form_makePasswordField('p', $lang['pass'], '', 'block'));
    if($conf['rememberme']) {
        $form->addElement(form_makeCheckboxField('r', '1', $lang['remember'], 'remember__me', 'simple'));
    }
    $form->addElement(form_makeButton('submit', '', $lang['btn_login']));
    $form->endFieldset();

    if(actionOK('register')){
        $registerLink = (new \dokuwiki\Menu\Item\Register())->asHtmlLink('', $svg);
        $form->addElement('<p>'.$lang['reghere'].': '. $registerLink .'</p>');
    }

    if (actionOK('resendpwd')) {
        $resendPwLink = (new \dokuwiki\Menu\Item\Resendpwd())->asHtmlLink('', $svg);
        $form->addElement('<p>'.$lang['pwdforget'].': '. $resendPwLink .'</p>');
    }

    html_form('login', $form);
    print '</div>'.NL;
}


/**
 * Denied page content
 *
 * @return string html
 */
function html_denied() {
    print p_locale_xhtml('denied');

    if(empty($_SERVER['REMOTE_USER']) && actionOK('login')){
        html_login();
    }
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
function html_secedit($text,$show=true){
    global $INFO;

    if(!$INFO['writable'] || !$show || $INFO['rev']){
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

    return trigger_event('HTML_SECEDIT_BUTTON', $data,
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

    return "<div class='secedit editbutton_" . $data['target'] .
                       " editbutton_" . $secid . "'>" .
           html_btn('secedit', $ID, '',
                    array_merge(array('do'  => 'edit',
                                      'rev' => $INFO['lastmod'],
                                      'summary' => '['.$name.'] '), $data),
                    'post', $name) . '</div>';
}

/**
 * Just the back to top button (in its own form)
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 *
 * @return string html
 */
function html_topbtn(){
    global $lang;

    $ret  = '<a class="nolink" href="#dokuwiki__top"><button class="button" onclick="window.scrollTo(0, 0)" title="'.$lang['btn_top'].'">'.$lang['btn_top'].'</button></a>';

    return $ret;
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
function html_btn($name, $id, $akey, $params, $method='get', $tooltip='', $label=false, $svg=null){
    global $conf;
    global $lang;

    if (!$label)
        $label = $lang['btn_'.$name];

    $ret = '';

    //filter id (without urlencoding)
    $id = idfilter($id,false);

    //make nice URLs even for buttons
    if($conf['userewrite'] == 2){
        $script = DOKU_BASE.DOKU_SCRIPT.'/'.$id;
    }elseif($conf['userewrite']){
        $script = DOKU_BASE.$id;
    }else{
        $script = DOKU_BASE.DOKU_SCRIPT;
        $params['id'] = $id;
    }

    $ret .= '<form class="button btn_'.$name.'" method="'.$method.'" action="'.$script.'"><div class="no">';

    if(is_array($params)){
        foreach($params as $key => $val) {
            $ret .= '<input type="hidden" name="'.$key.'" ';
            $ret .= 'value="'.hsc($val).'" />';
        }
    }

    if ($tooltip!='') {
        $tip = hsc($tooltip);
    }else{
        $tip = hsc($label);
    }

    $ret .= '<button type="submit" ';
    if($akey){
        $tip .= ' ['.strtoupper($akey).']';
        $ret .= 'accesskey="'.$akey.'" ';
    }
    $ret .= 'title="'.$tip.'">';
    if ($svg) {
        $ret .= '<span>' . hsc($label) . '</span>';
        $ret .= inlineSVG($svg);
    } else {
        $ret .= hsc($label);
    }
    $ret .= '</button>';
    $ret .= '</div></form>';

    return $ret;
}
/**
 * show a revision warning
 *
 * @author Szymon Olewniczak <dokuwiki@imz.re>
 */
function html_showrev() {
    print p_locale_xhtml('showrev');
}

/**
 * Show a wiki page
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 *
 * @param null|string $txt wiki text or null for showing $ID
 */
function html_show($txt=null){
    global $ID;
    global $REV;
    global $HIGH;
    global $INFO;
    global $DATE_AT;
    //disable section editing for old revisions or in preview
    if($txt || $REV){
        $secedit = false;
    }else{
        $secedit = true;
    }

    if (!is_null($txt)){
        //PreviewHeader
        echo '<br id="scroll__here" />';
        echo p_locale_xhtml('preview');
        echo '<div class="preview"><div class="pad">';
        $html = html_secedit(p_render('xhtml',p_get_instructions($txt),$info),$secedit);
        if($INFO['prependTOC']) $html = tpl_toc(true).$html;
        echo $html;
        echo '<div class="clearer"></div>';
        echo '</div></div>';

    }else{
        if ($REV||$DATE_AT){
            $data = array('rev' => &$REV, 'date_at' => &$DATE_AT);
            trigger_event('HTML_SHOWREV_OUTPUT', $data, 'html_showrev');
        }
        $html = p_wiki_xhtml($ID,$REV,true,$DATE_AT);
        $html = html_secedit($html,$secedit);
        if($INFO['prependTOC']) $html = tpl_toc(true).$html;
        $html = html_hilight($html,$HIGH);
        echo $html;
    }
}

/**
 * ask the user about how to handle an exisiting draft
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 */
function html_draft(){
    global $INFO;
    global $ID;
    global $lang;
    $draft = new \dokuwiki\Draft($ID, $INFO['client']);
    $text  = $draft->getDraftText();

    print p_locale_xhtml('draft');
    html_diff($text, false);
    $form = new Doku_Form(array('id' => 'dw__editform'));
    $form->addHidden('id', $ID);
    $form->addHidden('date', $draft->getDraftDate());
    $form->addHidden('wikitext', $text);
    $form->addElement(form_makeOpenTag('div', array('id'=>'draft__status')));
    $form->addElement($draft->getDraftMessage());
    $form->addElement(form_makeCloseTag('div'));
    $form->addElement(form_makeButton('submit', 'recover', $lang['btn_recover'], array('tabindex'=>'1')));
    $form->addElement(form_makeButton('submit', 'draftdel', $lang['btn_draftdel'], array('tabindex'=>'2')));
    $form->addElement(form_makeButton('submit', 'show', $lang['btn_cancel'], array('tabindex'=>'3')));
    html_form('draft', $form);
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
function html_hilight($html,$phrases){
    $phrases = (array) $phrases;
    $phrases = array_map('preg_quote_cb', $phrases);
    $phrases = array_map('ft_snippet_re_preprocess', $phrases);
    $phrases = array_filter($phrases);
    $regex = join('|',$phrases);

    if ($regex === '') return $html;
    if (!utf8_check($regex)) return $html;
    $html = @preg_replace_callback("/((<[^>]*)|$regex)/ui",'html_hilight_callback',$html);
    return $html;
}

/**
 * Callback used by html_hilight()
 *
 * @author Harry Fuecks <hfuecks@gmail.com>
 *
 * @param array $m matches
 * @return string html
 */
function html_hilight_callback($m) {
    $hlight = unslash($m[0]);
    if ( !isset($m[2])) {
        $hlight = '<span class="search_hit">'.$hlight.'</span>';
    }
    return $hlight;
}

/**
 * Display error on locked pages
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 */
function html_locked(){
    global $ID;
    global $conf;
    global $lang;
    global $INFO;

    $locktime = filemtime(wikiLockFN($ID));
    $expire = dformat($locktime + $conf['locktime']);
    $min    = round(($conf['locktime'] - (time() - $locktime) )/60);

    print p_locale_xhtml('locked');
    print '<ul>';
    print '<li><div class="li"><strong>'.$lang['lockedby'].'</strong> '.editorinfo($INFO['locked']).'</div></li>';
    print '<li><div class="li"><strong>'.$lang['lockexpire'].'</strong> '.$expire.' ('.$min.' min)</div></li>';
    print '</ul>';
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
 */
function html_revisions($first=0, $media_id = false){
    global $ID;
    global $INFO;
    global $conf;
    global $lang;
    $id = $ID;
    if ($media_id) {
        $id = $media_id;
        $changelog = new MediaChangeLog($id);
    } else {
        $changelog = new PageChangeLog($id);
    }

    /* we need to get one additional log entry to be able to
     * decide if this is the last page or is there another one.
     * see html_recent()
     */

    $revisions = $changelog->getRevisions($first, $conf['recent']+1);

    if(count($revisions)==0 && $first!=0){
        $first=0;
        $revisions = $changelog->getRevisions($first, $conf['recent']+1);
    }
    $hasNext = false;
    if (count($revisions)>$conf['recent']) {
        $hasNext = true;
        array_pop($revisions); // remove extra log entry
    }

    if (!$media_id) print p_locale_xhtml('revisions');

    $params = array('id' => 'page__revisions', 'class' => 'changes');
    if($media_id) {
        $params['action'] = media_managerURL(array('image' => $media_id), '&');
    }

    if(!$media_id) {
        $exists = $INFO['exists'];
        $display_name = useHeading('navigation') ? hsc(p_get_first_heading($id)) : $id;
        if(!$display_name) {
            $display_name = $id;
        }
    } else {
        $exists = file_exists(mediaFN($id));
        $display_name = $id;
    }

    $form = new Doku_Form($params);
    $form->addElement(form_makeOpenTag('ul'));

    if($exists && $first == 0) {
        $minor = false;
        if($media_id) {
            $date = dformat(@filemtime(mediaFN($id)));
            $href = media_managerURL(array('image' => $id, 'tab_details' => 'view'), '&');

            $changelog->setChunkSize(1024);
            $revinfo = $changelog->getRevisionInfo(@filemtime(fullpath(mediaFN($id))));

            $summary = $revinfo['sum'];
            if($revinfo['user']) {
                $editor = $revinfo['user'];
            } else {
                $editor = $revinfo['ip'];
            }
            $sizechange = $revinfo['sizechange'];
        } else {
            $date = dformat($INFO['lastmod']);
            if(isset($INFO['meta']) && isset($INFO['meta']['last_change'])) {
                if($INFO['meta']['last_change']['type'] === DOKU_CHANGE_TYPE_MINOR_EDIT) {
                    $minor = true;
                }
                if(isset($INFO['meta']['last_change']['sizechange'])) {
                    $sizechange = $INFO['meta']['last_change']['sizechange'];
                } else {
                    $sizechange = null;
                }
            }
            $pagelog = new PageChangeLog($ID);
            $latestrev = $pagelog->getRevisions(-1, 1);
            $latestrev = array_pop($latestrev);
            $href = wl($id,"rev=$latestrev",false,'&');
            $summary = $INFO['sum'];
            $editor = $INFO['editor'];
        }

        $form->addElement(form_makeOpenTag('li', array('class' => ($minor ? 'minor' : ''))));
        $form->addElement(form_makeOpenTag('div', array('class' => 'li')));
        $form->addElement(form_makeTag('input', array(
                        'type' => 'checkbox',
                        'name' => 'rev2[]',
                        'value' => 'current')));

        $form->addElement(form_makeOpenTag('span', array('class' => 'date')));
        $form->addElement($date);
        $form->addElement(form_makeCloseTag('span'));

        $form->addElement('<img src="'.DOKU_BASE.'lib/images/blank.gif" width="15" height="11" alt="" />');

        $form->addElement(form_makeOpenTag('a', array(
                        'class' => 'wikilink1',
                        'href'  => $href)));
        $form->addElement($display_name);
        $form->addElement(form_makeCloseTag('a'));

        if ($media_id) $form->addElement(form_makeOpenTag('div'));

        if($summary) {
            $form->addElement(form_makeOpenTag('span', array('class' => 'sum')));
            if(!$media_id) $form->addElement(' – ');
            $form->addElement('<bdi>' . hsc($summary) . '</bdi>');
            $form->addElement(form_makeCloseTag('span'));
        }

        $form->addElement(form_makeOpenTag('span', array('class' => 'user')));
        $form->addElement((empty($editor))?('('.$lang['external_edit'].')'):'<bdi>'.editorinfo($editor).'</bdi>');
        $form->addElement(form_makeCloseTag('span'));

        html_sizechange($sizechange, $form);

        $form->addElement('('.$lang['current'].')');

        if ($media_id) $form->addElement(form_makeCloseTag('div'));

        $form->addElement(form_makeCloseTag('div'));
        $form->addElement(form_makeCloseTag('li'));
    }

    foreach($revisions as $rev) {
        $date = dformat($rev);
        $info = $changelog->getRevisionInfo($rev);
        if($media_id) {
            $exists = file_exists(mediaFN($id, $rev));
        } else {
            $exists = page_exists($id, $rev);
        }

        $class = '';
        if($info['type'] === DOKU_CHANGE_TYPE_MINOR_EDIT) {
            $class = 'minor';
        }
        $form->addElement(form_makeOpenTag('li', array('class' => $class)));
        $form->addElement(form_makeOpenTag('div', array('class' => 'li')));
        if($exists){
            $form->addElement(form_makeTag('input', array(
                            'type' => 'checkbox',
                            'name' => 'rev2[]',
                            'value' => $rev)));
        }else{
            $form->addElement('<img src="'.DOKU_BASE.'lib/images/blank.gif" width="15" height="11" alt="" />');
        }

        $form->addElement(form_makeOpenTag('span', array('class' => 'date')));
        $form->addElement($date);
        $form->addElement(form_makeCloseTag('span'));

        if($exists){
            if (!$media_id) {
                $href = wl($id,"rev=$rev,do=diff", false, '&');
            } else {
                $href = media_managerURL(array('image' => $id, 'rev' => $rev, 'mediado' => 'diff'), '&');
            }
            $form->addElement(form_makeOpenTag('a', array(
                            'class' => 'diff_link',
                            'href' => $href)));
            $form->addElement(form_makeTag('img', array(
                            'src'    => DOKU_BASE.'lib/images/diff.png',
                            'width'  => 15,
                            'height' => 11,
                            'title'  => $lang['diff'],
                            'alt'    => $lang['diff'])));
            $form->addElement(form_makeCloseTag('a'));

            if (!$media_id) {
                $href = wl($id,"rev=$rev",false,'&');
            } else {
                $href = media_managerURL(array('image' => $id, 'tab_details' => 'view', 'rev' => $rev), '&');
            }
            $form->addElement(form_makeOpenTag('a', array(
                            'class' => 'wikilink1',
                            'href' => $href)));
            $form->addElement($display_name);
            $form->addElement(form_makeCloseTag('a'));
        }else{
            $form->addElement('<img src="'.DOKU_BASE.'lib/images/blank.gif" width="15" height="11" alt="" />');
            $form->addElement($display_name);
        }

        if ($media_id) $form->addElement(form_makeOpenTag('div'));

        if ($info['sum']) {
            $form->addElement(form_makeOpenTag('span', array('class' => 'sum')));
            if(!$media_id) $form->addElement(' – ');
            $form->addElement('<bdi>'.hsc($info['sum']).'</bdi>');
            $form->addElement(form_makeCloseTag('span'));
        }

        $form->addElement(form_makeOpenTag('span', array('class' => 'user')));
        if($info['user']){
            $form->addElement('<bdi>'.editorinfo($info['user']).'</bdi>');
            if(auth_ismanager()){
                $form->addElement(' <bdo dir="ltr">('.$info['ip'].')</bdo>');
            }
        }else{
            $form->addElement('<bdo dir="ltr">'.$info['ip'].'</bdo>');
        }
        $form->addElement(form_makeCloseTag('span'));

        html_sizechange($info['sizechange'], $form);

        if ($media_id) $form->addElement(form_makeCloseTag('div'));

        $form->addElement(form_makeCloseTag('div'));
        $form->addElement(form_makeCloseTag('li'));
    }
    $form->addElement(form_makeCloseTag('ul'));
    if (!$media_id) {
        $form->addElement(form_makeButton('submit', 'diff', $lang['diff2']));
    } else {
        $form->addHidden('mediado', 'diff');
        $form->addElement(form_makeButton('submit', '', $lang['diff2']));
    }
    html_form('revisions', $form);

    print '<div class="pagenav">';
    $last = $first + $conf['recent'];
    if ($first > 0) {
        $first -= $conf['recent'];
        if ($first < 0) $first = 0;
        print '<div class="pagenav-prev">';
        if ($media_id) {
            print html_btn('newer',$media_id,"p",media_managerURL(array('first' => $first), '&amp;', false, true));
        } else {
            print html_btn('newer',$id,"p",array('do' => 'revisions', 'first' => $first));
        }
        print '</div>';
    }
    if ($hasNext) {
        print '<div class="pagenav-next">';
        if ($media_id) {
            print html_btn('older',$media_id,"n",media_managerURL(array('first' => $last), '&amp;', false, true));
        } else {
            print html_btn('older',$id,"n",array('do' => 'revisions', 'first' => $last));
        }
        print '</div>';
    }
    print '</div>';

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
 */
function html_recent($first = 0, $show_changes = 'both') {
    global $conf;
    global $lang;
    global $ID;
    /* we need to get one additionally log entry to be able to
     * decide if this is the last page or is there another one.
     * This is the cheapest solution to get this information.
     */
    $flags = 0;
    if($show_changes == 'mediafiles' && $conf['mediarevisions']) {
        $flags = RECENTS_MEDIA_CHANGES;
    } elseif($show_changes == 'pages') {
        $flags = 0;
    } elseif($conf['mediarevisions']) {
        $show_changes = 'both';
        $flags = RECENTS_MEDIA_PAGES_MIXED;
    }

    $recents = getRecents($first, $conf['recent'] + 1, getNS($ID), $flags);
    if(count($recents) == 0 && $first != 0) {
        $first = 0;
        $recents = getRecents($first, $conf['recent'] + 1, getNS($ID), $flags);
    }
    $hasNext = false;
    if(count($recents) > $conf['recent']) {
        $hasNext = true;
        array_pop($recents); // remove extra log entry
    }

    print p_locale_xhtml('recent');

    if(getNS($ID) != '') {
        print '<div class="level1"><p>' . sprintf($lang['recent_global'], getNS($ID), wl('', 'do=recent')) . '</p></div>';
    }

    $form = new Doku_Form(array('id' => 'dw__recent', 'method' => 'GET', 'class' => 'changes'));
    $form->addHidden('sectok', null);
    $form->addHidden('do', 'recent');
    $form->addHidden('id', $ID);

    if($conf['mediarevisions']) {
        $form->addElement('<div class="changeType">');
        $form->addElement(form_makeListboxField(
                    'show_changes',
                    array(
                        'pages'      => $lang['pages_changes'],
                        'mediafiles' => $lang['media_changes'],
                        'both'       => $lang['both_changes']
                    ),
                    $show_changes,
                    $lang['changes_type'],
                    '', '',
                    array('class' => 'quickselect')));

        $form->addElement(form_makeButton('submit', 'recent', $lang['btn_apply']));
        $form->addElement('</div>');
    }

    $form->addElement(form_makeOpenTag('ul'));

    foreach($recents as $recent) {
        $date = dformat($recent['date']);

        $class = '';
        if($recent['type'] === DOKU_CHANGE_TYPE_MINOR_EDIT) {
            $class = 'minor';
        }
        $form->addElement(form_makeOpenTag('li', array('class' => $class)));
        $form->addElement(form_makeOpenTag('div', array('class' => 'li')));

        if(!empty($recent['media'])) {
            $form->addElement(media_printicon($recent['id']));
        } else {
            $icon = DOKU_BASE . 'lib/images/fileicons/file.png';
            $form->addElement('<img src="' . $icon . '" alt="' . $recent['id'] . '" class="icon" />');
        }

        $form->addElement(form_makeOpenTag('span', array('class' => 'date')));
        $form->addElement($date);
        $form->addElement(form_makeCloseTag('span'));

        $diff = false;
        $href = '';

        if(!empty($recent['media'])) {
            $changelog = new MediaChangeLog($recent['id']);
            $revs = $changelog->getRevisions(0, 1);
            $diff = (count($revs) && file_exists(mediaFN($recent['id'])));
            if($diff) {
                $href = media_managerURL(array(
                                            'tab_details' => 'history',
                                            'mediado' => 'diff',
                                            'image' => $recent['id'],
                                            'ns' => getNS($recent['id'])
                                        ), '&');
            }
        } else {
            $href = wl($recent['id'], "do=diff", false, '&');
        }

        if(!empty($recent['media']) && !$diff) {
            $form->addElement('<img src="' . DOKU_BASE . 'lib/images/blank.gif" width="15" height="11" alt="" />');
        } else {
            $form->addElement(form_makeOpenTag('a', array('class' => 'diff_link', 'href' => $href)));
            $form->addElement(form_makeTag('img', array(
                            'src'    => DOKU_BASE . 'lib/images/diff.png',
                            'width'  => 15,
                            'height' => 11,
                            'title'  => $lang['diff'],
                            'alt'    => $lang['diff']
                        )));
            $form->addElement(form_makeCloseTag('a'));
        }

        if(!empty($recent['media'])) {
            $href = media_managerURL(array('tab_details' => 'history', 'image' => $recent['id'], 'ns' => getNS($recent['id'])), '&');
        } else {
            $href = wl($recent['id'], "do=revisions", false, '&');
        }
        $form->addElement(form_makeOpenTag('a', array(
                        'class' => 'revisions_link',
                        'href'  => $href)));
        $form->addElement(form_makeTag('img', array(
                        'src'    => DOKU_BASE . 'lib/images/history.png',
                        'width'  => 12,
                        'height' => 14,
                        'title'  => $lang['btn_revs'],
                        'alt'    => $lang['btn_revs']
                    )));
        $form->addElement(form_makeCloseTag('a'));

        if(!empty($recent['media'])) {
            $href = media_managerURL(array('tab_details' => 'view', 'image' => $recent['id'], 'ns' => getNS($recent['id'])), '&');
            $class = file_exists(mediaFN($recent['id'])) ? 'wikilink1' : 'wikilink2';
            $form->addElement(form_makeOpenTag('a', array(
                        'class' => $class,
                        'href'  => $href)));
            $form->addElement($recent['id']);
            $form->addElement(form_makeCloseTag('a'));
        } else {
            $form->addElement(html_wikilink(':' . $recent['id'], useHeading('navigation') ? null : $recent['id']));
        }
        $form->addElement(form_makeOpenTag('span', array('class' => 'sum')));
        $form->addElement(' – ' . hsc($recent['sum']));
        $form->addElement(form_makeCloseTag('span'));

        $form->addElement(form_makeOpenTag('span', array('class' => 'user')));
        if($recent['user']) {
            $form->addElement('<bdi>' . editorinfo($recent['user']) . '</bdi>');
            if(auth_ismanager()) {
                $form->addElement(' <bdo dir="ltr">(' . $recent['ip'] . ')</bdo>');
            }
        } else {
            $form->addElement('<bdo dir="ltr">' . $recent['ip'] . '</bdo>');
        }
        $form->addElement(form_makeCloseTag('span'));

        html_sizechange($recent['sizechange'], $form);

        $form->addElement(form_makeCloseTag('div'));
        $form->addElement(form_makeCloseTag('li'));
    }
    $form->addElement(form_makeCloseTag('ul'));

    $form->addElement(form_makeOpenTag('div', array('class' => 'pagenav')));
    $last = $first + $conf['recent'];
    if($first > 0) {
        $first -= $conf['recent'];
        if($first < 0) $first = 0;
        $form->addElement(form_makeOpenTag('div', array('class' => 'pagenav-prev')));
        $form->addElement(form_makeOpenTag('button', array(
                        'type'      => 'submit',
                        'name'      => 'first[' . $first . ']',
                        'accesskey' => 'n',
                        'title'     => $lang['btn_newer'] . ' [N]',
                        'class'     => 'button show'
                    )));
        $form->addElement($lang['btn_newer']);
        $form->addElement(form_makeCloseTag('button'));
        $form->addElement(form_makeCloseTag('div'));
    }
    if($hasNext) {
        $form->addElement(form_makeOpenTag('div', array('class' => 'pagenav-next')));
        $form->addElement(form_makeOpenTag('button', array(
                        'type'      => 'submit',
                        'name'      => 'first[' . $last . ']',
                        'accesskey' => 'p',
                        'title'     => $lang['btn_older'] . ' [P]',
                        'class'     => 'button show'
                    )));
        $form->addElement($lang['btn_older']);
        $form->addElement(form_makeCloseTag('button'));
        $form->addElement(form_makeCloseTag('div'));
    }
    $form->addElement(form_makeCloseTag('div'));
    html_form('recent', $form);
}

/**
 * Display page index
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 *
 * @param string $ns
 */
function html_index($ns){
    global $conf;
    global $ID;
    $ns  = cleanID($ns);
    if(empty($ns)){
        $ns = getNS($ID);
        if($ns === false) $ns ='';
    }
    $ns  = utf8_encodeFN(str_replace(':','/',$ns));

    echo p_locale_xhtml('index');
    echo '<div id="index__tree" class="index__tree">';

    $data = array();
    search($data,$conf['datadir'],'search_index',array('ns' => $ns));
    echo html_buildlist($data,'idx','html_list_index','html_li_index');

    echo '</div>';
}

/**
 * Index item formatter
 *
 * User function for html_buildlist()
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 *
 * @param array $item
 * @return string
 */
function html_list_index($item){
    global $ID, $conf;

    // prevent searchbots needlessly following links
    $nofollow = ($ID != $conf['start'] || $conf['sitemap']) ? ' rel="nofollow"' : '';

    $ret = '';
    $base = ':'.$item['id'];
    $base = substr($base,strrpos($base,':')+1);
    if($item['type']=='d'){
        // FS#2766, no need for search bots to follow namespace links in the index
        $ret .= '<a href="'.wl($ID,'idx='.rawurlencode($item['id'])).'" title="' . $item['id'] . '" class="idx_dir"' . $nofollow . '><strong>';
        $ret .= $base;
        $ret .= '</strong></a>';
    }else{
        // default is noNSorNS($id), but we want noNS($id) when useheading is off FS#2605
        $ret .= html_wikilink(':'.$item['id'], useHeading('navigation') ? null : noNS($item['id']));
    }
    return $ret;
}

/**
 * Index List item
 *
 * This user function is used in html_buildlist to build the
 * <li> tags for namespaces when displaying the page index
 * it gives different classes to opened or closed "folders"
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 *
 * @param array $item
 * @return string html
 */
function html_li_index($item){
    global $INFO;
    global $ACT;

    $class = '';
    $id = '';

    if($item['type'] == "f"){
        // scroll to the current item
        if($item['id'] == $INFO['id'] && $ACT == 'index') {
            $id = ' id="scroll__here"';
            $class = ' bounce';
        }
        return '<li class="level'.$item['level'].$class.'" '.$id.'>';
    }elseif($item['open']){
        return '<li class="open">';
    }else{
        return '<li class="closed">';
    }
}

/**
 * Default List item
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 *
 * @param array $item
 * @return string html
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
 * @param callable $lifunc callback to the opening li tag
 * @param bool     $forcewrapper Trigger building a wrapper ul if the first level is
 *                               0 (we have a root object) or 1 (just the root content)
 * @return string html of an unordered list
 */
function html_buildlist($data,$class,$func,$lifunc='html_li_default',$forcewrapper=false){
    if (count($data) === 0) {
        return '';
    }

    $firstElement = reset($data);
    $start_level = $firstElement['level'];
    $level = $start_level;
    $ret   = '';
    $open  = 0;

    foreach ($data as $item){

        if( $item['level'] > $level ){
            //open new list
            for($i=0; $i<($item['level'] - $level); $i++){
                if ($i) $ret .= "<li class=\"clear\">";
                $ret .= "\n<ul class=\"$class\">\n";
                $open++;
            }
            $level = $item['level'];

        }elseif( $item['level'] < $level ){
            //close last item
            $ret .= "</li>\n";
            while( $level > $item['level'] && $open > 0 ){
                //close higher lists
                $ret .= "</ul>\n</li>\n";
                $level--;
                $open--;
            }
        } elseif ($ret !== '') {
            //close previous item
            $ret .= "</li>\n";
        }

        //print item
        $ret .= call_user_func($lifunc,$item);
        $ret .= '<div class="li">';

        $ret .= call_user_func($func,$item);
        $ret .= '</div>';
    }

    //close remaining items and lists
    $ret .= "</li>\n";
    while($open-- > 0) {
        $ret .= "</ul></li>\n";
    }

    if ($forcewrapper || $start_level < 2) {
        // Trigger building a wrapper ul if the first level is
        // 0 (we have a root object) or 1 (just the root content)
        $ret = "\n<ul class=\"$class\">\n".$ret."</ul>\n";
    }

    return $ret;
}

/**
 * display backlinks
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 * @author Michael Klier <chi@chimeric.de>
 */
function html_backlinks(){
    global $ID;
    global $lang;

    print p_locale_xhtml('backlinks');

    $data = ft_backlinks($ID);

    if(!empty($data)) {
        print '<ul class="idx">';
        foreach($data as $blink){
            print '<li><div class="li">';
            print html_wikilink(':'.$blink,useHeading('navigation')?null:$blink);
            print '</div></li>';
        }
        print '</ul>';
    } else {
        print '<div class="level1"><p>' . $lang['nothingfound'] . '</p></div>';
    }
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
 */
function html_diff_head($l_rev, $r_rev, $id = null, $media = false, $inline = false) {
    global $lang;
    if ($id === null) {
        global $ID;
        $id = $ID;
    }
    $head_separator = $inline ? ' ' : '<br />';
    $media_or_wikiFN = $media ? 'mediaFN' : 'wikiFN';
    $ml_or_wl = $media ? 'ml' : 'wl';
    $l_minor = $r_minor = '';

    if($media) {
        $changelog = new MediaChangeLog($id);
    } else {
        $changelog = new PageChangeLog($id);
    }
    if(!$l_rev){
        $l_head = '&mdash;';
    }else{
        $l_info   = $changelog->getRevisionInfo($l_rev);
        if($l_info['user']){
            $l_user = '<bdi>'.editorinfo($l_info['user']).'</bdi>';
            if(auth_ismanager()) $l_user .= ' <bdo dir="ltr">('.$l_info['ip'].')</bdo>';
        } else {
            $l_user = '<bdo dir="ltr">'.$l_info['ip'].'</bdo>';
        }
        $l_user  = '<span class="user">'.$l_user.'</span>';
        $l_sum   = ($l_info['sum']) ? '<span class="sum"><bdi>'.hsc($l_info['sum']).'</bdi></span>' : '';
        if ($l_info['type']===DOKU_CHANGE_TYPE_MINOR_EDIT) $l_minor = 'class="minor"';

        $l_head_title = ($media) ? dformat($l_rev) : $id.' ['.dformat($l_rev).']';
        $l_head = '<bdi><a class="wikilink1" href="'.$ml_or_wl($id,"rev=$l_rev").'">'.
        $l_head_title.'</a></bdi>'.
        $head_separator.$l_user.' '.$l_sum;
    }

    if($r_rev){
        $r_info   = $changelog->getRevisionInfo($r_rev);
        if($r_info['user']){
            $r_user = '<bdi>'.editorinfo($r_info['user']).'</bdi>';
            if(auth_ismanager()) $r_user .= ' <bdo dir="ltr">('.$r_info['ip'].')</bdo>';
        } else {
            $r_user = '<bdo dir="ltr">'.$r_info['ip'].'</bdo>';
        }
        $r_user = '<span class="user">'.$r_user.'</span>';
        $r_sum  = ($r_info['sum']) ? '<span class="sum"><bdi>'.hsc($r_info['sum']).'</bdi></span>' : '';
        if ($r_info['type']===DOKU_CHANGE_TYPE_MINOR_EDIT) $r_minor = 'class="minor"';

        $r_head_title = ($media) ? dformat($r_rev) : $id.' ['.dformat($r_rev).']';
        $r_head = '<bdi><a class="wikilink1" href="'.$ml_or_wl($id,"rev=$r_rev").'">'.
        $r_head_title.'</a></bdi>'.
        $head_separator.$r_user.' '.$r_sum;
    }elseif($_rev = @filemtime($media_or_wikiFN($id))){
        $_info   = $changelog->getRevisionInfo($_rev);
        if($_info['user']){
            $_user = '<bdi>'.editorinfo($_info['user']).'</bdi>';
            if(auth_ismanager()) $_user .= ' <bdo dir="ltr">('.$_info['ip'].')</bdo>';
        } else {
            $_user = '<bdo dir="ltr">'.$_info['ip'].'</bdo>';
        }
        $_user = '<span class="user">'.$_user.'</span>';
        $_sum  = ($_info['sum']) ? '<span class="sum"><bdi>'.hsc($_info['sum']).'</span></bdi>' : '';
        if ($_info['type']===DOKU_CHANGE_TYPE_MINOR_EDIT) $r_minor = 'class="minor"';

        $r_head_title = ($media) ? dformat($_rev) : $id.' ['.dformat($_rev).']';
        $r_head  = '<bdi><a class="wikilink1" href="'.$ml_or_wl($id).'">'.
        $r_head_title.'</a></bdi> '.
        '('.$lang['current'].')'.
        $head_separator.$_user.' '.$_sum;
    }else{
        $r_head = '&mdash; ('.$lang['current'].')';
    }

    return array($l_head, $r_head, $l_minor, $r_minor);
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
 */
function html_diff($text = '', $intro = true, $type = null) {
    global $ID;
    global $REV;
    global $lang;
    global $INPUT;
    global $INFO;
    $pagelog = new PageChangeLog($ID);

    /*
     * Determine diff type
     */
    if(!$type) {
        $type = $INPUT->str('difftype');
        if(empty($type)) {
            $type = get_doku_pref('difftype', $type);
            if(empty($type) && $INFO['ismobile']) {
                $type = 'inline';
            }
        }
    }
    if($type != 'inline') $type = 'sidebyside';

    /*
     * Determine requested revision(s)
     */
    // we're trying to be clever here, revisions to compare can be either
    // given as rev and rev2 parameters, with rev2 being optional. Or in an
    // array in rev2.
    $rev1 = $REV;

    $rev2 = $INPUT->ref('rev2');
    if(is_array($rev2)) {
        $rev1 = (int) $rev2[0];
        $rev2 = (int) $rev2[1];

        if(!$rev1) {
            $rev1 = $rev2;
            unset($rev2);
        }
    } else {
        $rev2 = $INPUT->int('rev2');
    }

    /*
     * Determine left and right revision, its texts and the header
     */
    $r_minor = '';
    $l_minor = '';

    if($text) { // compare text to the most current revision
        $l_rev = '';
        $l_text = rawWiki($ID, '');
        $l_head = '<a class="wikilink1" href="' . wl($ID) . '">' .
            $ID . ' ' . dformat((int) @filemtime(wikiFN($ID))) . '</a> ' .
            $lang['current'];

        $r_rev = '';
        $r_text = cleanText($text);
        $r_head = $lang['yours'];
    } else {
        if($rev1 && isset($rev2) && $rev2) { // two specific revisions wanted
            // make sure order is correct (older on the left)
            if($rev1 < $rev2) {
                $l_rev = $rev1;
                $r_rev = $rev2;
            } else {
                $l_rev = $rev2;
                $r_rev = $rev1;
            }
        } elseif($rev1) { // single revision given, compare to current
            $r_rev = '';
            $l_rev = $rev1;
        } else { // no revision was given, compare previous to current
            $r_rev = '';
            $revs = $pagelog->getRevisions(0, 1);
            $l_rev = $revs[0];
            $REV = $l_rev; // store revision back in $REV
        }

        // when both revisions are empty then the page was created just now
        if(!$l_rev && !$r_rev) {
            $l_text = '';
        } else {
            $l_text = rawWiki($ID, $l_rev);
        }
        $r_text = rawWiki($ID, $r_rev);

        list($l_head, $r_head, $l_minor, $r_minor) = html_diff_head($l_rev, $r_rev, null, false, $type == 'inline');
    }

    /*
     * Build navigation
     */
    $l_nav = '';
    $r_nav = '';
    if(!$text) {
        list($l_nav, $r_nav) = html_diff_navigation($pagelog, $type, $l_rev, $r_rev);
    }
    /*
     * Create diff object and the formatter
     */
    $diff = new Diff(explode("\n", $l_text), explode("\n", $r_text));

    if($type == 'inline') {
        $diffformatter = new InlineDiffFormatter();
    } else {
        $diffformatter = new TableDiffFormatter();
    }
    /*
     * Display intro
     */
    if($intro) print p_locale_xhtml('diff');

    /*
     * Display type and exact reference
     */
    if(!$text) {
        ptln('<div class="diffoptions group">');


        $form = new Doku_Form(array('action' => wl()));
        $form->addHidden('id', $ID);
        $form->addHidden('rev2[0]', $l_rev);
        $form->addHidden('rev2[1]', $r_rev);
        $form->addHidden('do', 'diff');
        $form->addElement(
             form_makeListboxField(
                 'difftype',
                 array(
                     'sidebyside' => $lang['diff_side'],
                     'inline' => $lang['diff_inline']
                 ),
                 $type,
                 $lang['diff_type'],
                 '', '',
                 array('class' => 'quickselect')
             )
        );
        $form->addElement(form_makeButton('submit', 'diff', 'Go'));
        $form->printForm();

        ptln('<p>');
        // link to exactly this view FS#2835
        echo html_diff_navigationlink($type, 'difflink', $l_rev, $r_rev ? $r_rev : $INFO['currentrev']);
        ptln('</p>');

        ptln('</div>'); // .diffoptions
    }

    /*
     * Display diff view table
     */
    ?>
    <div class="table">
    <table class="diff diff_<?php echo $type ?>">

        <?php
        //navigation and header
        if($type == 'inline') {
            if(!$text) { ?>
                <tr>
                    <td class="diff-lineheader">-</td>
                    <td class="diffnav"><?php echo $l_nav ?></td>
                </tr>
                <tr>
                    <th class="diff-lineheader">-</th>
                    <th <?php echo $l_minor ?>>
                        <?php echo $l_head ?>
                    </th>
                </tr>
            <?php } ?>
            <tr>
                <td class="diff-lineheader">+</td>
                <td class="diffnav"><?php echo $r_nav ?></td>
            </tr>
            <tr>
                <th class="diff-lineheader">+</th>
                <th <?php echo $r_minor ?>>
                    <?php echo $r_head ?>
                </th>
            </tr>
        <?php } else {
            if(!$text) { ?>
                <tr>
                    <td colspan="2" class="diffnav"><?php echo $l_nav ?></td>
                    <td colspan="2" class="diffnav"><?php echo $r_nav ?></td>
                </tr>
            <?php } ?>
            <tr>
                <th colspan="2" <?php echo $l_minor ?>>
                    <?php echo $l_head ?>
                </th>
                <th colspan="2" <?php echo $r_minor ?>>
                    <?php echo $r_head ?>
                </th>
            </tr>
        <?php }

        //diff view
        echo html_insert_softbreaks($diffformatter->format($diff)); ?>

    </table>
    </div>
<?php
}

/**
 * Create html for revision navigation
 *
 * @param PageChangeLog $pagelog changelog object of current page
 * @param string        $type    inline vs sidebyside
 * @param int           $l_rev   left revision timestamp
 * @param int           $r_rev   right revision timestamp
 * @return string[] html of left and right navigation elements
 */
function html_diff_navigation($pagelog, $type, $l_rev, $r_rev) {
    global $INFO, $ID;

    // last timestamp is not in changelog, retrieve timestamp from metadata
    // note: when page is removed, the metadata timestamp is zero
    if(!$r_rev) {
        if(isset($INFO['meta']['last_change']['date'])) {
            $r_rev = $INFO['meta']['last_change']['date'];
        } else {
            $r_rev = 0;
        }
    }

    //retrieve revisions with additional info
    list($l_revs, $r_revs) = $pagelog->getRevisionsAround($l_rev, $r_rev);
    $l_revisions = array();
    if(!$l_rev) {
        $l_revisions[0] = array(0, "", false); //no left revision given, add dummy
    }
    foreach($l_revs as $rev) {
        $info = $pagelog->getRevisionInfo($rev);
        $l_revisions[$rev] = array(
            $rev,
            dformat($info['date']) . ' ' . editorinfo($info['user'], true) . ' ' . $info['sum'],
            $r_rev ? $rev >= $r_rev : false //disable?
        );
    }
    $r_revisions = array();
    if(!$r_rev) {
        $r_revisions[0] = array(0, "", false); //no right revision given, add dummy
    }
    foreach($r_revs as $rev) {
        $info = $pagelog->getRevisionInfo($rev);
        $r_revisions[$rev] = array(
            $rev,
            dformat($info['date']) . ' ' . editorinfo($info['user'], true) . ' ' . $info['sum'],
            $rev <= $l_rev //disable?
        );
    }

    //determine previous/next revisions
    $l_index = array_search($l_rev, $l_revs);
    $l_prev = $l_revs[$l_index + 1];
    $l_next = $l_revs[$l_index - 1];
    if($r_rev) {
        $r_index = array_search($r_rev, $r_revs);
        $r_prev = $r_revs[$r_index + 1];
        $r_next = $r_revs[$r_index - 1];
    } else {
        //removed page
        if($l_next) {
            $r_prev = $r_revs[0];
        } else {
            $r_prev = null;
        }
        $r_next = null;
    }

    /*
     * Left side:
     */
    $l_nav = '';
    //move back
    if($l_prev) {
        $l_nav .= html_diff_navigationlink($type, 'diffbothprevrev', $l_prev, $r_prev);
        $l_nav .= html_diff_navigationlink($type, 'diffprevrev', $l_prev, $r_rev);
    }
    //dropdown
    $form = new Doku_Form(array('action' => wl()));
    $form->addHidden('id', $ID);
    $form->addHidden('difftype', $type);
    $form->addHidden('rev2[1]', $r_rev);
    $form->addHidden('do', 'diff');
    $form->addElement(
         form_makeListboxField(
             'rev2[0]',
             $l_revisions,
             $l_rev,
             '', '', '',
             array('class' => 'quickselect')
         )
    );
    $form->addElement(form_makeButton('submit', 'diff', 'Go'));
    $l_nav .= $form->getForm();
    //move forward
    if($l_next && ($l_next < $r_rev || !$r_rev)) {
        $l_nav .= html_diff_navigationlink($type, 'diffnextrev', $l_next, $r_rev);
    }

    /*
     * Right side:
     */
    $r_nav = '';
    //move back
    if($l_rev < $r_prev) {
        $r_nav .= html_diff_navigationlink($type, 'diffprevrev', $l_rev, $r_prev);
    }
    //dropdown
    $form = new Doku_Form(array('action' => wl()));
    $form->addHidden('id', $ID);
    $form->addHidden('rev2[0]', $l_rev);
    $form->addHidden('difftype', $type);
    $form->addHidden('do', 'diff');
    $form->addElement(
         form_makeListboxField(
             'rev2[1]',
             $r_revisions,
             $r_rev,
             '', '', '',
             array('class' => 'quickselect')
         )
    );
    $form->addElement(form_makeButton('submit', 'diff', 'Go'));
    $r_nav .= $form->getForm();
    //move forward
    if($r_next) {
        if($pagelog->isCurrentRevision($r_next)) {
            $r_nav .= html_diff_navigationlink($type, 'difflastrev', $l_rev); //last revision is diff with current page
        } else {
            $r_nav .= html_diff_navigationlink($type, 'diffnextrev', $l_rev, $r_next);
        }
        $r_nav .= html_diff_navigationlink($type, 'diffbothnextrev', $l_next, $r_next);
    }
    return array($l_nav, $r_nav);
}

/**
 * Create html link to a diff defined by two revisions
 *
 * @param string $difftype display type
 * @param string $linktype
 * @param int $lrev oldest revision
 * @param int $rrev newest revision or null for diff with current revision
 * @return string html of link to a diff
 */
function html_diff_navigationlink($difftype, $linktype, $lrev, $rrev = null) {
    global $ID, $lang;
    if(!$rrev) {
        $urlparam = array(
            'do' => 'diff',
            'rev' => $lrev,
            'difftype' => $difftype,
        );
    } else {
        $urlparam = array(
            'do' => 'diff',
            'rev2[0]' => $lrev,
            'rev2[1]' => $rrev,
            'difftype' => $difftype,
        );
    }
    return  '<a class="' . $linktype . '" href="' . wl($ID, $urlparam) . '" title="' . $lang[$linktype] . '">' .
                '<span>' . $lang[$linktype] . '</span>' .
            '</a>' . "\n";
}

/**
 * Insert soft breaks in diff html
 *
 * @param string $diffhtml
 * @return string
 */
function html_insert_softbreaks($diffhtml) {
    // search the diff html string for both:
    // - html tags, so these can be ignored
    // - long strings of characters without breaking characters
    return preg_replace_callback('/<[^>]*>|[^<> ]{12,}/','html_softbreak_callback',$diffhtml);
}

/**
 * callback which adds softbreaks
 *
 * @param array $match array with first the complete match
 * @return string the replacement
 */
function html_softbreak_callback($match){
    // if match is an html tag, return it intact
    if ($match[0]{0} == '<') return $match[0];

    // its a long string without a breaking character,
    // make certain characters into breaking characters by inserting a
    // word break opportunity (<wbr> tag) in front of them.
    $regex = <<< REGEX
(?(?=                                 # start a conditional expression with a positive look ahead ...
&\#?\\w{1,6};)                        # ... for html entities - we don't want to split them (ok to catch some invalid combinations)
&\#?\\w{1,6};                         # yes pattern - a quicker match for the html entity, since we know we have one
|
[?/,&\#;:]                            # no pattern - any other group of 'special' characters to insert a breaking character after
)+                                    # end conditional expression
REGEX;

    return preg_replace('<'.$regex.'>xu','\0<wbr>',$match[0]);
}

/**
 * show warning on conflict detection
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 *
 * @param string $text
 * @param string $summary
 */
function html_conflict($text,$summary){
    global $ID;
    global $lang;

    print p_locale_xhtml('conflict');
    $form = new Doku_Form(array('id' => 'dw__editform'));
    $form->addHidden('id', $ID);
    $form->addHidden('wikitext', $text);
    $form->addHidden('summary', $summary);
    $form->addElement(form_makeButton('submit', 'save', $lang['btn_save'], array('accesskey'=>'s')));
    $form->addElement(form_makeButton('submit', 'cancel', $lang['btn_cancel']));
    html_form('conflict', $form);
    print '<br /><br /><br /><br />'.NL;
}

/**
 * Prints the global message array
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 */
function html_msgarea(){
    global $MSG, $MSG_shown;
    /** @var array $MSG */
    // store if the global $MSG has already been shown and thus HTML output has been started
    $MSG_shown = true;

    if(!isset($MSG)) return;

    $shown = array();
    foreach($MSG as $msg){
        $hash = md5($msg['msg']);
        if(isset($shown[$hash])) continue; // skip double messages
        if(info_msg_allowed($msg)){
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
 */
function html_register(){
    global $lang;
    global $conf;
    global $INPUT;

    $base_attrs = array('size'=>50,'required'=>'required');
    $email_attrs = $base_attrs + array('type'=>'email','class'=>'edit');

    print p_locale_xhtml('register');
    print '<div class="centeralign">'.NL;
    $form = new Doku_Form(array('id' => 'dw__register'));
    $form->startFieldset($lang['btn_register']);
    $form->addHidden('do', 'register');
    $form->addHidden('save', '1');
    $form->addElement(form_makeTextField('login', $INPUT->post->str('login'), $lang['user'], '', 'block', $base_attrs));
    if (!$conf['autopasswd']) {
        $form->addElement(form_makePasswordField('pass', $lang['pass'], '', 'block', $base_attrs));
        $form->addElement(form_makePasswordField('passchk', $lang['passchk'], '', 'block', $base_attrs));
    }
    $form->addElement(form_makeTextField('fullname', $INPUT->post->str('fullname'), $lang['fullname'], '', 'block', $base_attrs));
    $form->addElement(form_makeField('email','email', $INPUT->post->str('email'), $lang['email'], '', 'block', $email_attrs));
    $form->addElement(form_makeButton('submit', '', $lang['btn_register']));
    $form->endFieldset();
    html_form('register', $form);

    print '</div>'.NL;
}

/**
 * Preprocess edit form data
 *
 * @author   Andreas Gohr <andi@splitbrain.org>
 *
 * @triggers HTML_EDITFORM_OUTPUT
 */
function html_edit(){
    global $INPUT;
    global $ID;
    global $REV;
    global $DATE;
    global $PRE;
    global $SUF;
    global $INFO;
    global $SUM;
    global $lang;
    global $conf;
    global $TEXT;

    if ($INPUT->has('changecheck')) {
        $check = $INPUT->str('changecheck');
    } elseif(!$INFO['exists']){
        // $TEXT has been loaded from page template
        $check = md5('');
    } else {
        $check = md5($TEXT);
    }
    $mod = md5($TEXT) !== $check;

    $wr = $INFO['writable'] && !$INFO['locked'];
    $include = 'edit';
    if($wr){
        if ($REV) $include = 'editrev';
    }else{
        // check pseudo action 'source'
        if(!actionOK('source')){
            msg('Command disabled: source',-1);
            return;
        }
        $include = 'read';
    }

    global $license;

    $form = new Doku_Form(array('id' => 'dw__editform'));
    $form->addHidden('id', $ID);
    $form->addHidden('rev', $REV);
    $form->addHidden('date', $DATE);
    $form->addHidden('prefix', $PRE . '.');
    $form->addHidden('suffix', $SUF);
    $form->addHidden('changecheck', $check);

    $data = array('form' => $form,
                  'wr'   => $wr,
                  'media_manager' => true,
                  'target' => ($INPUT->has('target') && $wr) ? $INPUT->str('target') : 'section',
                  'intro_locale' => $include);

    if ($data['target'] !== 'section') {
        // Only emit event if page is writable, section edit data is valid and
        // edit target is not section.
        trigger_event('HTML_EDIT_FORMSELECTION', $data, 'html_edit_form', true);
    } else {
        html_edit_form($data);
    }
    if (isset($data['intro_locale'])) {
        echo p_locale_xhtml($data['intro_locale']);
    }

    $form->addHidden('target', $data['target']);
    if ($INPUT->has('hid')) {
        $form->addHidden('hid', $INPUT->str('hid'));
    }
    if ($INPUT->has('codeblockOffset')) {
        $form->addHidden('codeblockOffset', $INPUT->str('codeblockOffset'));
    }
    $form->addElement(form_makeOpenTag('div', array('id'=>'wiki__editbar', 'class'=>'editBar')));
    $form->addElement(form_makeOpenTag('div', array('id'=>'size__ctl')));
    $form->addElement(form_makeCloseTag('div'));
    if ($wr) {
        $form->addElement(form_makeOpenTag('div', array('class'=>'editButtons')));
        $form->addElement(form_makeButton('submit', 'save', $lang['btn_save'], array('id'=>'edbtn__save', 'accesskey'=>'s', 'tabindex'=>'4')));
        $form->addElement(form_makeButton('submit', 'preview', $lang['btn_preview'], array('id'=>'edbtn__preview', 'accesskey'=>'p', 'tabindex'=>'5')));
        $form->addElement(form_makeButton('submit', 'cancel', $lang['btn_cancel'], array('tabindex'=>'6')));
        $form->addElement(form_makeCloseTag('div'));
        $form->addElement(form_makeOpenTag('div', array('class'=>'summary')));
        $form->addElement(form_makeTextField('summary', $SUM, $lang['summary'], 'edit__summary', 'nowrap', array('size'=>'50', 'tabindex'=>'2')));
        $elem = html_minoredit();
        if ($elem) $form->addElement($elem);
        $form->addElement(form_makeCloseTag('div'));
    }
    $form->addElement(form_makeCloseTag('div'));
    if($wr && $conf['license']){
        $form->addElement(form_makeOpenTag('div', array('class'=>'license')));
        $out  = $lang['licenseok'];
        $out .= ' <a href="'.$license[$conf['license']]['url'].'" rel="license" class="urlextern"';
        if($conf['target']['extern']) $out .= ' target="'.$conf['target']['extern'].'"';
        $out .= '>'.$license[$conf['license']]['name'].'</a>';
        $form->addElement($out);
        $form->addElement(form_makeCloseTag('div'));
    }

    if ($wr) {
        // sets changed to true when previewed
        echo '<script type="text/javascript">/*<![CDATA[*/'. NL;
        echo 'textChanged = ' . ($mod ? 'true' : 'false');
        echo '/*!]]>*/</script>' . NL;
    } ?>
    <div class="editBox" role="application">

    <div class="toolbar group">
        <div id="tool__bar" class="tool__bar"><?php if ($wr && $data['media_manager']){?><a href="<?php echo DOKU_BASE?>lib/exe/mediamanager.php?ns=<?php echo $INFO['namespace']?>"
            target="_blank"><?php echo $lang['mediaselect'] ?></a><?php }?></div>
    </div>
    <div id="draft__status" class="draft__status">
        <?php
        $draft = new \dokuwiki\Draft($ID, $INFO['client']);
        if ($draft->isDraftAvailable()) {
            echo $draft->getDraftMessage();
        }
        ?>
    </div>
    <?php

    html_form('edit', $form);
    print '</div>'.NL;
}

/**
 * Display the default edit form
 *
 * Is the default action for HTML_EDIT_FORMSELECTION.
 *
 * @param mixed[] $param
 */
function html_edit_form($param) {
    global $TEXT;

    if ($param['target'] !== 'section') {
        msg('No editor for edit target ' . hsc($param['target']) . ' found.', -1);
    }

    $attr = array('tabindex'=>'1');
    if (!$param['wr']) $attr['readonly'] = 'readonly';

    $param['form']->addElement(form_makeWikiText($TEXT, $attr));
}

/**
 * Adds a checkbox for minor edits for logged in users
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 *
 * @return array|bool
 */
function html_minoredit(){
    global $conf;
    global $lang;
    global $INPUT;
    // minor edits are for logged in users only
    if(!$conf['useacl'] || !$_SERVER['REMOTE_USER']){
        return false;
    }

    $p = array();
    $p['tabindex'] = 3;
    if($INPUT->bool('minor')) $p['checked']='checked';
    return form_makeCheckboxField('minor', '1', $lang['minoredit'], 'minoredit', 'nowrap', $p);
}

/**
 * prints some debug info
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 */
function html_debug(){
    global $conf;
    global $lang;
    /** @var DokuWiki_Auth_Plugin $auth */
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

    if($auth){
        print '<b>Auth backend capabilities:</b><pre>';
        foreach ($auth->getCapabilities() as $cando){
            print '   '.str_pad($cando,16) . ' => ' . (int)$auth->canDo($cando) . NL;
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
 */
function html_resendpwd() {
    global $lang;
    global $conf;
    global $INPUT;

    $token = preg_replace('/[^a-f0-9]+/','',$INPUT->str('pwauth'));

    if(!$conf['autopasswd'] && $token){
        print p_locale_xhtml('resetpwd');
        print '<div class="centeralign">'.NL;
        $form = new Doku_Form(array('id' => 'dw__resendpwd'));
        $form->startFieldset($lang['btn_resendpwd']);
        $form->addHidden('token', $token);
        $form->addHidden('do', 'resendpwd');

        $form->addElement(form_makePasswordField('pass', $lang['pass'], '', 'block', array('size'=>'50')));
        $form->addElement(form_makePasswordField('passchk', $lang['passchk'], '', 'block', array('size'=>'50')));

        $form->addElement(form_makeButton('submit', '', $lang['btn_resendpwd']));
        $form->endFieldset();
        html_form('resendpwd', $form);
        print '</div>'.NL;
    }else{
        print p_locale_xhtml('resendpwd');
        print '<div class="centeralign">'.NL;
        $form = new Doku_Form(array('id' => 'dw__resendpwd'));
        $form->startFieldset($lang['resendpwd']);
        $form->addHidden('do', 'resendpwd');
        $form->addHidden('save', '1');
        $form->addElement(form_makeTag('br'));
        $form->addElement(form_makeTextField('login', $INPUT->post->str('login'), $lang['user'], '', 'block'));
        $form->addElement(form_makeTag('br'));
        $form->addElement(form_makeTag('br'));
        $form->addElement(form_makeButton('submit', '', $lang['btn_resendpwd']));
        $form->endFieldset();
        html_form('resendpwd', $form);
        print '</div>'.NL;
    }
}

/**
 * Return the TOC rendered to XHTML
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 *
 * @param array $toc
 * @return string html
 */
function html_TOC($toc){
    if(!count($toc)) return '';
    global $lang;
    $out  = '<!-- TOC START -->'.DOKU_LF;
    $out .= '<div id="dw__toc" class="dw__toc">'.DOKU_LF;
    $out .= '<h3 class="toggle">';
    $out .= $lang['toc'];
    $out .= '</h3>'.DOKU_LF;
    $out .= '<div>'.DOKU_LF;
    $out .= html_buildlist($toc,'toc','html_list_toc','html_li_default',true);
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
function html_list_toc($item){
    if(isset($item['hid'])){
        $link = '#'.$item['hid'];
    }else{
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
function html_mktocitem($link, $text, $level, $hash='#'){
    return  array( 'link'  => $hash.$link,
            'title' => $text,
            'type'  => 'ul',
            'level' => $level);
}

/**
 * Output a Doku_Form object.
 * Triggers an event with the form name: HTML_{$name}FORM_OUTPUT
 *
 * @author Tom N Harris <tnharris@whoopdedo.org>
 *
 * @param string     $name The name of the form
 * @param Doku_Form  $form The form
 */
function html_form($name, &$form) {
    // Safety check in case the caller forgets.
    $form->endFieldset();
    trigger_event('HTML_'.strtoupper($name).'FORM_OUTPUT', $form, 'html_form_output', false);
}

/**
 * Form print function.
 * Just calls printForm() on the data object.
 *
 * @param Doku_Form $data The form
 */
function html_form_output($data) {
    $data->printForm();
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
 */
function html_tabs($tabs, $current_tab = null) {
    echo '<ul class="tabs">'.NL;

    foreach($tabs as $id => $tab) {
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
 */

function html_tab($href, $caption, $selected=false) {
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
 * @param Doku_Form $form - form to add elements to
 */

function html_sizechange($sizechange, Doku_Form $form) {
    if(isset($sizechange)) {
        $class = 'sizechange';
        $value = filesize_h(abs($sizechange));
        if($sizechange > 0) {
            $class .= ' positive';
            $value = '+' . $value;
        } elseif($sizechange < 0) {
            $class .= ' negative';
            $value = '-' . $value;
        } else {
            $value = '±' . $value;
        }
        $form->addElement(form_makeOpenTag('span', array('class' => $class)));
        $form->addElement($value);
        $form->addElement(form_makeCloseTag('span'));
    }
}
