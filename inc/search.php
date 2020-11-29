<?php
/**
 * DokuWiki search functions
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 */

use dokuwiki\Utf8\Sort;

/**
 * Recurse directory
 *
 * This function recurses into a given base directory
 * and calls the supplied function for each file and directory
 *
 * @param   array    &$data The results of the search are stored here
 * @param   string    $base Where to start the search
 * @param   callback  $func Callback (function name or array with object,method)
 * @param   array     $opts option array will be given to the Callback
 * @param   string    $dir  Current directory beyond $base
 * @param   int       $lvl  Recursion Level
 * @param   mixed     $sort 'natural' to use natural order sorting (default);
 *                          'date' to sort by filemtime; leave empty to skip sorting.
 * @author  Andreas Gohr <andi@splitbrain.org>
 */
function search(&$data, $base, $func, $opts, $dir='', $lvl=1, $sort='natural')
{
    $dirs   = array();
    $files  = array();
    $filepaths = array();

    // safeguard against runaways #1452
    if ($base == '' || $base == '/') {
        throw new RuntimeException('No valid $base passed to search() - possible misconfiguration or bug');
    }

    //read in directories and files
    $dh = @opendir($base.'/'.$dir);
    if (!$dh) return;
    while (($file = readdir($dh)) !== false) {
        if (preg_match('/^[\._]/', $file)) continue; //skip hidden files and upper dirs
        if (is_dir($base.'/'.$dir.'/'.$file)) {
            $dirs[] = $dir.'/'.$file;
            continue;
        }
        $files[] = $dir.'/'.$file;
        $filepaths[] = $base.'/'.$dir.'/'.$file;
    }
    closedir($dh);
    if (!empty($sort)) {
        if ($sort == 'date') {
            @array_multisort(array_map('filemtime', $filepaths), SORT_NUMERIC, SORT_DESC, $files);
        } else /* natural */ {
            Sort::asortFN($files);
        }
        Sort::asortFN($dirs);
    }

    //give directories to userfunction then recurse
    foreach ($dirs as $dir) {
        if (call_user_func_array($func, array(&$data, $base, $dir, 'd', $lvl,$opts))) {
            search($data, $base, $func, $opts, $dir, $lvl+1, $sort);
        }
    }
    //now handle the files
    foreach ($files as $file) {
        call_user_func_array($func, array(&$data, $base, $file, 'f', $lvl, $opts));
    }
}

/**
 * The following functions are userfunctions to use with the search
 * function above. This function is called for every found file or
 * directory. When a directory is given to the function it has to
 * decide if this directory should be traversed (true) or not (false)
 * The function has to accept the following parameters:
 *
 * array &$data  - Reference to the result data structure
 * string $base  - Base usually $conf['datadir']
 * string $file  - current file or directory relative to $base
 * string $type  - Type either 'd' for directory or 'f' for file
 * int    $lvl   - Current recursion depht
 * array  $opts  - option array as given to search()
 *
 * return values for files are ignored
 *
 * All functions should check the ACL for document READ rights
 * namespaces (directories) are NOT checked (when sneaky_index is 0) as this
 * would break the recursion (You can have an nonreadable dir over a readable
 * one deeper nested) also make sure to check the file type (for example
 * in case of lockfiles).
 */

/**
 * Searches for pages beginning with the given query
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 *
 * @param array $data
 * @param string $base
 * @param string $file
 * @param string $type
 * @param integer $lvl
 * @param array $opts
 *
 * @return bool
 */
function search_qsearch(&$data, $base, $file, $type, $lvl, $opts)
{
    $opts = array(
            'idmatch'   => '(^|:)'.preg_quote($opts['query'],'/').'/',
            'listfiles' => true,
            'pagesonly' => true,
            );
    return search_universal($data, $base, $file, $type, $lvl, $opts);
}

/**
 * Build the browsable index of pages
 *
 * $opts['ns'] is the currently viewed namespace
 *
 * @author  Andreas Gohr <andi@splitbrain.org>
 *
 * @param array $data
 * @param string $base
 * @param string $file
 * @param string $type
 * @param integer $lvl
 * @param array $opts
 *
 * @return bool
 */
