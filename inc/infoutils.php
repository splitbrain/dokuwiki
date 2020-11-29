<?php
/**
 * Information and debugging functions
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 */

use dokuwiki\HTTP\DokuHTTPClient;
use dokuwiki\Search\MetadataIndex;
use dokuwiki\Search\FulltextIndex;
use dokuwiki\Utf8;

if(!defined('DOKU_MESSAGEURL')){
    if(in_array('ssl', stream_get_transports())) {
        define('DOKU_MESSAGEURL','https://update.dokuwiki.org/check/');
    }else{
        define('DOKU_MESSAGEURL','http://update.dokuwiki.org/check/');
    }
}

/**
 * Check for new messages from upstream
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 */
function checkUpdateMessages(){
    global $conf;
    global $INFO;
    global $updateVersion;
    if(!$conf['updatecheck']) return;
    if($conf['useacl'] && !$INFO['ismanager']) return;

    $cf = getCacheName($updateVersion, '.updmsg');
    $lm = @filemtime($cf);
    $is_http = substr(DOKU_MESSAGEURL, 0, 5) != 'https';

    // check if new messages needs to be fetched
    if($lm < time()-(60*60*24) || $lm < @filemtime(DOKU_INC.DOKU_SCRIPT)){
        @touch($cf);
        dbglog("checkUpdateMessages(): downloading messages to ".$cf.($is_http?' (without SSL)':' (with SSL)'));
        $http = new DokuHTTPClient();
        $http->timeout = 12;
        $resp = $http->get(DOKU_MESSAGEURL.$updateVersion);
        if(is_string($resp) && ($resp == "" || substr(trim($resp), -1) == '%')) {
            // basic sanity check that this is either an empty string response (ie "no messages")
            // or it looks like one of our messages, not WiFi login or other interposed response
            io_saveFile($cf,$resp);
        } else {
            dbglog("checkUpdateMessages(): unexpected HTTP response received");
        }
    }else{
        dbglog("checkUpdateMessages(): messages up to date");
    }

    $data = io_readFile($cf);
    // show messages through the usual message mechanism
    $msgs = explode("\n%\n",$data);
    foreach($msgs as $msg){
        if($msg) msg($msg,2);
    }
}


/**
 * Return DokuWiki's version (split up in date and type)
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 */
function getVersionData(){
    $version = array();
    //import version string
    if(file_exists(DOKU_INC.'VERSION')){
        //official release
        $version['date'] = trim(io_readFile(DOKU_INC.'VERSION'));
        $version['type'] = 'Release';
    }elseif(is_dir(DOKU_INC.'.git')){
        $version['type'] = 'Git';
        $version['date'] = 'unknown';

        if ($date = shell_exec("git log -1 --pretty=format:'%cd' --date=short")) {
            $version['date'] = hsc($date);
        } else if (file_exists(DOKU_INC . '.git/HEAD')) {
            // we cannot use git on the shell -- let's do it manually!
            $headCommit = trim(file_get_contents(DOKU_INC . '.git/HEAD'));
            if (strpos($headCommit, 'ref: ') === 0) {
                // it is something like `ref: refs/heads/master`
                $pathToHead = substr($headCommit, 5);
                $headCommit = trim(file_get_contents(DOKU_INC . '.git/' . $pathToHead));
            }
            $subDir = substr($headCommit, 0, 2);
            $fileName = substr($headCommit, 2);
            $gitCommitObject = DOKU_INC . ".git/objects/$subDir/$fileName";
            if (file_exists($gitCommitObject) && method_exists(zlib_decode)) {
                $commit = zlib_decode(file_get_contents($gitCommitObject));
                $committerLine = explode("\n", $commit)[3];
                $committerData = explode(' ', $committerLine);
                end($committerData);
                $ts = prev($committerData);
                if ($ts && $date = date('Y-m-d', $ts)) {
                    $version['date'] = $date;
                }
            }
        }
    }else{
        global $updateVersion;
        $version['date'] = 'update version '.$updateVersion;
        $version['type'] = 'snapshot?';
    }
    return $version;
}

/**
 * Return DokuWiki's version (as a string)
 *
 * @author Anika Henke <anika@selfthinker.org>
 */
function getVersion(){
    $version = getVersionData();
    return $version['type'].' '.$version['date'];
}

