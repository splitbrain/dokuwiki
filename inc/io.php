<?php
/**
 * File IO functions
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 */

use dokuwiki\HTTP\DokuHTTPClient;
use dokuwiki\Extension\Event;

/**
 * Removes empty directories
 *
 * Sends IO_NAMESPACE_DELETED events for 'pages' and 'media' namespaces.
 * Event data:
 * $data[0]    ns: The colon separated namespace path minus the trailing page name.
 * $data[1]    ns_type: 'pages' or 'media' namespace tree.
 *
 * @param string $id      - a pageid, the namespace of that id will be tried to deleted
 * @param string $basedir - the config name of the type to delete (datadir or mediadir usally)
 * @return bool - true if at least one namespace was deleted
 *
 * @author  Andreas Gohr <andi@splitbrain.org>
 * @author Ben Coburn <btcoburn@silicodon.net>
 */
function io_sweepNS($id,$basedir='datadir'){
    global $conf;
    $types = array ('datadir'=>'pages', 'mediadir'=>'media');
    $ns_type = (isset($types[$basedir])?$types[$basedir]:false);

    $delone = false;

    //scan all namespaces
    while(($id = getNS($id)) !== false){
        $dir = $conf[$basedir].'/'.utf8_encodeFN(str_replace(':','/',$id));

        //try to delete dir else return
        if(@rmdir($dir)) {
            if ($ns_type!==false) {
                $data = array($id, $ns_type);
                $delone = true; // we deleted at least one dir
                Event::createAndTrigger('IO_NAMESPACE_DELETED', $data);
            }
        } else { return $delone; }
    }
    return $delone;
}

/**
 * Used to read in a DokuWiki page from file, and send IO_WIKIPAGE_READ events.
 *
 * Generates the action event which delegates to io_readFile().
 * Action plugins are allowed to modify the page content in transit.
 * The file path should not be changed.
 *
 * Event data:
 * $data[0]    The raw arguments for io_readFile as an array.
 * $data[1]    ns: The colon separated namespace path minus the trailing page name. (false if root ns)
 * $data[2]    page_name: The wiki page name.
 * $data[3]    rev: The page revision, false for current wiki pages.
 *
 * @author Ben Coburn <btcoburn@silicodon.net>
 *
 * @param string   $file filename
 * @param string   $id page id
 * @param bool|int $rev revision timestamp
 * @return string
 */
function io_readWikiPage($file, $id, $rev=false) {
    if (empty($rev)) { $rev = false; }
    $data = array(array($file, true), getNS($id), noNS($id), $rev);
    return Event::createAndTrigger('IO_WIKIPAGE_READ', $data, '_io_readWikiPage_action', false);
}

/**
 * Callback adapter for io_readFile().
 *
 * @author Ben Coburn <btcoburn@silicodon.net>
 *
 * @param array $data event data
 * @return string
 */
function _io_readWikiPage_action($data) {
    if (is_array($data) && is_array($data[0]) && count($data[0])===2) {
        return call_user_func_array('io_readFile', $data[0]);
    } else {
        return ''; //callback error
    }
}

/**
 * Returns content of $file as cleaned string.
 *
 * Uses gzip if extension is .gz
 *
 * If you want to use the returned value in unserialize
 * be sure to set $clean to false!
 *
 * @author  Andreas Gohr <andi@splitbrain.org>
 *
 * @param string $file  filename
 * @param bool   $clean
 * @return string|bool the file contents or false on error
 */