function search_index(&$data, $base, $file, $type, $lvl, $opts)
{
    global $conf;
    $opts = array(
        'pagesonly' => true,
        'listdirs' => true,
        'listfiles' => empty($opts['nofiles']),
        'sneakyacl' => $conf['sneaky_index'],
        // Hacky, should rather use recmatch
        'depth' => preg_match('#^'.preg_quote($file, '#').'(/|$)#','/'.$opts['ns']) ? 0 : -1
    );

    return search_universal($data, $base, $file, $type, $lvl, $opts);
}

/**
 * List all namespaces
 *
 * @author  Andreas Gohr <andi@splitbrain.org>
 *
 * @param array $data
 * @param string $base
 * @param string $file
 * @param string $type
 * @param integer $lvl
 * @param array $opts
 *
 * @return bool
 */
function search_namespaces(&$data, $base, $file, $type, $lvl, $opts)
{
    $opts = array(
            'listdirs' => true,
            );
    return search_universal($data, $base, $file, $type, $lvl, $opts);
}

/**
 * List all mediafiles in a namespace
 *   $opts['depth']     recursion level, 0 for all
 *   $opts['showmsg']   shows message if invalid media id is used
 *   $opts['skipacl']   skip acl checking
 *   $opts['pattern']   check given pattern
 *   $opts['hash']      add hashes to result list
 *
 * @author  Andreas Gohr <andi@splitbrain.org>
 *
 * @param array $data
 * @param string $base
 * @param string $file
 * @param string $type
 * @param integer $lvl
 * @param array $opts
 *
 * @return bool
 */
function search_media(&$data, $base, $file, $type, $lvl, $opts)
{
    //we do nothing with directories
    if ($type == 'd') {
        if (empty($opts['depth'])) return true; // recurse forever
        $depth = substr_count($file,'/');
        if ($depth >= $opts['depth']) return false; // depth reached
        return true;
    }

    $info         = array();
    $info['id']   = pathID($file, true);
    if ($info['id'] != cleanID($info['id'])) {
        if ($opts['showmsg'])
            msg(hsc($info['id']).' is not a valid file name for DokuWiki - skipped',-1);
        return false; // skip non-valid files
    }

    //check ACL for namespace (we have no ACL for mediafiles)
    $info['perm'] = auth_quickaclcheck(getNS($info['id']).':*');
    if (empty($opts['skipacl']) && $info['perm'] < AUTH_READ) {
        return false;
    }

    //check pattern filter
    if (!empty($opts['pattern']) && !@preg_match($opts['pattern'], $info['id'])) {
        return false;
    }

    $info['file']     = \dokuwiki\Utf8\PhpString::basename($file);
    $info['size']     = filesize($base.'/'.$file);
    $info['mtime']    = filemtime($base.'/'.$file);
    $info['writable'] = is_writable($base.'/'.$file);
    if (preg_match("/\.(jpe?g|gif|png)$/", $file)) {
        $info['isimg'] = true;
        $info['meta']  = new JpegMeta($base.'/'.$file);
    } else {
        $info['isimg'] = false;
    }
    if (!empty($opts['hash'])) {
        $info['hash'] = md5(io_readFile(mediaFN($info['id']), false));
    }

    $data[] = $info;

    return false;
}

/**
 * This function just lists documents (for RSS namespace export)
 *
 * @author  Andreas Gohr <andi@splitbrain.org>
 *
 * @param array $data
 * @param string $base
 * @param string $file
 * @param string $type
 * @param integer $lvl
 * @param array $opts
 *
 * @return bool
 */
function search_list(&$data, $base, $file, $type, $lvl, $opts)
{
    //we do nothing with directories
    if ($type == 'd') return false;
    //only search txt files
    if (substr($file, -4) == '.txt') {
        //check ACL
        $id = pathID($file);
        if (auth_quickaclcheck($id) < AUTH_READ) {
            return false;
        }
        $data[]['id'] = $id;
    }
    return false;
}

/**
 * Quicksearch for searching matching pagenames
 *
 * $opts['query'] is the search query
 *
 * @author  Andreas Gohr <andi@splitbrain.org>
 *
 * @param array $data
 * @param string $base
 * @param string $file
 * @param string $type
 * @param integer $lvl
 * @param array $opts
 *
 * @return bool
 */
