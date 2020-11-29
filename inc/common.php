<?php
/**
 * Common DokuWiki functions
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 */

use dokuwiki\Cache\CacheInstructions;
use dokuwiki\Cache\CacheRenderer;
use dokuwiki\ChangeLog\PageChangeLog;
use dokuwiki\Subscriptions\PageSubscriptionSender;
use dokuwiki\Subscriptions\SubscriberManager;
use dokuwiki\Extension\AuthPlugin;
use dokuwiki\Extension\Event;
use dokuwiki\Search\MetadataIndex;

/**
 * Wrapper around htmlspecialchars()
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 * @see    htmlspecialchars()
 *
 * @param string $string the string being converted
 * @return string converted string
 */
function hsc($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Checks if the given input is blank
 *
 * This is similar to empty() but will return false for "0".
 *
 * Please note: when you pass uninitialized variables, they will implicitly be created
 * with a NULL value without warning.
 *
 * To avoid this it's recommended to guard the call with isset like this:
 *
 * (isset($foo) && !blank($foo))
 * (!isset($foo) || blank($foo))
 *
 * @param $in
 * @param bool $trim Consider a string of whitespace to be blank
 * @return bool
 */
function blank(&$in, $trim = false) {
    if(is_null($in)) return true;
    if(is_array($in)) return empty($in);
    if($in === "\0") return true;
    if($trim && trim($in) === '') return true;
    if(strlen($in) > 0) return false;
    return empty($in);
}

/**
 * print a newline terminated string
 *
 * You can give an indention as optional parameter
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 *
 * @param string $string  line of text
 * @param int    $indent  number of spaces indention
 */
function ptln($string, $indent = 0) {
    echo str_repeat(' ', $indent)."$string\n";
}

/**
 * strips control characters (<32) from the given string
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 *
 * @param string $string being stripped
 * @return string
 */
function stripctl($string) {
    return preg_replace('/[\x00-\x1F]+/s', '', $string);
}

/**
 * Return a secret token to be used for CSRF attack prevention
 *
 * @author  Andreas Gohr <andi@splitbrain.org>
 * @link    http://en.wikipedia.org/wiki/Cross-site_request_forgery
 * @link    http://christ1an.blogspot.com/2007/04/preventing-csrf-efficiently.html
 *
 * @return  string
 */
function getSecurityToken() {
    /** @var Input $INPUT */
    global $INPUT;

    $user = $INPUT->server->str('REMOTE_USER');
    $session = session_id();

    // CSRF checks are only for logged in users - do not generate for anonymous
    if(trim($user) == '' || trim($session) == '') return '';
    return \dokuwiki\PassHash::hmac('md5', $session.$user, auth_cookiesalt());
}

/**
 * Check the secret CSRF token
 *
 * @param null|string $token security token or null to read it from request variable
 * @return bool success if the token matched
 */
function checkSecurityToken($token = null) {
    /** @var Input $INPUT */
    global $INPUT;
    if(!$INPUT->server->str('REMOTE_USER')) return true; // no logged in user, no need for a check

    if(is_null($token)) $token = $INPUT->str('sectok');
    if(getSecurityToken() != $token) {
        msg('Security Token did not match. Possible CSRF attack.', -1);
        return false;
    }
    return true;
}

/**
 * Print a hidden form field with a secret CSRF token
 *
 * @author  Andreas Gohr <andi@splitbrain.org>
 *
 * @param bool $print  if true print the field, otherwise html of the field is returned
 * @return string html of hidden form field
 */
function formSecurityToken($print = true) {
    $ret = '<div class="no"><input type="hidden" name="sectok" value="'.getSecurityToken().'" /></div>'."\n";
    if($print) echo $ret;
    return $ret;
}

/**
 * Determine basic information for a request of $id
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 * @author Chris Smith <chris@jalakai.co.uk>
 *
 * @param string $id         pageid
 * @param bool   $htmlClient add info about whether is mobile browser
 * @return array with info for a request of $id
 *
 */
function basicinfo($id, $htmlClient=true){
    global $USERINFO;
    /* @var Input $INPUT */
    global $INPUT;

    // set info about manager/admin status.
    $info = array();
    $info['isadmin']   = false;
    $info['ismanager'] = false;
    if($INPUT->server->has('REMOTE_USER')) {
        $info['userinfo']   = $USERINFO;
        $info['perm']       = auth_quickaclcheck($id);
        $info['client']     = $INPUT->server->str('REMOTE_USER');

        if($info['perm'] == AUTH_ADMIN) {
            $info['isadmin']   = true;
            $info['ismanager'] = true;
        } elseif(auth_ismanager()) {
            $info['ismanager'] = true;
        }

        // if some outside auth were used only REMOTE_USER is set
        if(!$info['userinfo']['name']) {
            $info['userinfo']['name'] = $INPUT->server->str('REMOTE_USER');
        }

    } else {
        $info['perm']       = auth_aclcheck($id, '', null);
        $info['client']     = clientIP(true);
    }

    $info['namespace'] = getNS($id);

    // mobile detection
    if ($htmlClient) {
        $info['ismobile'] = clientismobile();
    }

    return $info;
 }

/**
 * Return info about the current document as associative
 * array.
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 *
 * @return array with info about current document
 */
function pageinfo() {
    global $ID;
    global $REV;
    global $RANGE;
    global $lang;
    /* @var Input $INPUT */
    global $INPUT;

    $info = basicinfo($ID);

    // include ID & REV not redundant, as some parts of DokuWiki may temporarily change $ID, e.g. p_wiki_xhtml
    // FIXME ... perhaps it would be better to ensure the temporary changes weren't necessary
    $info['id']  = $ID;
    $info['rev'] = $REV;

    $subManager = new SubscriberManager();
    $info['subscribed'] = $subManager->userSubscription();

    $info['locked']     = checklock($ID);
    $info['filepath']   = wikiFN($ID);
    $info['exists']     = file_exists($info['filepath']);
    $info['currentrev'] = @filemtime($info['filepath']);
    if($REV) {
        //check if current revision was meant
        if($info['exists'] && ($info['currentrev'] == $REV)) {
            $REV = '';
        } elseif($RANGE) {
            //section editing does not work with old revisions!
            $REV   = '';
            $RANGE = '';
            msg($lang['nosecedit'], 0);
        } else {
            //really use old revision
            $info['filepath'] = wikiFN($ID, $REV);
            $info['exists']   = file_exists($info['filepath']);
        }
    }
    $info['rev'] = $REV;
    if($info['exists']) {
        $info['writable'] = (is_writable($info['filepath']) &&
            ($info['perm'] >= AUTH_EDIT));
    } else {
        $info['writable'] = ($info['perm'] >= AUTH_CREATE);
    }
    $info['editable'] = ($info['writable'] && empty($info['locked']));
    $info['lastmod']  = @filemtime($info['filepath']);

    //load page meta data
    $info['meta'] = p_get_metadata($ID);

    //who's the editor
    $pagelog = new PageChangeLog($ID, 1024);
    if($REV) {
        $revinfo = $pagelog->getRevisionInfo($REV);
    } else {
        if(!empty($info['meta']['last_change']) && is_array($info['meta']['last_change'])) {
            $revinfo = $info['meta']['last_change'];
        } else {
            $revinfo = $pagelog->getRevisionInfo($info['lastmod']);
            // cache most recent changelog line in metadata if missing and still valid
            if($revinfo !== false) {
                $info['meta']['last_change'] = $revinfo;
                p_set_metadata($ID, array('last_change' => $revinfo));
            }
        }
    }
    //and check for an external edit
    if($revinfo !== false && $revinfo['date'] != $info['lastmod']) {
        // cached changelog line no longer valid
        $revinfo                     = false;
        $info['meta']['last_change'] = $revinfo;
        p_set_metadata($ID, array('last_change' => $revinfo));
    }

    if($revinfo !== false){
        $info['ip']   = $revinfo['ip'];
        $info['user'] = $revinfo['user'];
        $info['sum']  = $revinfo['sum'];
        // See also $INFO['meta']['last_change'] which is the most recent log line for page $ID.
        // Use $INFO['meta']['last_change']['type']===DOKU_CHANGE_TYPE_MINOR_EDIT in place of $info['minor'].

        if($revinfo['user']) {
            $info['editor'] = $revinfo['user'];
        } else {
            $info['editor'] = $revinfo['ip'];
        }
    }else{
        $info['ip']     = null;
        $info['user']   = null;
        $info['sum']    = null;
        $info['editor'] = null;
    }

    // draft
    $draft = new \dokuwiki\Draft($ID, $info['client']);
    if ($draft->isDraftAvailable()) {
        $info['draft'] = $draft->getDraftFilename();
    }

    return $info;
}

/**
 * Initialize and/or fill global $JSINFO with some basic info to be given to javascript
 */
function jsinfo() {
    global $JSINFO, $ID, $INFO, $ACT;

    if (!is_array($JSINFO)) {
        $JSINFO = [];
    }
    //export minimal info to JS, plugins can add more
    $JSINFO['id']                    = $ID;
    $JSINFO['namespace']             = isset($INFO) ? (string) $INFO['namespace'] : '';
    $JSINFO['ACT']                   = act_clean($ACT);
    $JSINFO['useHeadingNavigation']  = (int) useHeading('navigation');
    $JSINFO['useHeadingContent']     = (int) useHeading('content');
}

/**
 * Return information about the current media item as an associative array.
 *
 * @return array with info about current media item
 */
function mediainfo(){
    global $NS;
    global $IMG;

    $info = basicinfo("$NS:*");
    $info['image'] = $IMG;

    return $info;
}

/**
 * Build an string of URL parameters
 *
 * @author Andreas Gohr
 *
 * @param array  $params    array with key-value pairs
 * @param string $sep       series of pairs are separated by this character
 * @return string query string
 */
function buildURLparams($params, $sep = '&amp;') {
    $url = '';
    $amp = false;
    foreach($params as $key => $val) {
        if($amp) $url .= $sep;

        $url .= rawurlencode($key).'=';
        $url .= rawurlencode((string) $val);
        $amp = true;
    }
    return $url;
}

/**
 * Build an string of html tag attributes
 *
 * Skips keys starting with '_', values get HTML encoded
 *
 * @author Andreas Gohr
 *
 * @param array $params           array with (attribute name-attribute value) pairs
 * @param bool  $skipEmptyStrings skip empty string values?
 * @return string
 */
function buildAttributes($params, $skipEmptyStrings = false) {
    $url   = '';
    $white = false;
    foreach($params as $key => $val) {
        if($key[0] == '_') continue;
        if($val === '' && $skipEmptyStrings) continue;
        if($white) $url .= ' ';

        $url .= $key.'="';
        $url .= htmlspecialchars($val);
        $url .= '"';
        $white = true;
    }
    return $url;
}

/**
 * This builds the breadcrumb trail and returns it as array
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 *
 * @return string[] with the data: array(pageid=>name, ... )
 */
function breadcrumbs() {
    // we prepare the breadcrumbs early for quick session closing
    static $crumbs = null;
    if($crumbs != null) return $crumbs;

    global $ID;
    global $ACT;
    global $conf;
    global $INFO;

    //first visit?
    $crumbs = isset($_SESSION[DOKU_COOKIE]['bc']) ? $_SESSION[DOKU_COOKIE]['bc'] : array();
    //we only save on show and existing visible readable wiki documents
    $file = wikiFN($ID);
    if($ACT != 'show' || $INFO['perm'] < AUTH_READ || isHiddenPage($ID) || !file_exists($file)) {
        $_SESSION[DOKU_COOKIE]['bc'] = $crumbs;
        return $crumbs;
    }

    // page names
    $name = noNSorNS($ID);
    if(useHeading('navigation')) {
        // get page title
        $title = p_get_first_heading($ID, METADATA_RENDER_USING_SIMPLE_CACHE);
        if($title) {
            $name = $title;
        }
    }

    //remove ID from array
    if(isset($crumbs[$ID])) {
        unset($crumbs[$ID]);
    }

    //add to array
    $crumbs[$ID] = $name;
    //reduce size
    while(count($crumbs) > $conf['breadcrumbs']) {
        array_shift($crumbs);
    }
    //save to session
    $_SESSION[DOKU_COOKIE]['bc'] = $crumbs;
    return $crumbs;
}

/**
 * Filter for page IDs
 *
 * This is run on a ID before it is outputted somewhere
 * currently used to replace the colon with something else
 * on Windows (non-IIS) systems and to have proper URL encoding
 *
 * See discussions at https://github.com/splitbrain/dokuwiki/pull/84 and
 * https://github.com/splitbrain/dokuwiki/pull/173 why we use a whitelist of
 * unaffected servers instead of blacklisting affected servers here.
 *
 * Urlencoding is ommitted when the second parameter is false
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 *
 * @param string $id pageid being filtered
 * @param bool   $ue apply urlencoding?
 * @return string
 */
function idfilter($id, $ue = true) {
    global $conf;
    /* @var Input $INPUT */
    global $INPUT;

    if($conf['useslash'] && $conf['userewrite']) {
        $id = strtr($id, ':', '/');
    } elseif(strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' &&
        $conf['userewrite'] &&
        strpos($INPUT->server->str('SERVER_SOFTWARE'), 'Microsoft-IIS') === false
    ) {
        $id = strtr($id, ':', ';');
    }
    if($ue) {
        $id = rawurlencode($id);
        $id = str_replace('%3A', ':', $id); //keep as colon
        $id = str_replace('%3B', ';', $id); //keep as semicolon
        $id = str_replace('%2F', '/', $id); //keep as slash
    }
    return $id;
}

/**
 * This builds a link to a wikipage
 *
 * It handles URL rewriting and adds additional parameters
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 *
 * @param string       $id             page id, defaults to start page
 * @param string|array $urlParameters  URL parameters, associative array recommended
 * @param bool         $absolute       request an absolute URL instead of relative
 * @param string       $separator      parameter separator
 * @return string
 */
function wl($id = '', $urlParameters = '', $absolute = false, $separator = '&amp;') {
    global $conf;
    if(is_array($urlParameters)) {
        if(isset($urlParameters['rev']) && !$urlParameters['rev']) unset($urlParameters['rev']);
        if(isset($urlParameters['at']) && $conf['date_at_format']) {
            $urlParameters['at'] = date($conf['date_at_format'], $urlParameters['at']);
        }
        $urlParameters = buildURLparams($urlParameters, $separator);
    } else {
        $urlParameters = str_replace(',', $separator, $urlParameters);
    }
    if($id === '') {
        $id = $conf['start'];
    }
    $id = idfilter($id);
    if($absolute) {
        $xlink = DOKU_URL;
    } else {
        $xlink = DOKU_BASE;
    }

    if($conf['userewrite'] == 2) {
        $xlink .= DOKU_SCRIPT.'/'.$id;
        if($urlParameters) $xlink .= '?'.$urlParameters;
    } elseif($conf['userewrite']) {
        $xlink .= $id;
        if($urlParameters) $xlink .= '?'.$urlParameters;
    } elseif($id !== '') {
        $xlink .= DOKU_SCRIPT.'?id='.$id;
        if($urlParameters) $xlink .= $separator.$urlParameters;
    } else {
        $xlink .= DOKU_SCRIPT;
        if($urlParameters) $xlink .= '?'.$urlParameters;
    }

    return $xlink;
}

/**
 * This builds a link to an alternate page format
 *
 * Handles URL rewriting if enabled. Follows the style of wl().
 *
 * @author Ben Coburn <btcoburn@silicodon.net>
 * @param string       $id             page id, defaults to start page
 * @param string       $format         the export renderer to use
 * @param string|array $urlParameters  URL parameters, associative array recommended
 * @param bool         $abs            request an absolute URL instead of relative
 * @param string       $sep            parameter separator
 * @return string
 */
function exportlink($id = '', $format = 'raw', $urlParameters = '', $abs = false, $sep = '&amp;') {
    global $conf;
    if(is_array($urlParameters)) {
        $urlParameters = buildURLparams($urlParameters, $sep);
    } else {
        $urlParameters = str_replace(',', $sep, $urlParameters);
    }

    $format = rawurlencode($format);
    $id     = idfilter($id);
    if($abs) {
        $xlink = DOKU_URL;
    } else {
        $xlink = DOKU_BASE;
    }

    if($conf['userewrite'] == 2) {
        $xlink .= DOKU_SCRIPT.'/'.$id.'?do=export_'.$format;
        if($urlParameters) $xlink .= $sep.$urlParameters;
    } elseif($conf['userewrite'] == 1) {
        $xlink .= '_export/'.$format.'/'.$id;
        if($urlParameters) $xlink .= '?'.$urlParameters;
    } else {
        $xlink .= DOKU_SCRIPT.'?do=export_'.$format.$sep.'id='.$id;
        if($urlParameters) $xlink .= $sep.$urlParameters;
    }

    return $xlink;
}

/**
 * Build a link to a media file
 *
 * Will return a link to the detail page if $direct is false
 *
 * The $more parameter should always be given as array, the function then
 * will strip default parameters to produce even cleaner URLs
 *
 * @param string  $id     the media file id or URL
 * @param mixed   $more   string or array with additional parameters
 * @param bool    $direct link to detail page if false
 * @param string  $sep    URL parameter separator
 * @param bool    $abs    Create an absolute URL
 * @return string
 */
function ml($id = '', $more = '', $direct = true, $sep = '&amp;', $abs = false) {
    global $conf;
    $isexternalimage = media_isexternal($id);
    if(!$isexternalimage) {
        $id = cleanID($id);
    }

    if(is_array($more)) {
        // add token for resized images
        if(!empty($more['w']) || !empty($more['h']) || $isexternalimage){
            $more['tok'] = media_get_token($id,$more['w'],$more['h']);
        }
        // strip defaults for shorter URLs
        if(isset($more['cache']) && $more['cache'] == 'cache') unset($more['cache']);
        if(empty($more['w'])) unset($more['w']);
        if(empty($more['h'])) unset($more['h']);
        if(isset($more['id']) && $direct) unset($more['id']);
        if(isset($more['rev']) && !$more['rev']) unset($more['rev']);
        $more = buildURLparams($more, $sep);
    } else {
        $matches = array();
        if (preg_match_all('/\b(w|h)=(\d*)\b/',$more,$matches,PREG_SET_ORDER) || $isexternalimage){
            $resize = array('w'=>0, 'h'=>0);
            foreach ($matches as $match){
                $resize[$match[1]] = $match[2];
            }
            $more .= $more === '' ? '' : $sep;
            $more .= 'tok='.media_get_token($id,$resize['w'],$resize['h']);
        }
        $more = str_replace('cache=cache', '', $more); //skip default
        $more = str_replace(',,', ',', $more);
        $more = str_replace(',', $sep, $more);
    }

    if($abs) {
        $xlink = DOKU_URL;
    } else {
        $xlink = DOKU_BASE;
    }

    // external URLs are always direct without rewriting
    if($isexternalimage) {
        $xlink .= 'lib/exe/fetch.php';
        $xlink .= '?'.$more;
        $xlink .= $sep.'media='.rawurlencode($id);
        return $xlink;
    }

    $id = idfilter($id);

    // decide on scriptname
    if($direct) {
        if($conf['userewrite'] == 1) {
            $script = '_media';
        } else {
            $script = 'lib/exe/fetch.php';
        }
    } else {
        if($conf['userewrite'] == 1) {
            $script = '_detail';
        } else {
            $script = 'lib/exe/detail.php';
        }
    }

    // build URL based on rewrite mode
    if($conf['userewrite']) {
        $xlink .= $script.'/'.$id;
        if($more) $xlink .= '?'.$more;
    } else {
        if($more) {
            $xlink .= $script.'?'.$more;
            $xlink .= $sep.'media='.$id;
        } else {
            $xlink .= $script.'?media='.$id;
        }
    }

    return $xlink;
}

/**
 * Returns the URL to the DokuWiki base script
 *
 * Consider using wl() instead, unless you absoutely need the doku.php endpoint
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 *
 * @return string
 */
function script() {
    return DOKU_BASE.DOKU_SCRIPT;
}

/**
 * Spamcheck against wordlist
 *
 * Checks the wikitext against a list of blocked expressions
 * returns true if the text contains any bad words
 *
 * Triggers COMMON_WORDBLOCK_BLOCKED
 *
 *  Action Plugins can use this event to inspect the blocked data
 *  and gain information about the user who was blocked.
 *
 *  Event data:
 *    data['matches']  - array of matches
 *    data['userinfo'] - information about the blocked user
 *      [ip]           - ip address
 *      [user]         - username (if logged in)
 *      [mail]         - mail address (if logged in)
 *      [name]         - real name (if logged in)
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 * @author Michael Klier <chi@chimeric.de>
 *
 * @param  string $text - optional text to check, if not given the globals are used
 * @return bool         - true if a spam word was found
 */
function checkwordblock($text = '') {
    global $TEXT;
    global $PRE;
    global $SUF;
    global $SUM;
    global $conf;
    global $INFO;
    /* @var Input $INPUT */
    global $INPUT;

    if(!$conf['usewordblock']) return false;

    if(!$text) $text = "$PRE $TEXT $SUF $SUM";

    // we prepare the text a tiny bit to prevent spammers circumventing URL checks
    // phpcs:disable Generic.Files.LineLength.TooLong
    $text = preg_replace(
        '!(\b)(www\.[\w.:?\-;,]+?\.[\w.:?\-;,]+?[\w/\#~:.?+=&%@\!\-.:?\-;,]+?)([.:?\-;,]*[^\w/\#~:.?+=&%@\!\-.:?\-;,])!i',
        '\1http://\2 \2\3',
        $text
    );
    // phpcs:enable

    $wordblocks = getWordblocks();
    // how many lines to read at once (to work around some PCRE limits)
    if(version_compare(phpversion(), '4.3.0', '<')) {
        // old versions of PCRE define a maximum of parenthesises even if no
        // backreferences are used - the maximum is 99
        // this is very bad performancewise and may even be too high still
        $chunksize = 40;
    } else {
        // read file in chunks of 200 - this should work around the
        // MAX_PATTERN_SIZE in modern PCRE
        $chunksize = 200;
    }
    while($blocks = array_splice($wordblocks, 0, $chunksize)) {
        $re = array();
        // build regexp from blocks
        foreach($blocks as $block) {
            $block = preg_replace('/#.*$/', '', $block);
            $block = trim($block);
            if(empty($block)) continue;
            $re[] = $block;
        }
        if(count($re) && preg_match('#('.join('|', $re).')#si', $text, $matches)) {
            // prepare event data
            $data = array();
            $data['matches']        = $matches;
            $data['userinfo']['ip'] = $INPUT->server->str('REMOTE_ADDR');
            if($INPUT->server->str('REMOTE_USER')) {
                $data['userinfo']['user'] = $INPUT->server->str('REMOTE_USER');
                $data['userinfo']['name'] = $INFO['userinfo']['name'];
                $data['userinfo']['mail'] = $INFO['userinfo']['mail'];
            }
            $callback = function () {
                return true;
            };
            return Event::createAndTrigger('COMMON_WORDBLOCK_BLOCKED', $data, $callback, true);
        }
    }
    return false;
}

/**
 * Return the IP of the client
 *
 * Honours X-Forwarded-For and X-Real-IP Proxy Headers
 *
 * It returns a comma separated list of IPs if the above mentioned
 * headers are set. If the single parameter is set, it tries to return
 * a routable public address, prefering the ones suplied in the X
 * headers
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 *
 * @param  boolean $single If set only a single IP is returned
 * @return string
 */
function clientIP($single = false) {
    /* @var Input $INPUT */
    global $INPUT, $conf;

    $ip   = array();
    $ip[] = $INPUT->server->str('REMOTE_ADDR');
    if($INPUT->server->str('HTTP_X_FORWARDED_FOR')) {
        $ip = array_merge($ip, explode(',', str_replace(' ', '', $INPUT->server->str('HTTP_X_FORWARDED_FOR'))));
    }
    if($INPUT->server->str('HTTP_X_REAL_IP')) {
        $ip = array_merge($ip, explode(',', str_replace(' ', '', $INPUT->server->str('HTTP_X_REAL_IP'))));
    }

    // some IPv4/v6 regexps borrowed from Feyd
    // see: http://forums.devnetwork.net/viewtopic.php?f=38&t=53479
    $dec_octet   = '(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]\d|[0-9])';
    $hex_digit   = '[A-Fa-f0-9]';
    $h16         = "{$hex_digit}{1,4}";
    $IPv4Address = "$dec_octet\\.$dec_octet\\.$dec_octet\\.$dec_octet";
    $ls32        = "(?:$h16:$h16|$IPv4Address)";
    $IPv6Address =
        "(?:(?:{$IPv4Address})|(?:".
            "(?:$h16:){6}$ls32".
            "|::(?:$h16:){5}$ls32".
            "|(?:$h16)?::(?:$h16:){4}$ls32".
            "|(?:(?:$h16:){0,1}$h16)?::(?:$h16:){3}$ls32".
            "|(?:(?:$h16:){0,2}$h16)?::(?:$h16:){2}$ls32".
            "|(?:(?:$h16:){0,3}$h16)?::(?:$h16:){1}$ls32".
            "|(?:(?:$h16:){0,4}$h16)?::$ls32".
            "|(?:(?:$h16:){0,5}$h16)?::$h16".
            "|(?:(?:$h16:){0,6}$h16)?::".
            ")(?:\\/(?:12[0-8]|1[0-1][0-9]|[1-9][0-9]|[0-9]))?)";

    // remove any non-IP stuff
    $cnt   = count($ip);
    $match = array();
    for($i = 0; $i < $cnt; $i++) {
        if(preg_match("/^$IPv4Address$/", $ip[$i], $match) || preg_match("/^$IPv6Address$/", $ip[$i], $match)) {
            $ip[$i] = $match[0];
        } else {
            $ip[$i] = '';
        }
        if(empty($ip[$i])) unset($ip[$i]);
    }
    $ip = array_values(array_unique($ip));
    if(!$ip[0]) $ip[0] = '0.0.0.0'; // for some strange reason we don't have a IP

    if(!$single) return join(',', $ip);

    // skip trusted local addresses
    foreach($ip as $i) {
        if(!empty($conf['trustedproxy']) && preg_match('/'.$conf['trustedproxy'].'/', $i)) {
            continue;
        } else {
            return $i;
        }
    }

    // still here? just use the last address
    // this case all ips in the list are trusted
    return $ip[count($ip)-1];
}

/**
 * Check if the browser is on a mobile device
 *
 * Adapted from the example code at url below
 *
 * @link http://www.brainhandles.com/2007/10/15/detecting-mobile-browsers/#code
 *
 * @deprecated 2018-04-27 you probably want media queries instead anyway
 * @return bool if true, client is mobile browser; otherwise false
 */
function clientismobile() {
    /* @var Input $INPUT */
    global $INPUT;

    if($INPUT->server->has('HTTP_X_WAP_PROFILE')) return true;

    if(preg_match('/wap\.|\.wap/i', $INPUT->server->str('HTTP_ACCEPT'))) return true;

    if(!$INPUT->server->has('HTTP_USER_AGENT')) return false;

    $uamatches = join(
        '|',
        [
            'midp', 'j2me', 'avantg', 'docomo', 'novarra', 'palmos', 'palmsource', '240x320', 'opwv',
            'chtml', 'pda', 'windows ce', 'mmp\/', 'blackberry', 'mib\/', 'symbian', 'wireless', 'nokia',
            'hand', 'mobi', 'phone', 'cdm', 'up\.b', 'audio', 'SIE\-', 'SEC\-', 'samsung', 'HTC', 'mot\-',
            'mitsu', 'sagem', 'sony', 'alcatel', 'lg', 'erics', 'vx', 'NEC', 'philips', 'mmm', 'xx',
            'panasonic', 'sharp', 'wap', 'sch', 'rover', 'pocket', 'benq', 'java', 'pt', 'pg', 'vox',
            'amoi', 'bird', 'compal', 'kg', 'voda', 'sany', 'kdd', 'dbt', 'sendo', 'sgh', 'gradi', 'jb',
            '\d\d\di', 'moto'
        ]
    );

    if(preg_match("/$uamatches/i", $INPUT->server->str('HTTP_USER_AGENT'))) return true;

    return false;
}

/**
 * check if a given link is interwiki link
 *
 * @param string $link the link, e.g. "wiki>page"
 * @return bool
 */
function link_isinterwiki($link){
    if (preg_match('/^[a-zA-Z0-9\.]+>/u',$link)) return true;
    return false;
}

/**
 * Convert one or more comma separated IPs to hostnames
 *
 * If $conf['dnslookups'] is disabled it simply returns the input string
 *
 * @author Glen Harris <astfgl@iamnota.org>
 *
 * @param  string $ips comma separated list of IP addresses
 * @return string a comma separated list of hostnames
 */
function gethostsbyaddrs($ips) {
    global $conf;
    if(!$conf['dnslookups']) return $ips;

    $hosts = array();
    $ips   = explode(',', $ips);

    if(is_array($ips)) {
        foreach($ips as $ip) {
            $hosts[] = gethostbyaddr(trim($ip));
        }
        return join(',', $hosts);
    } else {
        return gethostbyaddr(trim($ips));
    }
}

/**
 * Checks if a given page is currently locked.
 *
 * removes stale lockfiles
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 *
 * @param string $id page id
 * @return bool page is locked?
 */
function checklock($id) {
    global $conf;
    /* @var Input $INPUT */
    global $INPUT;

    $lock = wikiLockFN($id);

    //no lockfile
    if(!file_exists($lock)) return false;

    //lockfile expired
    if((time() - filemtime($lock)) > $conf['locktime']) {
        @unlink($lock);
        return false;
    }

    //my own lock
    @list($ip, $session) = explode("\n", io_readFile($lock));
    if($ip == $INPUT->server->str('REMOTE_USER') || $ip == clientIP() || (session_id() && $session == session_id())) {
        return false;
    }

    return $ip;
}

/**
 * Lock a page for editing
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 *
 * @param string $id page id to lock
 */
function lock($id) {
    global $conf;
    /* @var Input $INPUT */
    global $INPUT;

    if($conf['locktime'] == 0) {
        return;
    }

    $lock = wikiLockFN($id);
    if($INPUT->server->str('REMOTE_USER')) {
        io_saveFile($lock, $INPUT->server->str('REMOTE_USER'));
    } else {
        io_saveFile($lock, clientIP()."\n".session_id());
    }
}

/**
 * Unlock a page if it was locked by the user
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 *
 * @param string $id page id to unlock
 * @return bool true if a lock was removed
 */
function unlock($id) {
    /* @var Input $INPUT */
    global $INPUT;

    $lock = wikiLockFN($id);
    if(file_exists($lock)) {
        @list($ip, $session) = explode("\n", io_readFile($lock));
        if($ip == $INPUT->server->str('REMOTE_USER') || $ip == clientIP() || $session == session_id()) {
            @unlink($lock);
            return true;
        }
    }
    return false;
}

/**
 * convert line ending to unix format
 *
 * also makes sure the given text is valid UTF-8
 *
 * @see    formText() for 2crlf conversion
 * @author Andreas Gohr <andi@splitbrain.org>
 *
 * @param string $text
 * @return string
 */
function cleanText($text) {
    $text = preg_replace("/(\015\012)|(\015)/", "\012", $text);

    // if the text is not valid UTF-8 we simply assume latin1
    // this won't break any worse than it breaks with the wrong encoding
    // but might actually fix the problem in many cases
    if(!\dokuwiki\Utf8\Clean::isUtf8($text)) $text = utf8_encode($text);

    return $text;
}

/**
 * Prepares text for print in Webforms by encoding special chars.
 * It also converts line endings to Windows format which is
 * pseudo standard for webforms.
 *
 * @see    cleanText() for 2unix conversion
 * @author Andreas Gohr <andi@splitbrain.org>
 *
 * @param string $text
 * @return string
 */
function formText($text) {
    $text = str_replace("\012", "\015\012", $text);
    return htmlspecialchars($text);
}

/**
 * Returns the specified local text in raw format
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 *
 * @param string $id   page id
 * @param string $ext  extension of file being read, default 'txt'
 * @return string
 */
function rawLocale($id, $ext = 'txt') {
    return io_readFile(localeFN($id, $ext));
}

/**
 * Returns the raw WikiText
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 *
 * @param string $id   page id
 * @param string|int $rev  timestamp when a revision of wikitext is desired
 * @return string
 */
function rawWiki($id, $rev = '') {
    return io_readWikiPage(wikiFN($id, $rev), $id, $rev);
}

/**
 * Returns the pagetemplate contents for the ID's namespace
 *
 * @triggers COMMON_PAGETPL_LOAD
 * @author Andreas Gohr <andi@splitbrain.org>
 *
 * @param string $id the id of the page to be created
 * @return string parsed pagetemplate content
 */
function pageTemplate($id) {
    global $conf;

    if(is_array($id)) $id = $id[0];

    // prepare initial event data
    $data = array(
        'id'        => $id, // the id of the page to be created
        'tpl'       => '', // the text used as template
        'tplfile'   => '', // the file above text was/should be loaded from
        'doreplace' => true // should wildcard replacements be done on the text?
    );

    $evt = new Event('COMMON_PAGETPL_LOAD', $data);
    if($evt->advise_before(true)) {
        // the before event might have loaded the content already
        if(empty($data['tpl'])) {
            // if the before event did not set a template file, try to find one
            if(empty($data['tplfile'])) {
                $path = dirname(wikiFN($id));
                if(file_exists($path.'/_template.txt')) {
                    $data['tplfile'] = $path.'/_template.txt';
                } else {
                    // search upper namespaces for templates
                    $len = strlen(rtrim($conf['datadir'], '/'));
                    while(strlen($path) >= $len) {
                        if(file_exists($path.'/__template.txt')) {
                            $data['tplfile'] = $path.'/__template.txt';
                            break;
                        }
                        $path = substr($path, 0, strrpos($path, '/'));
                    }
                }
            }
            // load the content
            $data['tpl'] = io_readFile($data['tplfile']);
        }
        if($data['doreplace']) parsePageTemplate($data);
    }
    $evt->advise_after();
    unset($evt);

    return $data['tpl'];
}

/**
 * Performs common page template replacements
 * This works on data from COMMON_PAGETPL_LOAD
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 *
 * @param array $data array with event data
 * @return string
 */
function parsePageTemplate(&$data) {
    /**
     * @var string $id        the id of the page to be created
     * @var string $tpl       the text used as template
     * @var string $tplfile   the file above text was/should be loaded from
     * @var bool   $doreplace should wildcard replacements be done on the text?
     */
    extract($data);

    global $USERINFO;
    global $conf;
    /* @var Input $INPUT */
    global $INPUT;

    // replace placeholders
    $file = noNS($id);
    $page = strtr($file, $conf['sepchar'], ' ');

    $tpl = str_replace(
        array(
             '@ID@',
             '@NS@',
             '@CURNS@',
             '@!CURNS@',
             '@!!CURNS@',
             '@!CURNS!@',
             '@FILE@',
             '@!FILE@',
             '@!FILE!@',
             '@PAGE@',
             '@!PAGE@',
             '@!!PAGE@',
             '@!PAGE!@',
             '@USER@',
             '@NAME@',
             '@MAIL@',
             '@DATE@',
        ),
        array(
             $id,
             getNS($id),
             curNS($id),
             \dokuwiki\Utf8\PhpString::ucfirst(curNS($id)),
             \dokuwiki\Utf8\PhpString::ucwords(curNS($id)),
             \dokuwiki\Utf8\PhpString::strtoupper(curNS($id)),
             $file,
             \dokuwiki\Utf8\PhpString::ucfirst($file),
             \dokuwiki\Utf8\PhpString::strtoupper($file),
             $page,
             \dokuwiki\Utf8\PhpString::ucfirst($page),
             \dokuwiki\Utf8\PhpString::ucwords($page),
             \dokuwiki\Utf8\PhpString::strtoupper($page),
             $INPUT->server->str('REMOTE_USER'),
             $USERINFO ? $USERINFO['name'] : '',
             $USERINFO ? $USERINFO['mail'] : '',
             $conf['dformat'],
        ), $tpl
    );

    // we need the callback to work around strftime's char limit
    $tpl = preg_replace_callback(
        '/%./',
        function ($m) {
            return strftime($m[0]);
        },
        $tpl
    );
    $data['tpl'] = $tpl;
    return $tpl;
}

/**
 * Returns the raw Wiki Text in three slices.
 *
 * The range parameter needs to have the form "from-to"
 * and gives the range of the section in bytes - no
 * UTF-8 awareness is needed.
 * The returned order is prefix, section and suffix.
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 *
 * @param string $range in form "from-to"
 * @param string $id    page id
 * @param string $rev   optional, the revision timestamp
 * @return string[] with three slices
 */
function rawWikiSlices($range, $id, $rev = '') {
    $text = io_readWikiPage(wikiFN($id, $rev), $id, $rev);

    // Parse range
    list($from, $to) = explode('-', $range, 2);
    // Make range zero-based, use defaults if marker is missing
    $from = !$from ? 0 : ($from - 1);
    $to   = !$to ? strlen($text) : ($to - 1);

    $slices = array();
    $slices[0] = substr($text, 0, $from);
    $slices[1] = substr($text, $from, $to - $from);
    $slices[2] = substr($text, $to);
    return $slices;
}

/**
 * Joins wiki text slices
 *
 * function to join the text slices.
 * When the pretty parameter is set to true it adds additional empty
 * lines between sections if needed (used on saving).
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 *
 * @param string $pre   prefix
 * @param string $text  text in the middle
 * @param string $suf   suffix
 * @param bool $pretty add additional empty lines between sections
 * @return string
 */
function con($pre, $text, $suf, $pretty = false) {
    if($pretty) {
        if($pre !== '' && substr($pre, -1) !== "\n" &&
            substr($text, 0, 1) !== "\n"
        ) {
            $pre .= "\n";
        }
        if($suf !== '' && substr($text, -1) !== "\n" &&
            substr($suf, 0, 1) !== "\n"
        ) {
            $text .= "\n";
        }
    }

    return $pre.$text.$suf;
}

/**
 * Checks if the current page version is newer than the last entry in the page's
 * changelog. If so, we assume it has been an external edit and we create an
 * attic copy and add a proper changelog line.
 *
 * This check is only executed when the page is about to be saved again from the
 * wiki, triggered in @see saveWikiText()
 *
 * @param string $id the page ID
 */
function detectExternalEdit($id) {
    global $lang;

    $fileLastMod = wikiFN($id);
    $lastMod     = @filemtime($fileLastMod); // from page
    $pagelog     = new PageChangeLog($id, 1024);
    $lastRev     = $pagelog->getRevisions(-1, 1); // from changelog
    $lastRev     = (int) (empty($lastRev) ? 0 : $lastRev[0]);

    if(!file_exists(wikiFN($id, $lastMod)) && file_exists($fileLastMod) && $lastMod >= $lastRev) {
        // add old revision to the attic if missing
        saveOldRevision($id);
        // add a changelog entry if this edit came from outside dokuwiki
        if($lastMod > $lastRev) {
            $fileLastRev = wikiFN($id, $lastRev);
            $revinfo = $pagelog->getRevisionInfo($lastRev);
            if(empty($lastRev) || !file_exists($fileLastRev) || $revinfo['type'] == DOKU_CHANGE_TYPE_DELETE) {
                $filesize_old = 0;
            } else {
                $filesize_old = io_getSizeFile($fileLastRev);
            }
            $filesize_new = filesize($fileLastMod);
            $sizechange = $filesize_new - $filesize_old;

            addLogEntry(
                $lastMod,
                $id,
                DOKU_CHANGE_TYPE_EDIT,
                $lang['external_edit'],
                '',
                array('ExternalEdit' => true),
                $sizechange
            );
            // remove soon to be stale instructions
            $cache = new CacheInstructions($id, $fileLastMod);
            $cache->removeCache();
        }
    }
}

/**
 * Saves a wikitext by calling io_writeWikiPage.
 * Also directs changelog and attic updates.
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 * @author Ben Coburn <btcoburn@silicodon.net>
 *
 * @param string $id       page id
 * @param string $text     wikitext being saved
 * @param string $summary  summary of text update
 * @param bool   $minor    mark this saved version as minor update
 */
function saveWikiText($id, $text, $summary, $minor = false) {
    /* Note to developers:
       This code is subtle and delicate. Test the behavior of
       the attic and changelog with dokuwiki and external edits
       after any changes. External edits change the wiki page
       directly without using php or dokuwiki.
     */
    global $conf;
    global $lang;
    global $REV;
    /* @var Input $INPUT */
    global $INPUT;

    // prepare data for event
    $svdta = array();
    $svdta['id']             = $id;
    $svdta['file']           = wikiFN($id);
    $svdta['revertFrom']     = $REV;
    $svdta['oldRevision']    = @filemtime($svdta['file']);
    $svdta['newRevision']    = 0;
    $svdta['newContent']     = $text;
    $svdta['oldContent']     = rawWiki($id);
    $svdta['summary']        = $summary;
    $svdta['contentChanged'] = ($svdta['newContent'] != $svdta['oldContent']);
    $svdta['changeInfo']     = '';
    $svdta['changeType']     = DOKU_CHANGE_TYPE_EDIT;
    $svdta['sizechange']     = null;

    // select changelog line type
    if ($REV) {
        $svdta['changeType']  = DOKU_CHANGE_TYPE_REVERT;
        $svdta['changeInfo'] = $REV;
    } elseif (!file_exists($svdta['file'])) {
        $svdta['changeType'] = DOKU_CHANGE_TYPE_CREATE;
    } elseif (trim($text) == '') {
        // empty or whitespace only content deletes
        $svdta['changeType'] = DOKU_CHANGE_TYPE_DELETE;
        // autoset summary on deletion
        if (blank($svdta['summary'])) {
            $svdta['summary'] = $lang['deleted'];
        }
    } elseif ($minor && $conf['useacl'] && $INPUT->server->str('REMOTE_USER')) {
        //minor edits only for logged in users
        $svdta['changeType'] = DOKU_CHANGE_TYPE_MINOR_EDIT;
    }

    $event = new Event('COMMON_WIKIPAGE_SAVE', $svdta);
    if (!$event->advise_before()) return;

    // if the content has not been changed, no save happens (plugins may override this)
    if(!$svdta['contentChanged']) return;

    detectExternalEdit($id);

    if (
        $svdta['changeType'] == DOKU_CHANGE_TYPE_CREATE ||
        ($svdta['changeType'] == DOKU_CHANGE_TYPE_REVERT && !file_exists($svdta['file']))
    ) {
        $filesize_old = 0;
    } else {
        $filesize_old = filesize($svdta['file']);
    }
    if ($svdta['changeType'] == DOKU_CHANGE_TYPE_DELETE) {
        // Send "update" event with empty data, so plugins can react to page deletion
        $data = array(array($svdta['file'], '', false), getNS($id), noNS($id), false);
        Event::createAndTrigger('IO_WIKIPAGE_WRITE', $data);
        // pre-save deleted revision
        @touch($svdta['file']);
        clearstatcache();
        $svdta['newRevision'] = saveOldRevision($id);
        // remove empty file
        @unlink($svdta['file']);
        $filesize_new = 0;
        // don't remove old meta info as it should be saved, plugins can use
        // IO_WIKIPAGE_WRITE for removing their metadata...
        // purge non-persistant meta data
        p_purge_metadata($id);
        // remove empty namespaces
        io_sweepNS($id, 'datadir');
        io_sweepNS($id, 'mediadir');
    } else {
        // save file (namespace dir is created in io_writeWikiPage)
        io_writeWikiPage($svdta['file'], $svdta['newContent'], $id);
        // pre-save the revision, to keep the attic in sync
        $svdta['newRevision'] = saveOldRevision($id);
        $filesize_new = filesize($svdta['file']);
    }
    $svdta['sizechange'] = $filesize_new - $filesize_old;

    $event->advise_after();

    addLogEntry(
        $svdta['newRevision'],
        $svdta['id'],
        $svdta['changeType'],
        $svdta['summary'],
        $svdta['changeInfo'],
        null,
        $svdta['sizechange']
    );

    // send notify mails
    notify($svdta['id'], 'admin', $svdta['oldRevision'], $svdta['summary'], $minor, $svdta['newRevision']);
    notify($svdta['id'], 'subscribers', $svdta['oldRevision'], $svdta['summary'], $minor, $svdta['newRevision']);

    // update the purgefile (timestamp of the last time anything within the wiki was changed)
    io_saveFile($conf['cachedir'].'/purgefile', time());

    // if useheading is enabled, purge the cache of all linking pages
    if (useHeading('content')) {
        $pages = (new MetadataIndex())->backlinks($id, true);
        foreach ($pages as $page) {
            $cache = new CacheRenderer($page, wikiFN($page), 'xhtml');
            $cache->removeCache();
        }
    }
}

/**
 * moves the current version to the attic and returns its
 * revision date
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 *
 * @param string $id page id
 * @return int|string revision timestamp
 */
function saveOldRevision($id) {
    $oldf = wikiFN($id);
    if(!file_exists($oldf)) return '';
    $date = filemtime($oldf);
    $newf = wikiFN($id, $date);
    io_writeWikiPage($newf, rawWiki($id), $id, $date);
    return $date;
}

/**
 * Sends a notify mail on page change or registration
 *
 * @param string     $id       The changed page
 * @param string     $who      Who to notify (admin|subscribers|register)
 * @param int|string $rev Old page revision
 * @param string     $summary  What changed
 * @param boolean    $minor    Is this a minor edit?
 * @param string[]   $replace  Additional string substitutions, @KEY@ to be replaced by value
 * @param int|string $current_rev  New page revision
 * @return bool
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 */
function notify($id, $who, $rev = '', $summary = '', $minor = false, $replace = array(), $current_rev = false) {
    global $conf;
    /* @var Input $INPUT */
    global $INPUT;

    // decide if there is something to do, eg. whom to mail
    if($who == 'admin') {
        if(empty($conf['notify'])) return false; //notify enabled?
        $tpl = 'mailtext';
        $to  = $conf['notify'];
    } elseif($who == 'subscribers') {
        if(!actionOK('subscribe')) return false; //subscribers enabled?
        if($conf['useacl'] && $INPUT->server->str('REMOTE_USER') && $minor) return false; //skip minors
        $data = array('id' => $id, 'addresslist' => '', 'self' => false, 'replacements' => $replace);
        Event::createAndTrigger(
            'COMMON_NOTIFY_ADDRESSLIST', $data,
            array(new SubscriberManager(), 'notifyAddresses')
        );
        $to = $data['addresslist'];
        if(empty($to)) return false;
        $tpl = 'subscr_single';
    } else {
        return false; //just to be safe
    }

    // prepare content
    $subscription = new PageSubscriptionSender();
    return $subscription->sendPageDiff($to, $tpl, $id, $rev, $summary, $current_rev);
}

/**
 * extracts the query from a search engine referrer
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 * @author Todd Augsburger <todd@rollerorgans.com>
 *
 * @return array|string
 */
function getGoogleQuery() {
    /* @var Input $INPUT */
    global $INPUT;

    if(!$INPUT->server->has('HTTP_REFERER')) {
        return '';
    }
    $url = parse_url($INPUT->server->str('HTTP_REFERER'));

    // only handle common SEs
    if(!preg_match('/(google|bing|yahoo|ask|duckduckgo|babylon|aol|yandex)/',$url['host'])) return '';

    $query = array();
    parse_str($url['query'], $query);

    $q = '';
    if(isset($query['q'])){
        $q = $query['q'];
    }elseif(isset($query['p'])){
        $q = $query['p'];
    }elseif(isset($query['query'])){
        $q = $query['query'];
    }
    $q = trim($q);

    if(!$q) return '';
    // ignore if query includes a full URL
    if(strpos($q, '//') !== false) return '';
    $q = preg_split('/[\s\'"\\\\`()\]\[?:!\.{};,#+*<>\\/]+/', $q, -1, PREG_SPLIT_NO_EMPTY);
    return $q;
}

/**
 * Return the human readable size of a file
 *
 * @param int $size A file size
 * @param int $dec A number of decimal places
 * @return string human readable size
 *
 * @author      Martin Benjamin <b.martin@cybernet.ch>
 * @author      Aidan Lister <aidan@php.net>
 * @version     1.0.0
 */
function filesize_h($size, $dec = 1) {
    $sizes = array('B', 'KB', 'MB', 'GB');
    $count = count($sizes);
    $i     = 0;

    while($size >= 1024 && ($i < $count - 1)) {
        $size /= 1024;
        $i++;
    }

    return round($size, $dec)."\xC2\xA0".$sizes[$i]; //non-breaking space
}

/**
 * Return the given timestamp as human readable, fuzzy age
 *
 * @author Andreas Gohr <gohr@cosmocode.de>
 *
 * @param int $dt timestamp
 * @return string
 */
function datetime_h($dt) {
    global $lang;

    $ago = time() - $dt;
    if($ago > 24 * 60 * 60 * 30 * 12 * 2) {
        return sprintf($lang['years'], round($ago / (24 * 60 * 60 * 30 * 12)));
    }
    if($ago > 24 * 60 * 60 * 30 * 2) {
        return sprintf($lang['months'], round($ago / (24 * 60 * 60 * 30)));
    }
    if($ago > 24 * 60 * 60 * 7 * 2) {
        return sprintf($lang['weeks'], round($ago / (24 * 60 * 60 * 7)));
    }
    if($ago > 24 * 60 * 60 * 2) {
        return sprintf($lang['days'], round($ago / (24 * 60 * 60)));
    }
    if($ago > 60 * 60 * 2) {
        return sprintf($lang['hours'], round($ago / (60 * 60)));
    }
    if($ago > 60 * 2) {
        return sprintf($lang['minutes'], round($ago / (60)));
    }
    return sprintf($lang['seconds'], $ago);
}

/**
 * Wraps around strftime but provides support for fuzzy dates
 *
 * The format default to $conf['dformat']. It is passed to
 * strftime - %f can be used to get the value from datetime_h()
 *
 * @see datetime_h
 * @author Andreas Gohr <gohr@cosmocode.de>
 *
 * @param int|null $dt      timestamp when given, null will take current timestamp
 * @param string   $format  empty default to $conf['dformat'], or provide format as recognized by strftime()
 * @return string
 */
function dformat($dt = null, $format = '') {
    global $conf;

    if(is_null($dt)) $dt = time();
    $dt = (int) $dt;
    if(!$format) $format = $conf['dformat'];

    $format = str_replace('%f', datetime_h($dt), $format);
    return strftime($format, $dt);
}

/**
 * Formats a timestamp as ISO 8601 date
 *
 * @author <ungu at terong dot com>
 * @link http://php.net/manual/en/function.date.php#54072
 *
 * @param int $int_date current date in UNIX timestamp
 * @return string
 */
function date_iso8601($int_date) {
    $date_mod     = date('Y-m-d\TH:i:s', $int_date);
    $pre_timezone = date('O', $int_date);
    $time_zone    = substr($pre_timezone, 0, 3).":".substr($pre_timezone, 3, 2);
    $date_mod .= $time_zone;
    return $date_mod;
}

/**
 * return an obfuscated email address in line with $conf['mailguard'] setting
 *
 * @author Harry Fuecks <hfuecks@gmail.com>
 * @author Christopher Smith <chris@jalakai.co.uk>
 *
 * @param string $email email address
 * @return string
 */
function obfuscate($email) {
    global $conf;

    switch($conf['mailguard']) {
        case 'visible' :
            $obfuscate = array('@' => ' [at] ', '.' => ' [dot] ', '-' => ' [dash] ');
            return strtr($email, $obfuscate);

        case 'hex' :
            return \dokuwiki\Utf8\Conversion::toHtml($email, true);

        case 'none' :
        default :
            return $email;
    }
}

/**
 * Removes quoting backslashes
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 *
 * @param string $string
 * @param string $char backslashed character
 * @return string
 */
function unslash($string, $char = "'") {
    return str_replace('\\'.$char, $char, $string);
}

/**
 * Convert php.ini shorthands to byte
 *
 * On 32 bit systems values >= 2GB will fail!
 *
 * -1 (infinite size) will be reported as -1
 *
 * @link   https://www.php.net/manual/en/faq.using.php#faq.using.shorthandbytes
 * @param string $value PHP size shorthand
 * @return int
 */
function php_to_byte($value) {
    switch (strtoupper(substr($value,-1))) {
        case 'G':
            $ret = intval(substr($value, 0, -1)) * 1024 * 1024 * 1024;
            break;
        case 'M':
            $ret = intval(substr($value, 0, -1)) * 1024 * 1024;
            break;
        case 'K':
            $ret = intval(substr($value, 0, -1)) * 1024;
            break;
        default:
            $ret = intval($value);
            break;
    }
    return $ret;
}

/**
 * Wrapper around preg_quote adding the default delimiter
 *
 * @param string $string
 * @return string
 */
function preg_quote_cb($string) {
    return preg_quote($string, '/');
}

/**
 * Shorten a given string by removing data from the middle
 *
 * You can give the string in two parts, the first part $keep
 * will never be shortened. The second part $short will be cut
 * in the middle to shorten but only if at least $min chars are
 * left to display it. Otherwise it will be left off.
 *
 * @param string $keep   the part to keep
 * @param string $short  the part to shorten
 * @param int    $max    maximum chars you want for the whole string
 * @param int    $min    minimum number of chars to have left for middle shortening
 * @param string $char   the shortening character to use
 * @return string
 */
function shorten($keep, $short, $max, $min = 9, $char = '…') {
    $max = $max - \dokuwiki\Utf8\PhpString::strlen($keep);
    if($max < $min) return $keep;
    $len = \dokuwiki\Utf8\PhpString::strlen($short);
    if($len <= $max) return $keep.$short;
    $half = floor($max / 2);
    return $keep .
        \dokuwiki\Utf8\PhpString::substr($short, 0, $half - 1) .
        $char .
        \dokuwiki\Utf8\PhpString::substr($short, $len - $half);
}

/**
 * Return the users real name or e-mail address for use
 * in page footer and recent changes pages
 *
 * @param string|null $username or null when currently logged-in user should be used
 * @param bool $textonly true returns only plain text, true allows returning html
 * @return string html or plain text(not escaped) of formatted user name
 *
 * @author Andy Webber <dokuwiki AT andywebber DOT com>
 */
function editorinfo($username, $textonly = false) {
    return userlink($username, $textonly);
}

/**
 * Returns users realname w/o link
 *
 * @param string|null $username or null when currently logged-in user should be used
 * @param bool $textonly true returns only plain text, true allows returning html
 * @return string html or plain text(not escaped) of formatted user name
 *
 * @triggers COMMON_USER_LINK
 */
function userlink($username = null, $textonly = false) {
    global $conf, $INFO;
    /** @var AuthPlugin $auth */
    global $auth;
    /** @var Input $INPUT */
    global $INPUT;

    // prepare initial event data
    $data = array(
        'username' => $username, // the unique user name
        'name' => '',
        'link' => array( //setting 'link' to false disables linking
                         'target' => '',
                         'pre' => '',
                         'suf' => '',
                         'style' => '',
                         'more' => '',
                         'url' => '',
                         'title' => '',
                         'class' => ''
        ),
        'userlink' => '', // formatted user name as will be returned
        'textonly' => $textonly
    );
    if($username === null) {
        $data['username'] = $username = $INPUT->server->str('REMOTE_USER');
        if($textonly){
            $data['name'] = $INFO['userinfo']['name']. ' (' . $INPUT->server->str('REMOTE_USER') . ')';
        }else {
            $data['name'] = '<bdi>' . hsc($INFO['userinfo']['name']) . '</bdi> '.
                '(<bdi>' . hsc($INPUT->server->str('REMOTE_USER')) . '</bdi>)';
        }
    }

    $evt = new Event('COMMON_USER_LINK', $data);
    if($evt->advise_before(true)) {
        if(empty($data['name'])) {
            if($auth) $info = $auth->getUserData($username);
            if($conf['showuseras'] != 'loginname' && isset($info) && $info) {
                switch($conf['showuseras']) {
                    case 'username':
                    case 'username_link':
                        $data['name'] = $textonly ? $info['name'] : hsc($info['name']);
                        break;
                    case 'email':
                    case 'email_link':
                        $data['name'] = obfuscate($info['mail']);
                        break;
                }
            } else {
                $data['name'] = $textonly ? $data['username'] : hsc($data['username']);
            }
        }

        /** @var Doku_Renderer_xhtml $xhtml_renderer */
        static $xhtml_renderer = null;

        if(!$data['textonly'] && empty($data['link']['url'])) {

            if(in_array($conf['showuseras'], array('email_link', 'username_link'))) {
                if(!isset($info)) {
                    if($auth) $info = $auth->getUserData($username);
                }
                if(isset($info) && $info) {
                    if($conf['showuseras'] == 'email_link') {
                        $data['link']['url'] = 'mailto:' . obfuscate($info['mail']);
                    } else {
                        if(is_null($xhtml_renderer)) {
                            $xhtml_renderer = p_get_renderer('xhtml');
                        }
                        if(empty($xhtml_renderer->interwiki)) {
                            $xhtml_renderer->interwiki = getInterwiki();
                        }
                        $shortcut = 'user';
                        $exists = null;
                        $data['link']['url'] = $xhtml_renderer->_resolveInterWiki($shortcut, $username, $exists);
                        $data['link']['class'] .= ' interwiki iw_user';
                        if($exists !== null) {
                            if($exists) {
                                $data['link']['class'] .= ' wikilink1';
                            } else {
                                $data['link']['class'] .= ' wikilink2';
                                $data['link']['rel'] = 'nofollow';
                            }
                        }
                    }
                } else {
                    $data['textonly'] = true;
                }

            } else {
                $data['textonly'] = true;
            }
        }

        if($data['textonly']) {
            $data['userlink'] = $data['name'];
        } else {
            $data['link']['name'] = $data['name'];
            if(is_null($xhtml_renderer)) {
                $xhtml_renderer = p_get_renderer('xhtml');
            }
            $data['userlink'] = $xhtml_renderer->_formatLink($data['link']);
        }
    }
    $evt->advise_after();
    unset($evt);

    return $data['userlink'];
}

/**
 * Returns the path to a image file for the currently chosen license.
 * When no image exists, returns an empty string
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 *
 * @param  string $type - type of image 'badge' or 'button'
 * @return string
 */
function license_img($type) {
    global $license;
    global $conf;
    if(!$conf['license']) return '';
    if(!is_array($license[$conf['license']])) return '';
    $try   = array();
    $try[] = 'lib/images/license/'.$type.'/'.$conf['license'].'.png';
    $try[] = 'lib/images/license/'.$type.'/'.$conf['license'].'.gif';
    if(substr($conf['license'], 0, 3) == 'cc-') {
        $try[] = 'lib/images/license/'.$type.'/cc.png';
    }
    foreach($try as $src) {
        if(file_exists(DOKU_INC.$src)) return $src;
    }
    return '';
}

/**
 * Checks if the given amount of memory is available
 *
 * If the memory_get_usage() function is not available the
 * function just assumes $bytes of already allocated memory
 *
 * @author Filip Oscadal <webmaster@illusionsoftworks.cz>
 * @author Andreas Gohr <andi@splitbrain.org>
 *
 * @param int  $mem    Size of memory you want to allocate in bytes
 * @param int  $bytes  already allocated memory (see above)
 * @return bool
 */
function is_mem_available($mem, $bytes = 1048576) {
    $limit = trim(ini_get('memory_limit'));
    if(empty($limit)) return true; // no limit set!
    if($limit == -1) return true; // unlimited

    // parse limit to bytes
    $limit = php_to_byte($limit);

    // get used memory if possible
    if(function_exists('memory_get_usage')) {
        $used = memory_get_usage();
    } else {
        $used = $bytes;
    }

    if($used + $mem > $limit) {
        return false;
    }

    return true;
}

/**
 * Send a HTTP redirect to the browser
 *
 * Works arround Microsoft IIS cookie sending bug. Exits the script.
 *
 * @link   http://support.microsoft.com/kb/q176113/
 * @author Andreas Gohr <andi@splitbrain.org>
 *
 * @param string $url url being directed to
 */
function send_redirect($url) {
    $url = stripctl($url); // defend against HTTP Response Splitting

    /* @var Input $INPUT */
    global $INPUT;

    //are there any undisplayed messages? keep them in session for display
    global $MSG;
    if(isset($MSG) && count($MSG) && !defined('NOSESSION')) {
        //reopen session, store data and close session again
        @session_start();
        $_SESSION[DOKU_COOKIE]['msg'] = $MSG;
    }

    // always close the session
    session_write_close();

    // check if running on IIS < 6 with CGI-PHP
    if($INPUT->server->has('SERVER_SOFTWARE') && $INPUT->server->has('GATEWAY_INTERFACE') &&
        (strpos($INPUT->server->str('GATEWAY_INTERFACE'), 'CGI') !== false) &&
        (preg_match('|^Microsoft-IIS/(\d)\.\d$|', trim($INPUT->server->str('SERVER_SOFTWARE')), $matches)) &&
        $matches[1] < 6
    ) {
        header('Refresh: 0;url='.$url);
    } else {
        header('Location: '.$url);
    }

    // no exits during unit tests
    if(defined('DOKU_UNITTEST')) {
        // pass info about the redirect back to the test suite
        $testRequest = TestRequest::getRunning();
        if($testRequest !== null) {
            $testRequest->addData('send_redirect', $url);
        }
        return;
    }

    exit;
}

/**
 * Validate a value using a set of valid values
 *
 * This function checks whether a specified value is set and in the array
 * $valid_values. If not, the function returns a default value or, if no
 * default is specified, throws an exception.
 *
 * @param string $param        The name of the parameter
 * @param array  $valid_values A set of valid values; Optionally a default may
 *                             be marked by the key “default”.
 * @param array  $array        The array containing the value (typically $_POST
 *                             or $_GET)
 * @param string $exc          The text of the raised exception
 *
 * @throws Exception
 * @return mixed
 * @author Adrian Lang <lang@cosmocode.de>
 */
function valid_input_set($param, $valid_values, $array, $exc = '') {
    if(isset($array[$param]) && in_array($array[$param], $valid_values)) {
        return $array[$param];
    } elseif(isset($valid_values['default'])) {
        return $valid_values['default'];
    } else {
        throw new Exception($exc);
    }
}

/**
 * Read a preference from the DokuWiki cookie
 * (remembering both keys & values are urlencoded)
 *
 * @param string $pref     preference key
 * @param mixed  $default  value returned when preference not found
 * @return string preference value
 */
function get_doku_pref($pref, $default) {
    $enc_pref = urlencode($pref);
    if(isset($_COOKIE['DOKU_PREFS']) && strpos($_COOKIE['DOKU_PREFS'], $enc_pref) !== false) {
        $parts = explode('#', $_COOKIE['DOKU_PREFS']);
        $cnt   = count($parts);

        // due to #2721 there might be duplicate entries,
        // so we read from the end
        for($i = $cnt-2; $i >= 0; $i -= 2) {
            if($parts[$i] == $enc_pref) {
                return urldecode($parts[$i + 1]);
            }
        }
    }
    return $default;
}

/**
 * Add a preference to the DokuWiki cookie
 * (remembering $_COOKIE['DOKU_PREFS'] is urlencoded)
 * Remove it by setting $val to false
 *
 * @param string $pref  preference key
 * @param string $val   preference value
 */
function set_doku_pref($pref, $val) {
    global $conf;
    $orig = get_doku_pref($pref, false);
    $cookieVal = '';

    if($orig !== false && ($orig !== $val)) {
        $parts = explode('#', $_COOKIE['DOKU_PREFS']);
        $cnt   = count($parts);
        // urlencode $pref for the comparison
        $enc_pref = rawurlencode($pref);
        $seen = false;
        for ($i = 0; $i < $cnt; $i += 2) {
            if ($parts[$i] == $enc_pref) {
                if (!$seen){
                    if ($val !== false) {
                        $parts[$i + 1] = rawurlencode($val);
                    } else {
                        unset($parts[$i]);
                        unset($parts[$i + 1]);
                    }
                    $seen = true;
                } else {
                    // no break because we want to remove duplicate entries
                    unset($parts[$i]);
                    unset($parts[$i + 1]);
                }
            }
        }
        $cookieVal = implode('#', $parts);
    } else if ($orig === false && $val !== false) {
        $cookieVal = ($_COOKIE['DOKU_PREFS'] ? $_COOKIE['DOKU_PREFS'] . '#' : '') .
            rawurlencode($pref) . '#' . rawurlencode($val);
    }

    $cookieDir = empty($conf['cookiedir']) ? DOKU_REL : $conf['cookiedir'];
    if(defined('DOKU_UNITTEST')) {
        $_COOKIE['DOKU_PREFS'] = $cookieVal;
    }else{
        setcookie('DOKU_PREFS', $cookieVal, time()+365*24*3600, $cookieDir, '', ($conf['securecookie'] && is_ssl()));
    }
}

/**
 * Strips source mapping declarations from given text #601
 *
 * @param string &$text reference to the CSS or JavaScript code to clean
 */
function stripsourcemaps(&$text){
    $text = preg_replace('/^(\/\/|\/\*)[@#]\s+sourceMappingURL=.*?(\*\/)?$/im', '\\1\\2', $text);
}

/**
 * Returns the contents of a given SVG file for embedding
 *
 * Inlining SVGs saves on HTTP requests and more importantly allows for styling them through
 * CSS. However it should used with small SVGs only. The $maxsize setting ensures only small
 * files are embedded.
 *
 * This strips unneeded headers, comments and newline. The result is not a vaild standalone SVG!
 *
 * @param string $file full path to the SVG file
 * @param int $maxsize maximum allowed size for the SVG to be embedded
 * @return string|false the SVG content, false if the file couldn't be loaded
 */
function inlineSVG($file, $maxsize = 2048) {
    $file = trim($file);
    if($file === '') return false;
    if(!file_exists($file)) return false;
    if(filesize($file) > $maxsize) return false;
    if(!is_readable($file)) return false;
    $content = file_get_contents($file);
    $content = preg_replace('/<!--.*?(-->)/s','', $content); // comments
    $content = preg_replace('/<\?xml .*?\?>/i', '', $content); // xml header
    $content = preg_replace('/<!DOCTYPE .*?>/i', '', $content); // doc type
    $content = preg_replace('/>\s+</s', '><', $content); // newlines between tags
    $content = trim($content);
    if(substr($content, 0, 5) !== '<svg ') return false;
    return $content;
}

//Setup VIM: ex: et ts=2 :