function io_readFile($file,$clean=true){
    $ret = '';
    if(file_exists($file)){
        if(substr($file,-3) == '.gz'){
            if(!DOKU_HAS_GZIP) return false;
            $ret = gzfile($file);
            if(is_array($ret)) $ret = join('', $ret);
        }else if(substr($file,-4) == '.bz2'){
            if(!DOKU_HAS_BZIP) return false;
            $ret = bzfile($file);
        }else{
            $ret = file_get_contents($file);
        }
    }
    if($ret === null) return false;
    if($ret !== false && $clean){
        return cleanText($ret);
    }else{
        return $ret;
    }
}
/**
 * Returns the content of a .bz2 compressed file as string
 *
 * @author marcel senf <marcel@rucksackreinigung.de>
 * @author  Andreas Gohr <andi@splitbrain.org>
 *
 * @param string $file filename
 * @param bool   $array return array of lines
 * @return string|array|bool content or false on error
 */
function bzfile($file, $array=false) {
    $bz = bzopen($file,"r");
    if($bz === false) return false;

    if($array) $lines = array();
    $str = '';
    while (!feof($bz)) {
        //8192 seems to be the maximum buffersize?
        $buffer = bzread($bz,8192);
        if(($buffer === false) || (bzerrno($bz) !== 0)) {
            return false;
        }
        $str = $str . $buffer;
        if($array) {
            $pos = strpos($str, "\n");
            while($pos !== false) {
                $lines[] = substr($str, 0, $pos+1);
                $str = substr($str, $pos+1);
                $pos = strpos($str, "\n");
            }
        }
    }
    bzclose($bz);
    if($array) {
        if($str !== '') $lines[] = $str;
        return $lines;
    }
    return $str;
}

/**
 * Used to write out a DokuWiki page to file, and send IO_WIKIPAGE_WRITE events.
 *
 * This generates an action event and delegates to io_saveFile().
 * Action plugins are allowed to modify the page content in transit.
 * The file path should not be changed.
 * (The append parameter is set to false.)
 *
 * Event data:
 * $data[0]    The raw arguments for io_saveFile as an array.
 * $data[1]    ns: The colon separated namespace path minus the trailing page name. (false if root ns)
 * $data[2]    page_name: The wiki page name.
 * $data[3]    rev: The page revision, false for current wiki pages.
 *
 * @author Ben Coburn <btcoburn@silicodon.net>
 *
 * @param string $file      filename
 * @param string $content
 * @param string $id        page id
 * @param int|bool $rev timestamp of revision
 * @return bool
 */
function io_writeWikiPage($file, $content, $id, $rev=false) {
    if (empty($rev)) { $rev = false; }
    if ($rev===false) { io_createNamespace($id); } // create namespaces as needed
    $data = array(array($file, $content, false), getNS($id), noNS($id), $rev);
    return Event::createAndTrigger('IO_WIKIPAGE_WRITE', $data, '_io_writeWikiPage_action', false);
}

/**
 * Callback adapter for io_saveFile().
 * @author Ben Coburn <btcoburn@silicodon.net>
 *
 * @param array $data event data
 * @return bool
 */
function _io_writeWikiPage_action($data) {
    if (is_array($data) && is_array($data[0]) && count($data[0])===3) {
        $ok = call_user_func_array('io_saveFile', $data[0]);
        // for attic files make sure the file has the mtime of the revision
        if($ok && is_int($data[3]) && $data[3] > 0) {
            @touch($data[0][0], $data[3]);
        }
        return $ok;
    } else {
        return false; //callback error
    }
}

/**
 * Internal function to save contents to a file.
 *
 * @author  Andreas Gohr <andi@splitbrain.org>
 *
 * @param string $file filename path to file
 * @param string $content
 * @param bool   $append
 * @return bool true on success, otherwise false
 */
function _io_saveFile($file, $content, $append) {
    global $conf;
    $mode = ($append) ? 'ab' : 'wb';
    $fileexists = file_exists($file);

    if(substr($file,-3) == '.gz'){
        if(!DOKU_HAS_GZIP) return false;
        $fh = @gzopen($file,$mode.'9');
        if(!$fh) return false;
        gzwrite($fh, $content);
        gzclose($fh);
    }else if(substr($file,-4) == '.bz2'){
        if(!DOKU_HAS_BZIP) return false;
        if($append) {
            $bzcontent = bzfile($file);
            if($bzcontent === false) return false;
            $content = $bzcontent.$content;
        }
        $fh = @bzopen($file,'w');
        if(!$fh) return false;
        bzwrite($fh, $content);
        bzclose($fh);
    }else{
        $fh = @fopen($file,$mode);
        if(!$fh) return false;
        fwrite($fh, $content);
        fclose($fh);
    }

    if(!$fileexists and !empty($conf['fperm'])) chmod($file, $conf['fperm']);
    return true;
}