/**
 * Run a few sanity checks
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 */
function check(){
    global $conf;
    global $INFO;
    /* @var Input $INPUT */
    global $INPUT;

    if ($INFO['isadmin'] || $INFO['ismanager']){
        msg('DokuWiki version: '.getVersion(),1);

        if(version_compare(phpversion(),'7.2.0','<')){
            msg('Your PHP version is too old ('.phpversion().' vs. 7.2+ needed)',-1);
        }else{
            msg('PHP version '.phpversion(),1);
        }
    } else {
        if(version_compare(phpversion(),'7.2.0','<')){
            msg('Your PHP version is too old',-1);
        }
    }

    $mem = (int) php_to_byte(ini_get('memory_limit'));
    if($mem){
        if ($mem === -1) {
            msg('PHP memory is unlimited', 1);
        } else if ($mem < 16777216) {
            msg('PHP is limited to less than 16MB RAM (' . filesize_h($mem) . ').
            Increase memory_limit in php.ini', -1);
        } else if ($mem < 20971520) {
            msg('PHP is limited to less than 20MB RAM (' . filesize_h($mem) . '),
                you might encounter problems with bigger pages. Increase memory_limit in php.ini', -1);
        } else if ($mem < 33554432) {
            msg('PHP is limited to less than 32MB RAM (' . filesize_h($mem) . '),
                but that should be enough in most cases. If not, increase memory_limit in php.ini', 0);
        } else {
            msg('More than 32MB RAM (' . filesize_h($mem) . ') available.', 1);
        }
    }

    if(is_writable($conf['changelog'])){
        msg('Changelog is writable',1);
    }else{
        if (file_exists($conf['changelog'])) {
            msg('Changelog is not writable',-1);
        }
    }

    if (isset($conf['changelog_old']) && file_exists($conf['changelog_old'])) {
        msg('Old changelog exists', 0);
    }

    if (file_exists($conf['changelog'].'_failed')) {
        msg('Importing old changelog failed', -1);
    } else if (file_exists($conf['changelog'].'_importing')) {
        msg('Importing old changelog now.', 0);
    } else if (file_exists($conf['changelog'].'_import_ok')) {
        msg('Old changelog imported', 1);
        if (!plugin_isdisabled('importoldchangelog')) {
            msg('Importoldchangelog plugin not disabled after import', -1);
        }
    }

    if(is_writable(DOKU_CONF)){
        msg('conf directory is writable',1);
    }else{
        msg('conf directory is not writable',-1);
    }

    if($conf['authtype'] == 'plain'){
        global $config_cascade;
        if(is_writable($config_cascade['plainauth.users']['default'])){
            msg('conf/users.auth.php is writable',1);
        }else{
            msg('conf/users.auth.php is not writable',0);
        }
    }

    if(function_exists('mb_strpos')){
        if(defined('UTF8_NOMBSTRING')){
            msg('mb_string extension is available but will not be used',0);
        }else{
            msg('mb_string extension is available and will be used',1);
            if(ini_get('mbstring.func_overload') != 0){
                msg('mb_string function overloading is enabled, this will cause problems and should be disabled',-1);
            }
        }
    }else{
        msg('mb_string extension not available - PHP only replacements will be used',0);
    }

    if (!UTF8_PREGSUPPORT) {
        msg('PHP is missing UTF-8 support in Perl-Compatible Regular Expressions (PCRE)', -1);
    }
    if (!UTF8_PROPERTYSUPPORT) {
        msg('PHP is missing Unicode properties support in Perl-Compatible Regular Expressions (PCRE)', -1);
    }

    $loc = setlocale(LC_ALL, 0);
    if(!$loc){
        msg('No valid locale is set for your PHP setup. You should fix this',-1);
    }elseif(stripos($loc,'utf') === false){
        msg('Your locale <code>'.hsc($loc).'</code> seems not to be a UTF-8 locale,
             you should fix this if you encounter problems.',0);
    }else{
        msg('Valid locale '.hsc($loc).' found.', 1);
    }

    if($conf['allowdebug']){
        msg('Debugging support is enabled. If you don\'t need it you should set $conf[\'allowdebug\'] = 0',-1);
    }else{
        msg('Debugging support is disabled',1);
    }

    if($INFO['userinfo']['name']){
        msg('You are currently logged in as '.$INPUT->server->str('REMOTE_USER').' ('.$INFO['userinfo']['name'].')',0);
        msg('You are part of the groups '.implode(', ', $INFO['userinfo']['grps']),0);
    }else{
        msg('You are currently not logged in',0);
    }

    msg('Your current permission for this page is '.$INFO['perm'],0);

    if (file_exists($INFO['filepath']) && is_writable($INFO['filepath'])) {
        msg('The current page is writable by the webserver', 1);
    } elseif (!file_exists($INFO['filepath']) && is_writable(dirname($INFO['filepath']))) {
        msg('The current page can be created by the webserver', 1);
    } else {
        msg('The current page is not writable by the webserver', -1);
    }

    if ($INFO['writable']) {
        msg('The current page is writable by you', 1);
    } else {
        msg('The current page is not writable by you', -1);
    }

    // Check for corrupted fulltext search index
    $FulltextIndex = new FulltextIndex();
    $lengths = $FulltextIndex->listIndexLengths();
    $index_corrupted = false;
    foreach ($lengths as $length) {
        if (count($FulltextIndex->getIndex('w', $length)) != count($FulltextIndex->getIndex('i', $length))) {
            $index_corrupted = true;
            break;
        }
    }

    // Check for corrupted metadata index
    $MetadataIndex = new MetadataIndex();
    foreach ($MetadataIndex->getIndex('metadata', '') as $name) {
        if (count($MetadataIndex->getIndex($name.'_w', '')) != count($MetadataIndex->getIndex($name.'_i', ''))) {
            $index_corrupted = true;
            break;
        }
    }

    if($index_corrupted) {
        msg(
            'The search index is corrupted. It might produce wrong results and most
                probably needs to be rebuilt. See
                <a href="http://www.dokuwiki.org/faq:searchindex">faq:searchindex</a>
                for ways to rebuild the search index.', -1
        );
    } elseif(!empty($lengths)) {
        msg('The search index seems to be working', 1);
    } else {
        msg(
            'The search index is empty. See
                <a href="http://www.dokuwiki.org/faq:searchindex">faq:searchindex</a>
                for help on how to fix the search index. If the default indexer
                isn\'t used or the wiki is actually empty this is normal.'
        );
    }

    // rough time check
    $http = new DokuHTTPClient();
    $http->max_redirect = 0;
    $http->timeout = 3;
    $http->sendRequest('http://www.dokuwiki.org', '', 'HEAD');
    $now = time();
    if(isset($http->resp_headers['date'])) {
        $time = strtotime($http->resp_headers['date']);
        $diff = $time - $now;

        if(abs($diff) < 4) {
            msg("Server time seems to be okay. Diff: {$diff}s", 1);
        } else {
            msg("Your server's clock seems to be out of sync!
                 Consider configuring a sync with a NTP server.  Diff: {$diff}s");
        }
    }

}

/**
 * Display a message to the user
 *
 * If HTTP headers were not sent yet the message is added
 * to the global message array else it's printed directly
 * using html_msgarea()
 *
 * Triggers INFOUTIL_MSG_SHOW
 *
 * @see    html_msgarea()
 * @param string $message
 * @param int    $lvl   -1 = error, 0 = info, 1 = success, 2 = notify
 * @param string $line  line number
 * @param string $file  file number
 * @param int    $allow who's allowed to see the message, see MSG_* constants
 */
function msg($message,$lvl=0,$line='',$file='',$allow=MSG_PUBLIC){
    global $MSG, $MSG_shown;
    static $errors = [
        -1 => 'error',
        0 => 'info',
        1 => 'success',
        2 => 'notify',
    ];

    $msgdata = [
        'msg' => $message,
        'lvl' => $errors[$lvl],
        'allow' => $allow,
        'line' => $line,
        'file' => $file,
    ];

    $evt = new \dokuwiki\Extension\Event('INFOUTIL_MSG_SHOW', $msgdata);
    if ($evt->advise_before()) {
        /* Show msg normally - event could suppress message show */
        if($msgdata['line'] || $msgdata['file']) {
            $basename = \dokuwiki\Utf8\PhpString::basename($msgdata['file']);
            $msgdata['msg'] .=' ['.$basename.':'.$msgdata['line'].']';
        }

        if(!isset($MSG)) $MSG = array();
        $MSG[] = $msgdata;
        if(isset($MSG_shown) || headers_sent()){
            if(function_exists('html_msgarea')){
                html_msgarea();
            }else{
                print "ERROR(".$msgdata['lvl'].") ".$msgdata['msg']."\n";
            }
            unset($GLOBALS['MSG']);
        }
    }
    $evt->advise_after();
    unset($evt);
}
/**
 * Determine whether the current user is allowed to view the message
 * in the $msg data structure
 *
 * @param  $msg   array    dokuwiki msg structure
 *                         msg   => string, the message
 *                         lvl   => int, level of the message (see msg() function)
 *                         allow => int, flag used to determine who is allowed to see the message
 *                                       see MSG_* constants
 * @return bool
 */
function info_msg_allowed($msg){
    global $INFO, $auth;

    // is the message public? - everyone and anyone can see it
    if (empty($msg['allow']) || ($msg['allow'] == MSG_PUBLIC)) return true;

    // restricted msg, but no authentication
    if (empty($auth)) return false;

    switch ($msg['allow']){
        case MSG_USERS_ONLY:
            return !empty($INFO['userinfo']);

        case MSG_MANAGERS_ONLY:
            return $INFO['ismanager'];

        case MSG_ADMINS_ONLY:
            return $INFO['isadmin'];

        default:
            trigger_error('invalid msg allow restriction.  msg="'.$msg['msg'].'" allow='.$msg['allow'].'"',
                          E_USER_WARNING);
            return $INFO['isadmin'];
    }

    return false;
}

/**
 * print debug messages
 *
 * little function to print the content of a var
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 *
 * @param string $msg
 * @param bool $hidden
 */
function dbg($msg,$hidden=false){
    if($hidden){
        echo "<!--\n";
        print_r($msg);
        echo "\n-->";
    }else{
        echo '<pre class="dbg">';
        echo hsc(print_r($msg,true));
        echo '</pre>';
    }
}

/**
 * Print info to a log file
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 *
 * @param string $msg
 * @param string $header
 */
function dbglog($msg,$header=''){
    global $conf;
    /* @var Input $INPUT */
    global $INPUT;

    // The debug log isn't automatically cleaned thus only write it when
    // debugging has been enabled by the user.
    if($conf['allowdebug'] !== 1) return;
    if(is_object($msg) || is_array($msg)){
        $msg = print_r($msg,true);
    }

    if($header) $msg = "$header\n$msg";

    $file = $conf['cachedir'].'/debug.log';
    $fh = fopen($file,'a');
    if($fh){
        fwrite($fh,date('H:i:s ').$INPUT->server->str('REMOTE_ADDR').': '.$msg."\n");
        fclose($fh);
    }
}

/**
 * Log accesses to deprecated fucntions to the debug log
 *
 * @param string $alternative The function or method that should be used instead
 * @triggers INFO_DEPRECATION_LOG
 */
function dbg_deprecated($alternative = '') {
    \dokuwiki\Debug\DebugHelper::dbgDeprecatedFunction($alternative, 2);
}

/**
 * Print a reversed, prettyprinted backtrace
 *
 * @author Gary Owen <gary_owen@bigfoot.com>
 */
function dbg_backtrace(){
    // Get backtrace
    $backtrace = debug_backtrace();

    // Unset call to debug_print_backtrace
    array_shift($backtrace);

    // Iterate backtrace
    $calls = array();
    $depth = count($backtrace) - 1;
    foreach ($backtrace as $i => $call) {
        $location = $call['file'] . ':' . $call['line'];
        $function = (isset($call['class'])) ?
            $call['class'] . $call['type'] . $call['function'] : $call['function'];

        $params = array();
        if (isset($call['args'])){
            foreach($call['args'] as $arg){
                if(is_object($arg)){
                    $params[] = '[Object '.get_class($arg).']';
                }elseif(is_array($arg)){
                    $params[] = '[Array]';
                }elseif(is_null($arg)){
                    $params[] = '[NULL]';
                }else{
                    $params[] = (string) '"'.$arg.'"';
                }
            }
        }
        $params = implode(', ',$params);

        $calls[$depth - $i] = sprintf('%s(%s) called at %s',
                $function,
                str_replace("\n", '\n', $params),
                $location);
    }
    ksort($calls);

    return implode("\n", $calls);
}

/**
 * Remove all data from an array where the key seems to point to sensitive data
 *
 * This is used to remove passwords, mail addresses and similar data from the
 * debug output
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 *
 * @param array $data
 */
function debug_guard(&$data){
    foreach($data as $key => $value){
        if(preg_match('/(notify|pass|auth|secret|ftp|userinfo|token|buid|mail|proxy)/i',$key)){
            $data[$key] = '***';
            continue;
        }
        if(is_array($value)) debug_guard($data[$key]);
    }
}