function search_pagename(&$data, $base, $file, $type, $lvl, $opts)
{
    //we do nothing with directories
    if ($type == 'd') return true;
    //only search txt files
    if (substr($file, -4) != '.txt') return true;

    //simple stringmatching
    if (!empty($opts['query'])) {
        if (strpos($file, $opts['query']) !== false) {
            //check ACL
            $id = pathID($file);
            if (auth_quickaclcheck($id) < AUTH_READ) {
                return false;
            }
            $data[]['id'] = $id;
        }
    }
    return true;
}

/**
 * Just lists all documents
 *
 * $opts['depth']   recursion level, 0 for all
 * $opts['hash']    do md5 sum of content?
 * $opts['skipacl'] list everything regardless of ACL
 *
 * @author  Andreas Gohr <andi@splitbrain.org>
 *
 * @param array $data
 * @param string $base
 * @param string $file
 * @param string $type
 * @param integer $lvl
 * @param array $opts
 *
 * @return bool
 */
function search_allpages(&$data, $base, $file, $type, $lvl, $opts)
{
    if (isset($opts['depth']) && $opts['depth']) {
        $parts = explode('/',ltrim($file, '/'));
        if (($type == 'd' && count($parts) >= $opts['depth'])
            || ($type != 'd' && count($parts) > $opts['depth'])
        ){
            return false; // depth reached
        }
    }

    //we do nothing with directories
    if ($type == 'd') {
        return true;
    }

    //only search txt files
    if (substr($file, -4) != '.txt') return true;

    $item = array();
    $item['id']   = pathID($file);
    if (empty($opts['skipacl']) && auth_quickaclcheck($item['id']) < AUTH_READ) {
        return false;
    }

    $item['rev']   = filemtime($base.'/'.$file);
    $item['mtime'] = $item['rev'];
    $item['size']  = filesize($base.'/'.$file);
    if (!empty($opts['hash'])) {
        $item['hash'] = md5(trim(rawWiki($item['id'])));
    }

    $data[] = $item;
    return true;
}

/* ------------- helper functions below -------------- */

/**
 * fulltext sort
 *
 * Callback sort function for use with usort to sort the data
 * structure created by search_fulltext. Sorts descending by count
 *
 * @author  Andreas Gohr <andi@splitbrain.org>
 *
 * @param array $a
 * @param array $b
 *
 * @return int
 */
function sort_search_fulltext($a, $b)
{
    if ($a['count'] > $b['count']) {
        return -1;
    } elseif ($a['count'] < $b['count']) {
        return 1;
    }else{
        return Sort::strcmp($a['id'],$b['id']);
    }
}

/**
 * translates a document path to an ID
 *
 * @author  Andreas Gohr <andi@splitbrain.org>
 * @todo    move to pageutils
 *
 * @param string $path
 * @param bool $keeptxt
 *
 * @return mixed|string
 */
function pathID($path, $keeptxt=false)
{
    $id = utf8_decodeFN($path);
    $id = str_replace('/', ':', $id);
    if (!$keeptxt) $id = preg_replace('#\.txt$#', '', $id);
    $id = trim($id, ':');
    return $id;
}


/**
 * This is a very universal callback for the search() function, replacing
 * many of the former individual functions at the cost of a more complex
 * setup.
 *
 * How the function behaves, depends on the options passed in the $opts
 * array, where the following settings can be used.
 *
 * depth      int     recursion depth. 0 for unlimited                       (default: 0)
 * keeptxt    bool    keep .txt extension for IDs                            (default: false)
 * listfiles  bool    include files in listing                               (default: false)
 * listdirs   bool    include namespaces in listing                          (default: false)
 * pagesonly  bool    restrict files to pages                                (default: false)
 * skipacl    bool    do not check for READ permission                       (default: false)
 * sneakyacl  bool    don't recurse into nonreadable dirs                    (default: false)
 * hash       bool    create MD5 hash for files                              (default: false)
 * meta       bool    return file metadata                                   (default: false)
 * filematch  string  match files against this regexp                        (default: '', so accept everything)
 * idmatch    string  match full ID against this regexp                      (default: '', so accept everything)
 * dirmatch   string  match directory against this regexp when adding        (default: '', so accept everything)
 * nsmatch    string  match namespace against this regexp when adding        (default: '', so accept everything)
 * recmatch   string  match directory against this regexp when recursing     (default: '', so accept everything)
 * showmsg    bool    warn about non-ID files                                (default: false)
 * showhidden bool    show hidden files(e.g. by hidepages config) too        (default: false)
 * firsthead  bool    return first heading for pages                         (default: false)
 *
 * @param array &$data  - Reference to the result data structure
 * @param string $base  - Base usually $conf['datadir']
 * @param string $file  - current file or directory relative to $base
 * @param string $type  - Type either 'd' for directory or 'f' for file
 * @param int    $lvl   - Current recursion depht
 * @param array  $opts  - option array as given to search()
 * @return bool if this directory should be traversed (true) or not (false)
 *              return value is ignored for files
 *
 * @author Andreas Gohr <gohr@cosmocode.de>
 */