/**
 * Saves $content to $file.
 *
 * If the third parameter is set to true the given content
 * will be appended.
 *
 * Uses gzip if extension is .gz
 * and bz2 if extension is .bz2
 *
 * @author  Andreas Gohr <andi@splitbrain.org>
 *
 * @param string $file filename path to file
 * @param string $content
 * @param bool   $append
 * @return bool true on success, otherwise false
 */
function io_saveFile($file, $content, $append=false) {
    io_makeFileDir($file);
    io_lock($file);
    if(!_io_saveFile($file, $content, $append)) {
        msg("Writing $file failed",-1);
        io_unlock($file);
        return false;
    }
    io_unlock($file);
    return true;
}

/**
 * Replace one or more occurrences of a line in a file.
 *
 * The default, when $maxlines is 0 is to delete all matching lines then append a single line.
 * A regex that matches any part of the line will remove the entire line in this mode.
 * Captures in $newline are not available.
 *
 * Otherwise each line is matched and replaced individually, up to the first $maxlines lines
 * or all lines if $maxlines is -1. If $regex is true then captures can be used in $newline.
 *
 * Be sure to include the trailing newline in $oldline when replacing entire lines.
 *
 * Uses gzip if extension is .gz
 * and bz2 if extension is .bz2
 *
 * @author Steven Danz <steven-danz@kc.rr.com>
 * @author Christopher Smith <chris@jalakai.co.uk>
 * @author Patrick Brown <ptbrown@whoopdedo.org>
 *
 * @param string $file     filename
 * @param string $oldline  exact linematch to remove
 * @param string $newline  new line to insert
 * @param bool   $regex    use regexp?
 * @param int    $maxlines number of occurrences of the line to replace
 * @return bool true on success
 */
function io_replaceInFile($file, $oldline, $newline, $regex=false, $maxlines=0) {
    if ((string)$oldline === '') {
        trigger_error('$oldline parameter cannot be empty in io_replaceInFile()', E_USER_WARNING);
        return false;
    }

    if (!file_exists($file)) return true;

    io_lock($file);

    // load into array
    if(substr($file,-3) == '.gz'){
        if(!DOKU_HAS_GZIP) return false;
        $lines = gzfile($file);
    }else if(substr($file,-4) == '.bz2'){
        if(!DOKU_HAS_BZIP) return false;
        $lines = bzfile($file, true);
    }else{
        $lines = file($file);
    }

    // make non-regexes into regexes
    $pattern = $regex ? $oldline : '/^'.preg_quote($oldline,'/').'$/';
    $replace = $regex ? $newline : addcslashes($newline, '\$');

    // remove matching lines
    if ($maxlines > 0) {
        $count = 0;
        $matched = 0;
        foreach($lines as $i => $line) {
            if($count >= $maxlines) break;
            // $matched will be set to 0|1 depending on whether pattern is matched and line replaced
            $lines[$i] = preg_replace($pattern, $replace, $line, -1, $matched);
            if ($matched) $count++;
        }
    } else if ($maxlines == 0) {
        $lines = preg_grep($pattern, $lines, PREG_GREP_INVERT);

        if ((string)$newline !== ''){
            $lines[] = $newline;
        }
    } else {
        $lines = preg_replace($pattern, $replace, $lines);
    }

    if(count($lines)){
        if(!_io_saveFile($file, join('',$lines), false)) {
            msg("Removing content from $file failed",-1);
            io_unlock($file);
            return false;
        }
    }else{
        @unlink($file);
    }

    io_unlock($file);
    return true;
}

/**
 * Delete lines that match $badline from $file.
 *
 * Be sure to include the trailing newline in $badline
 *
 * @author Patrick Brown <ptbrown@whoopdedo.org>
 *
 * @param string $file    filename
 * @param string $badline exact linematch to remove
 * @param bool   $regex   use regexp?
 * @return bool true on success
 */
function io_deleteFromFile($file,$badline,$regex=false){
    return io_replaceInFile($file,$badline,null,$regex,0);
}

/**
 * Tries to lock a file
 *
 * Locking is only done for io_savefile and uses directories
 * inside $conf['lockdir']
 *
 * It waits maximal 3 seconds for the lock, after this time
 * the lock is assumed to be stale and the function goes on
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 *
 * @param string $file filename
 */
function io_lock($file){
    global $conf;

    $lockDir = $conf['lockdir'].'/'.md5($file);
    @ignore_user_abort(1);

    $timeStart = time();
    do {
        //waited longer than 3 seconds? -> stale lock
        if ((time() - $timeStart) > 3) break;
        $locked = @mkdir($lockDir, $conf['dmode']);
        if($locked){
            if($conf['dperm']) chmod($lockDir, $conf['dperm']);
            break;
        }
        usleep(50);
    } while ($locked === false);
}

/**
 * Unlocks a file
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 *
 * @param string $file filename
 */
function io_unlock($file){
    global $conf;

    $lockDir = $conf['lockdir'].'/'.md5($file);
    @rmdir($lockDir);
    @ignore_user_abort(0);
}

/**
 * Create missing namespace directories and send the IO_NAMESPACE_CREATED events
 * in the order of directory creation. (Parent directories first.)
 *
 * Event data:
 * $data[0]    ns: The colon separated namespace path minus the trailing page name.
 * $data[1]    ns_type: 'pages' or 'media' namespace tree.
 *
 * @author Ben Coburn <btcoburn@silicodon.net>
 *
 * @param string $id page id
 * @param string $ns_type 'pages' or 'media'
 */
function io_createNamespace($id, $ns_type='pages') {
    // verify ns_type
    $types = array('pages'=>'wikiFN', 'media'=>'mediaFN');
    if (!isset($types[$ns_type])) {
        trigger_error('Bad $ns_type parameter for io_createNamespace().');
        return;
    }
    // make event list
    $missing = array();
    $ns_stack = explode(':', $id);
    $ns = $id;
    $tmp = dirname( $file = call_user_func($types[$ns_type], $ns) );
    while (!@is_dir($tmp) && !(file_exists($tmp) && !is_dir($tmp))) {
        array_pop($ns_stack);
        $ns = implode(':', $ns_stack);
        if (strlen($ns)==0) { break; }
        $missing[] = $ns;
        $tmp = dirname(call_user_func($types[$ns_type], $ns));
    }
    // make directories
    io_makeFileDir($file);
    // send the events
    $missing = array_reverse($missing); // inside out
    foreach ($missing as $ns) {
        $data = array($ns, $ns_type);
        Event::createAndTrigger('IO_NAMESPACE_CREATED', $data);
    }
}

/**
 * Create the directory needed for the given file
 *
 * @author  Andreas Gohr <andi@splitbrain.org>
 *
 * @param string $file file name
 */
function io_makeFileDir($file){
    $dir = dirname($file);
    if(!@is_dir($dir)){
        io_mkdir_p($dir) || msg("Creating directory $dir failed",-1);
    }
}

/**
 * Creates a directory hierachy.
 *
 * @link    http://php.net/manual/en/function.mkdir.php
 * @author  <saint@corenova.com>
 * @author  Andreas Gohr <andi@splitbrain.org>
 *
 * @param string $target filename
 * @return bool|int|string
 */