function search_universal(&$data, $base, $file, $type, $lvl, $opts)
{
    $item   = array();
    $return = true;

    // get ID and check if it is a valid one
    $item['id'] = pathID($file, ($type == 'd' || !empty($opts['keeptxt'])));
    if ($item['id'] != cleanID($item['id'])){
        if (!empty($opts['showmsg'])) {
            msg(hsc($item['id']).' is not a valid file name for DokuWiki - skipped',-1);
        }
        return false; // skip non-valid files
    }
    $item['ns']  = getNS($item['id']);

    if ($type == 'd') {
        // decide if to recursion into this directory is wanted
        if (empty($opts['depth'])) {
            $return = true; // recurse forever
        } else {
            $depth = substr_count($file,'/');
            if ($depth >= $opts['depth']) {
                $return = false; // depth reached
            } else {
                $return = true;
            }
        }

        if ($return) {
            $match = empty($opts['recmatch']) || preg_match('/'.$opts['recmatch'].'/', $file);
            if (!$match) {
                return false; // doesn't match
            }
        }
    }

    // check ACL
    if (empty($opts['skipacl'])) {
        if ($type == 'd') {
            $item['perm'] = auth_quickaclcheck($item['id'].':*');
        } else {
            $item['perm'] = auth_quickaclcheck($item['id']); //FIXME check namespace for media files
        }
    } else {
        $item['perm'] = AUTH_DELETE;
    }

    // are we done here maybe?
    if ($type == 'd') {
        if (empty($opts['listdirs'])) return $return;
        //neither list nor recurse forbidden items:
        if (empty($opts['skipacl']) && !empty($opts['sneakyacl']) && $item['perm'] < AUTH_READ) return false;
        if (!empty($opts['dirmatch']) && !preg_match('/'.$opts['dirmatch'].'/', $file)) return $return;
        if (!empty($opts['nsmatch']) && !preg_match('/'.$opts['nsmatch'].'/', $item['ns'])) return $return;
    } else {
        if (empty($opts['listfiles'])) return $return;
        if (empty($opts['skipacl']) && $item['perm'] < AUTH_READ) return $return;
        if (!empty($opts['pagesonly']) && (substr($file, -4) != '.txt')) return $return;
        if (empty($opts['showhidden']) && isHiddenPage($item['id'])) return $return;
        if (!empty($opts['filematch']) && !preg_match('/'.$opts['filematch'].'/', $file)) return $return;
        if (!empty($opts['idmatch']) && !preg_match('/'.$opts['idmatch'].'/', $item['id'])) return $return;
    }

    // still here? prepare the item
    $item['type']  = $type;
    $item['level'] = $lvl;
    $item['open']  = $return;

    if (!empty($opts['meta'])) {
        $item['file']       = \dokuwiki\Utf8\PhpString::basename($file);
        $item['size']       = filesize($base.'/'.$file);
        $item['mtime']      = filemtime($base.'/'.$file);
        $item['rev']        = $item['mtime'];
        $item['writable']   = is_writable($base.'/'.$file);
        $item['executable'] = is_executable($base.'/'.$file);
    }

    if ($type == 'f') {
        if (!empty($opts['hash'])) $item['hash'] = md5(io_readFile($base.'/'.$file, false));
        if (!empty($opts['firsthead'])) {
            $item['title'] = p_get_first_heading($item['id'], METADATA_DONT_RENDER);
        }
    }

    // finally add the item
    $data[] = $item;
    return $return;
}

//Setup VIM: ex: et ts=4 :