function io_mkdir_p($target){
    global $conf;
    if (@is_dir($target)||empty($target)) return 1; // best case check first
    if (file_exists($target) && !is_dir($target)) return 0;
    //recursion
    if (io_mkdir_p(substr($target,0,strrpos($target,'/')))){
        $ret = @mkdir($target,$conf['dmode']); // crawl back up & create dir tree
        if($ret && !empty($conf['dperm'])) chmod($target, $conf['dperm']);
        return $ret;
    }
    return 0;
}

/**
 * Recursively delete a directory
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 * @param string $path
 * @param bool   $removefiles defaults to false which will delete empty directories only
 * @return bool
 */
function io_rmdir($path, $removefiles = false) {
    if(!is_string($path) || $path == "") return false;
    if(!file_exists($path)) return true; // it's already gone or was never there, count as success

    if(is_dir($path) && !is_link($path)) {
        $dirs  = array();
        $files = array();

        if(!$dh = @opendir($path)) return false;
        while(false !== ($f = readdir($dh))) {
            if($f == '..' || $f == '.') continue;

            // collect dirs and files first
            if(is_dir("$path/$f") && !is_link("$path/$f")) {
                $dirs[] = "$path/$f";
            } else if($removefiles) {
                $files[] = "$path/$f";
            } else {
                return false; // abort when non empty
            }

        }
        closedir($dh);

        // now traverse into  directories first
        foreach($dirs as $dir) {
            if(!io_rmdir($dir, $removefiles)) return false; // abort on any error
        }

        // now delete files
        foreach($files as $file) {
            if(!@unlink($file)) return false; //abort on any error
        }

        // remove self
        return @rmdir($path);
    } else if($removefiles) {
        return @unlink($path);
    }
    return false;
}

/**
 * Creates a unique temporary directory and returns
 * its path.
 *
 * @author Michael Klier <chi@chimeric.de>
 *
 * @return false|string path to new directory or false
 */
function io_mktmpdir() {
    global $conf;

    $base = $conf['tmpdir'];
    $dir  = md5(uniqid(mt_rand(), true));
    $tmpdir = $base.'/'.$dir;

    if(io_mkdir_p($tmpdir)) {
        return($tmpdir);
    } else {
        return false;
    }
}

/**
 * downloads a file from the net and saves it
 *
 * if $useAttachment is false,
 * - $file is the full filename to save the file, incl. path
 * - if successful will return true, false otherwise
 *
 * if $useAttachment is true,
 * - $file is the directory where the file should be saved
 * - if successful will return the name used for the saved file, false otherwise
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 * @author Chris Smith <chris@jalakai.co.uk>
 *
 * @param string $url           url to download
 * @param string $file          path to file or directory where to save
 * @param bool   $useAttachment true: try to use name of download, uses otherwise $defaultName
 *                              false: uses $file as path to file
 * @param string $defaultName   fallback for if using $useAttachment
 * @param int    $maxSize       maximum file size
 * @return bool|string          if failed false, otherwise true or the name of the file in the given dir
 */
function io_download($url,$file,$useAttachment=false,$defaultName='',$maxSize=2097152){
    global $conf;
    $http = new DokuHTTPClient();
    $http->max_bodysize = $maxSize;
    $http->timeout = 25; //max. 25 sec
    $http->keep_alive = false; // we do single ops here, no need for keep-alive

    $data = $http->get($url);
    if(!$data) return false;

    $name = '';
    if ($useAttachment) {
        if (isset($http->resp_headers['content-disposition'])) {
            $content_disposition = $http->resp_headers['content-disposition'];
            $match=array();
            if (is_string($content_disposition) &&
                    preg_match('/attachment;\s*filename\s*=\s*"([^"]*)"/i', $content_disposition, $match)) {

                $name = \dokuwiki\Utf8\PhpString::basename($match[1]);
            }

        }

        if (!$name) {
            if (!$defaultName) return false;
            $name = $defaultName;
        }

        $file = $file.$name;
    }

    $fileexists = file_exists($file);
    $fp = @fopen($file,"w");
    if(!$fp) return false;
    fwrite($fp,$data);
    fclose($fp);
    if(!$fileexists and $conf['fperm']) chmod($file, $conf['fperm']);
    if ($useAttachment) return $name;
    return true;
}

/**
 * Windows compatible rename
 *
 * rename() can not overwrite existing files on Windows
 * this function will use copy/unlink instead
 *
 * @param string $from
 * @param string $to
 * @return bool succes or fail
 */
function io_rename($from,$to){
    global $conf;
    if(!@rename($from,$to)){
        if(@copy($from,$to)){
            if($conf['fperm']) chmod($to, $conf['fperm']);
            @unlink($from);
            return true;
        }
        return false;
    }
    return true;
}

/**
 * Runs an external command with input and output pipes.
 * Returns the exit code from the process.
 *
 * @author Tom N Harris <tnharris@whoopdedo.org>
 *
 * @param string $cmd
 * @param string $input  input pipe
 * @param string $output output pipe
 * @return int exit code from process
 */
function io_exec($cmd, $input, &$output){
    $descspec = array(
            0=>array("pipe","r"),
            1=>array("pipe","w"),
            2=>array("pipe","w"));
    $ph = proc_open($cmd, $descspec, $pipes);
    if(!$ph) return -1;
    fclose($pipes[2]); // ignore stderr
    fwrite($pipes[0], $input);
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    return proc_close($ph);
}

/**
 * Search a file for matching lines
 *
 * This is probably not faster than file()+preg_grep() but less
 * memory intensive because not the whole file needs to be loaded
 * at once.
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 * @param  string $file    The file to search
 * @param  string $pattern PCRE pattern
 * @param  int    $max     How many lines to return (0 for all)
 * @param  bool   $backref When true returns array with backreferences instead of lines
 * @return array matching lines or backref, false on error
 */
function io_grep($file,$pattern,$max=0,$backref=false){
    $fh = @fopen($file,'r');
    if(!$fh) return false;
    $matches = array();

    $cnt  = 0;
    $line = '';
    while (!feof($fh)) {
        $line .= fgets($fh, 4096);  // read full line
        if(substr($line,-1) != "\n") continue;

        // check if line matches
        if(preg_match($pattern,$line,$match)){
            if($backref){
                $matches[] = $match;
            }else{
                $matches[] = $line;
            }
            $cnt++;
        }
        if($max && $max == $cnt) break;
        $line = '';
    }
    fclose($fh);
    return $matches;
}


/**
 * Get size of contents of a file, for a compressed file the uncompressed size
 * Warning: reading uncompressed size of content of bz-files requires uncompressing
 *
 * @author  Gerrit Uitslag <klapinklapin@gmail.com>
 *
 * @param string $file filename path to file
 * @return int size of file
 */
function io_getSizeFile($file) {
    if (!file_exists($file)) return 0;

    if(substr($file,-3) == '.gz'){
        $fp = @fopen($file, "rb");
        if($fp === false) return 0;

        fseek($fp, -4, SEEK_END);
        $buffer = fread($fp, 4);
        fclose($fp);
        $array = unpack("V", $buffer);
        $uncompressedsize = end($array);
    }else if(substr($file,-4) == '.bz2'){
        if(!DOKU_HAS_BZIP) return 0;

        $bz = bzopen($file,"r");
        if($bz === false) return 0;

        $uncompressedsize = 0;
        while (!feof($bz)) {
            //8192 seems to be the maximum buffersize?
            $buffer = bzread($bz,8192);
            if(($buffer === false) || (bzerrno($bz) !== 0)) {
                return 0;
            }
            $uncompressedsize += strlen($buffer);
        }
    }else{
        $uncompressedsize = filesize($file);
    }

    return $uncompressedsize;
 }
