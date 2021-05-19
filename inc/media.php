<?php
/**
 * All output and handler function needed for the media management popup
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 */

use dokuwiki\ChangeLog\MediaChangeLog;
use dokuwiki\HTTP\DokuHTTPClient;
use dokuwiki\Subscriptions\MediaSubscriptionSender;
use dokuwiki\Extension\Event;
use dokuwiki\Form\Form;
use dokuwiki\Utf8\Sort;

/**
 * Lists pages which currently use a media file selected for deletion
 *
 * References uses the same visual as search results and share
 * their CSS tags except pagenames won't be links.
 *
 * @author Matthias Grimm <matthiasgrimm@users.sourceforge.net>
 *
 * @param array $data
 * @param string $id
 */
function media_filesinuse($data,$id){
    global $lang;
    echo '<h1>'.$lang['reference'].' <code>'.hsc(noNS($id)).'</code></h1>';
    echo '<p>'.hsc($lang['ref_inuse']).'</p>';

    $hidden=0; //count of hits without read permission
    foreach($data as $row){
        if(auth_quickaclcheck($row) >= AUTH_READ && isVisiblePage($row)){
            echo '<div class="search_result">';
            echo '<span class="mediaref_ref">'.hsc($row).'</span>';
            echo '</div>';
        }else
            $hidden++;
    }
    if ($hidden){
        print '<div class="mediaref_hidden">'.$lang['ref_hidden'].'</div>';
    }
}

/**
 * Handles the saving of image meta data
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 * @author Kate Arzamastseva <pshns@ukr.net>
 *
 * @param string $id media id
 * @param int $auth permission level
 * @param array $data
 * @return false|string
 */
function media_metasave($id,$auth,$data){
    if($auth < AUTH_UPLOAD) return false;
    if(!checkSecurityToken()) return false;
    global $lang;
    global $conf;
    $src = mediaFN($id);

    $meta = new JpegMeta($src);
    $meta->_parseAll();

    foreach($data as $key => $val){
        $val=trim($val);
        if(empty($val)){
            $meta->deleteField($key);
        }else{
            $meta->setField($key,$val);
        }
    }

    $old = @filemtime($src);
    if(!file_exists(mediaFN($id, $old)) && file_exists($src)) {
        // add old revision to the attic
        media_saveOldRevision($id);
    }
    $filesize_old = filesize($src);
    if($meta->save()){
        if($conf['fperm']) chmod($src, $conf['fperm']);
        @clearstatcache(true, $src);
        $new = @filemtime($src);
        $filesize_new = filesize($src);
        $sizechange = $filesize_new - $filesize_old;

        // add a log entry to the media changelog
        addMediaLogEntry($new, $id, DOKU_CHANGE_TYPE_EDIT, $lang['media_meta_edited'], '', null, $sizechange);

        msg($lang['metasaveok'],1);
        return $id;
    }else{
        msg($lang['metasaveerr'],-1);
        return false;
    }
}

/**
 * check if a media is external source
 *
 * @author Gerrit Uitslag <klapinklapin@gmail.com>
 *
 * @param string $id the media ID or URL
 * @return bool
 */
function media_isexternal($id){
    if (preg_match('#^(?:https?|ftp)://#i', $id)) return true;
    return false;
}

/**
 * Check if a media item is public (eg, external URL or readable by @ALL)
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 *
 * @param string $id  the media ID or URL
 * @return bool
 */
function media_ispublic($id){
    if(media_isexternal($id)) return true;
    $id = cleanID($id);
    if(auth_aclcheck(getNS($id).':*', '', array()) >= AUTH_READ) return true;
    return false;
}

/**
 * Display the form to edit image meta data
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 * @author Kate Arzamastseva <pshns@ukr.net>
 *
 * @param string $id media id
 * @param int $auth permission level
 * @return bool
 */
function media_metaform($id, $auth) {
    global $lang;

    if ($auth < AUTH_UPLOAD) {
        echo '<div class="nothing">'.$lang['media_perm_upload'].'</div>'.DOKU_LF;
        return false;
    }

    // load the field descriptions
    static $fields = null;
    if ($fields === null) {
        $config_files = getConfigFiles('mediameta');
        foreach ($config_files as $config_file) {
            if (file_exists($config_file)) include($config_file);
        }
    }

    $src = mediaFN($id);

    // output
    $form = new Form([
            'action' => media_managerURL(['tab_details' => 'view'], '&'),
            'class' => 'meta'
    ]);
    $form->addTagOpen('div')->addClass('no');
    $form->setHiddenField('img', $id);
    $form->setHiddenField('mediado', 'save');
    foreach ($fields as $key => $field) {
        // get current value
        if (empty($field[0])) continue;
        $tags = array($field[0]);
        if (is_array($field[3])) $tags = array_merge($tags, $field[3]);
        $value = tpl_img_getTag($tags, '', $src);
        $value = cleanText($value);

        // prepare attributes
        $p = array(
            'class' => 'edit',
            'id'    => 'meta__'.$key,
            'name'  => 'meta['.$field[0].']',
        );

        $form->addTagOpen('div')->addClass('row');
        if ($field[2] == 'text') {
            $form->addTextInput(
                $p['name'],
                ($lang[$field[1]] ? $lang[$field[1]] : $field[1] . ':')
            )->id($p['id'])->addClass($p['class'])->val($value);
        } else {
            $form->addTextarea($p['name'], $lang[$field[1]])->id($p['id'])
                ->val(formText($value))
                ->addClass($p['class'])
                ->attr('rows', '6')->attr('cols', '50');
        }
        $form->addTagClose('div');
    }
    $form->addTagOpen('div')->addClass('buttons');
    $form->addButton('mediado[save]', $lang['btn_save'])->attr('type', 'submit')
        ->attrs(['accesskey' => 's']);
    $form->addTagClose('div');

    $form->addTagClose('div');
    echo $form->toHTML();
    return true;
}

/**
 * Convenience function to check if a media file is still in use
 *
 * @author Michael Klier <chi@chimeric.de>
 *
 * @param string $id media id
 * @return array|bool
 */
function media_inuse($id) {
    global $conf;

    if($conf['refcheck']){
        $mediareferences = ft_mediause($id,true);
        if(!count($mediareferences)) {
            return false;
        } else {
            return $mediareferences;
        }
    } else {
        return false;
    }
}

/**
 * Handles media file deletions
 *
 * If configured, checks for media references before deletion
 *
 * @author             Andreas Gohr <andi@splitbrain.org>
 *
 * @param string $id media id
 * @param int $auth no longer used
 * @return int One of: 0,
 *                     DOKU_MEDIA_DELETED,
 *                     DOKU_MEDIA_DELETED | DOKU_MEDIA_EMPTY_NS,
 *                     DOKU_MEDIA_NOT_AUTH,
 *                     DOKU_MEDIA_INUSE
 */
function media_delete($id,$auth){
    global $lang;
    $auth = auth_quickaclcheck(ltrim(getNS($id).':*', ':'));
    if($auth < AUTH_DELETE) return DOKU_MEDIA_NOT_AUTH;
    if(media_inuse($id)) return DOKU_MEDIA_INUSE;

    $file = mediaFN($id);

    // trigger an event - MEDIA_DELETE_FILE
    $data = array();
    $data['id']   = $id;
    $data['name'] = \dokuwiki\Utf8\PhpString::basename($file);
    $data['path'] = $file;
    $data['size'] = (file_exists($file)) ? filesize($file) : 0;

    $data['unl'] = false;
    $data['del'] = false;
    $evt = new Event('MEDIA_DELETE_FILE',$data);
    if ($evt->advise_before()) {
        $old = @filemtime($file);
        if(!file_exists(mediaFN($id, $old)) && file_exists($file)) {
            // add old revision to the attic
            media_saveOldRevision($id);
        }

        $data['unl'] = @unlink($file);
        if($data['unl']) {
            $sizechange = 0 - $data['size'];
            addMediaLogEntry(time(), $id, DOKU_CHANGE_TYPE_DELETE, $lang['deleted'], '', null, $sizechange);

            $data['del'] = io_sweepNS($id, 'mediadir');
        }
    }
    $evt->advise_after();
    unset($evt);

    if($data['unl'] && $data['del']){
        return DOKU_MEDIA_DELETED | DOKU_MEDIA_EMPTY_NS;
    }

    return $data['unl'] ? DOKU_MEDIA_DELETED : 0;
}

/**
 * Handle file uploads via XMLHttpRequest
 *
 * @param string $ns   target namespace
 * @param int    $auth current auth check result
 * @return false|string false on error, id of the new file on success
 */
function media_upload_xhr($ns,$auth){
    if(!checkSecurityToken()) return false;
    global $INPUT;

    $id = $INPUT->get->str('qqfile');
    list($ext,$mime) = mimetype($id);
    $input = fopen("php://input", "r");
    if (!($tmp = io_mktmpdir())) return false;
    $path = $tmp.'/'.md5($id);
    $target = fopen($path, "w");
    $realSize = stream_copy_to_stream($input, $target);
    fclose($target);
    fclose($input);
    if (isset($_SERVER["CONTENT_LENGTH"]) && ($realSize != (int)$_SERVER["CONTENT_LENGTH"])){
        unlink($path);
        return false;
    }

    $res = media_save(
        array('name' => $path,
            'mime' => $mime,
            'ext'  => $ext),
        $ns.':'.$id,
        (($INPUT->get->str('ow') == 'true') ? true : false),
        $auth,
        'copy'
    );
    unlink($path);
    if ($tmp) io_rmdir($tmp, true);
    if (is_array($res)) {
        msg($res[0], $res[1]);
        return false;
    }
    return $res;
}

/**
 * Handles media file uploads
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 * @author Michael Klier <chi@chimeric.de>
 *
 * @param string     $ns    target namespace
 * @param int        $auth  current auth check result
 * @param bool|array $file  $_FILES member, $_FILES['upload'] if false
 * @return false|string false on error, id of the new file on success
 */
function media_upload($ns,$auth,$file=false){
    if(!checkSecurityToken()) return false;
    global $lang;
    global $INPUT;

    // get file and id
    $id   = $INPUT->post->str('mediaid');
    if (!$file) $file = $_FILES['upload'];
    if(empty($id)) $id = $file['name'];

    // check for errors (messages are done in lib/exe/mediamanager.php)
    if($file['error']) return false;

    // check extensions
    list($fext,$fmime) = mimetype($file['name']);
    list($iext,$imime) = mimetype($id);
    if($fext && !$iext){
        // no extension specified in id - read original one
        $id   .= '.'.$fext;
        $imime = $fmime;
    }elseif($fext && $fext != $iext){
        // extension was changed, print warning
        msg(sprintf($lang['mediaextchange'],$fext,$iext));
    }

    $res = media_save(array('name' => $file['tmp_name'],
                            'mime' => $imime,
                            'ext'  => $iext), $ns.':'.$id,
                      $INPUT->post->bool('ow'), $auth, 'copy_uploaded_file');
    if (is_array($res)) {
        msg($res[0], $res[1]);
        return false;
    }
    return $res;
}

/**
 * An alternative to move_uploaded_file that copies
 *
 * Using copy, makes sure any setgid bits on the media directory are honored
 *
 * @see   move_uploaded_file()
 *
 * @param string $from
 * @param string $to
 * @return bool
 */
function copy_uploaded_file($from, $to){
    if(!is_uploaded_file($from)) return false;
    $ok = copy($from, $to);
    @unlink($from);
    return $ok;
}

/**
 * This generates an action event and delegates to _media_upload_action().
 * Action plugins are allowed to pre/postprocess the uploaded file.
 * (The triggered event is preventable.)
 *
 * Event data:
 * $data[0]     fn_tmp:    the temporary file name (read from $_FILES)
 * $data[1]     fn:        the file name of the uploaded file
 * $data[2]     id:        the future directory id of the uploaded file
 * $data[3]     imime:     the mimetype of the uploaded file
 * $data[4]     overwrite: if an existing file is going to be overwritten
 * $data[5]     move:      name of function that performs move/copy/..
 *
 * @triggers MEDIA_UPLOAD_FINISH
 *
 * @param array  $file
 * @param string $id   media id
 * @param bool   $ow   overwrite?
 * @param int    $auth permission level
 * @param string $move name of functions that performs move/copy/..
 * @return false|array|string
 */
function media_save($file, $id, $ow, $auth, $move) {
    if($auth < AUTH_UPLOAD) {
        return array("You don't have permissions to upload files.", -1);
    }

    if (!isset($file['mime']) || !isset($file['ext'])) {
        list($ext, $mime) = mimetype($id);
        if (!isset($file['mime'])) {
            $file['mime'] = $mime;
        }
        if (!isset($file['ext'])) {
            $file['ext'] = $ext;
        }
    }

    global $lang, $conf;

    // get filename
    $id   = cleanID($id);
    $fn   = mediaFN($id);

    // get filetype regexp
    $types = array_keys(getMimeTypes());
    $types = array_map(
        function ($q) {
            return preg_quote($q, "/");
        },
        $types
    );
    $regex = join('|',$types);

    // because a temp file was created already
    if(!preg_match('/\.('.$regex.')$/i',$fn)) {
        return array($lang['uploadwrong'],-1);
    }

    //check for overwrite
    $overwrite = file_exists($fn);
    $auth_ow = (($conf['mediarevisions']) ? AUTH_UPLOAD : AUTH_DELETE);
    if($overwrite && (!$ow || $auth < $auth_ow)) {
        return array($lang['uploadexist'], 0);
    }
    // check for valid content
    $ok = media_contentcheck($file['name'], $file['mime']);
    if($ok == -1){
        return array(sprintf($lang['uploadbadcontent'],'.' . $file['ext']),-1);
    }elseif($ok == -2){
        return array($lang['uploadspam'],-1);
    }elseif($ok == -3){
        return array($lang['uploadxss'],-1);
    }

    // prepare event data
    $data = array();
    $data[0] = $file['name'];
    $data[1] = $fn;
    $data[2] = $id;
    $data[3] = $file['mime'];
    $data[4] = $overwrite;
    $data[5] = $move;

    // trigger event
    return Event::createAndTrigger('MEDIA_UPLOAD_FINISH', $data, '_media_upload_action', true);
}

/**
 * Callback adapter for media_upload_finish() triggered by MEDIA_UPLOAD_FINISH
 *
 * @author Michael Klier <chi@chimeric.de>
 *
 * @param array $data event data
 * @return false|array|string
 */
function _media_upload_action($data) {
    // fixme do further sanity tests of given data?
    if(is_array($data) && count($data)===6) {
        return media_upload_finish($data[0], $data[1], $data[2], $data[3], $data[4], $data[5]);
    } else {
        return false; //callback error
    }
}

/**
 * Saves an uploaded media file
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 * @author Michael Klier <chi@chimeric.de>
 * @author Kate Arzamastseva <pshns@ukr.net>
 *
 * @param string $fn_tmp
 * @param string $fn
 * @param string $id        media id
 * @param string $imime     mime type
 * @param bool   $overwrite overwrite existing?
 * @param string $move      function name
 * @return array|string
 */
function media_upload_finish($fn_tmp, $fn, $id, $imime, $overwrite, $move = 'move_uploaded_file') {
    global $conf;
    global $lang;
    global $REV;

    $old = @filemtime($fn);
    if(!file_exists(mediaFN($id, $old)) && file_exists($fn)) {
        // add old revision to the attic if missing
        media_saveOldRevision($id);
    }

    // prepare directory
    io_createNamespace($id, 'media');

    $filesize_old = file_exists($fn) ? filesize($fn) : 0;

    if($move($fn_tmp, $fn)) {
        @clearstatcache(true,$fn);
        $new = @filemtime($fn);
        // Set the correct permission here.
        // Always chmod media because they may be saved with different permissions than expected from the php umask.
        // (Should normally chmod to $conf['fperm'] only if $conf['fperm'] is set.)
        chmod($fn, $conf['fmode']);
        msg($lang['uploadsucc'],1);
        media_notify($id,$fn,$imime,$old,$new);
        // add a log entry to the media changelog
        $filesize_new = filesize($fn);
        $sizechange = $filesize_new - $filesize_old;
        if($REV) {
            addMediaLogEntry(
                $new,
                $id,
                DOKU_CHANGE_TYPE_REVERT,
                sprintf($lang['restored'], dformat($REV)),
                $REV,
                null,
                $sizechange
            );
        } elseif($overwrite) {
            addMediaLogEntry($new, $id, DOKU_CHANGE_TYPE_EDIT, '', '', null, $sizechange);
        } else {
            addMediaLogEntry($new, $id, DOKU_CHANGE_TYPE_CREATE, $lang['created'], '', null, $sizechange);
        }
        return $id;
    }else{
        return array($lang['uploadfail'],-1);
    }
}

/**
 * Moves the current version of media file to the media_attic
 * directory
 *
 * @author Kate Arzamastseva <pshns@ukr.net>
 *
 * @param string $id
 * @return int - revision date
 */
function media_saveOldRevision($id){
    global $conf, $lang;

    $oldf = mediaFN($id);
    if(!file_exists($oldf)) return '';
    $date = filemtime($oldf);
    if (!$conf['mediarevisions']) return $date;

    $medialog = new MediaChangeLog($id);
    if (!$medialog->getRevisionInfo($date)) {
        // there was an external edit,
        // there is no log entry for current version of file
        $sizechange = filesize($oldf);
        if(!file_exists(mediaMetaFN($id, '.changes'))) {
            addMediaLogEntry($date, $id, DOKU_CHANGE_TYPE_CREATE, $lang['created'], '', null, $sizechange);
        } else {
            $oldRev = $medialog->getRevisions(-1, 1); // from changelog
            $oldRev = (int) (empty($oldRev) ? 0 : $oldRev[0]);
            $filesize_old = filesize(mediaFN($id, $oldRev));
            $sizechange = $sizechange - $filesize_old;

            addMediaLogEntry($date, $id, DOKU_CHANGE_TYPE_EDIT, '', '', null, $sizechange);
        }
    }

    $newf = mediaFN($id,$date);
    io_makeFileDir($newf);
    if(copy($oldf, $newf)) {
        // Set the correct permission here.
        // Always chmod media because they may be saved with different permissions than expected from the php umask.
        // (Should normally chmod to $conf['fperm'] only if $conf['fperm'] is set.)
        chmod($newf, $conf['fmode']);
    }
    return $date;
}

/**
 * This function checks if the uploaded content is really what the
 * mimetype says it is. We also do spam checking for text types here.
 *
 * We need to do this stuff because we can not rely on the browser
 * to do this check correctly. Yes, IE is broken as usual.
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 * @link   http://www.splitbrain.org/blog/2007-02/12-internet_explorer_facilitates_cross_site_scripting
 * @fixme  check all 26 magic IE filetypes here?
 *
 * @param string $file path to file
 * @param string $mime mimetype
 * @return int
 */
function media_contentcheck($file,$mime){
    global $conf;
    if($conf['iexssprotect']){
        $fh = @fopen($file, 'rb');
        if($fh){
            $bytes = fread($fh, 256);
            fclose($fh);
            if(preg_match('/<(script|a|img|html|body|iframe)[\s>]/i',$bytes)){
                return -3; //XSS: possibly malicious content
            }
        }
    }
    if(substr($mime,0,6) == 'image/'){
        $info = @getimagesize($file);
        if($mime == 'image/gif' && $info[2] != 1){
            return -1; // uploaded content did not match the file extension
        }elseif($mime == 'image/jpeg' && $info[2] != 2){
            return -1;
        }elseif($mime == 'image/png' && $info[2] != 3){
            return -1;
        }
        # fixme maybe check other images types as well
    }elseif(substr($mime,0,5) == 'text/'){
        global $TEXT;
        $TEXT = io_readFile($file);
        if(checkwordblock()){
            return -2; //blocked by the spam blacklist
        }
    }
    return 0;
}

/**
 * Send a notify mail on uploads
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 *
 * @param string   $id      media id
 * @param string   $file    path to file
 * @param string   $mime    mime type
 * @param bool|int $old_rev revision timestamp or false
 * @return bool
 */
function media_notify($id,$file,$mime,$old_rev=false,$current_rev=false){
    global $conf;
    if(empty($conf['notify'])) return false; //notify enabled?

    $subscription = new MediaSubscriptionSender();
    return $subscription->sendMediaDiff($conf['notify'], 'uploadmail', $id, $old_rev, $current_rev);
}

/**
 * List all files in a given Media namespace
 *
 * @param string      $ns             namespace
 * @param null|int    $auth           permission level
 * @param string      $jump           id
 * @param bool        $fullscreenview
 * @param bool|string $sort           sorting order, false skips sorting
 */
function media_filelist($ns,$auth=null,$jump='',$fullscreenview=false,$sort=false){
    global $conf;
    global $lang;
    $ns = cleanID($ns);

    // check auth our self if not given (needed for ajax calls)
    if(is_null($auth)) $auth = auth_quickaclcheck("$ns:*");

    if (!$fullscreenview) echo '<h1 id="media__ns">:'.hsc($ns).'</h1>'.NL;

    if($auth < AUTH_READ){
        // FIXME: print permission warning here instead?
        echo '<div class="nothing">'.$lang['nothingfound'].'</div>'.NL;
    }else{
        if (!$fullscreenview) {
            media_uploadform($ns, $auth);
            media_searchform($ns);
        }

        $dir = utf8_encodeFN(str_replace(':','/',$ns));
        $data = array();
        search($data,$conf['mediadir'],'search_mediafiles',
                array('showmsg'=>true,'depth'=>1),$dir,1,$sort);

        if(!count($data)){
            echo '<div class="nothing">'.$lang['nothingfound'].'</div>'.NL;
        }else {
            if ($fullscreenview) {
                echo '<ul class="' . _media_get_list_type() . '">';
            }
            foreach($data as $item){
                if (!$fullscreenview) {
                    //FIXME old call: media_printfile($item,$auth,$jump);
                    $display = new \dokuwiki\Ui\Media\DisplayRow($item);
                    $display->scrollIntoView($jump == $item->getID());
                    $display->show();
                } else {
                    //FIXME old call: media_printfile_thumbs($item,$auth,$jump);
                    echo '<li>';
                    $display = new \dokuwiki\Ui\Media\DisplayTile($item);
                    $display->scrollIntoView($jump == $item->getID());
                    $display->show();
                    echo '</li>';
                }
            }
            if ($fullscreenview) echo '</ul>'.NL;
        }
    }
}

/**
 * Prints tabs for files list actions
 *
 * @author Kate Arzamastseva <pshns@ukr.net>
 * @author Adrian Lang <mail@adrianlang.de>
 *
 * @param string $selected_tab - opened tab
 */

function media_tabs_files($selected_tab = ''){
    global $lang;
    $tabs = array();
    foreach(array('files'  => 'mediaselect',
                  'upload' => 'media_uploadtab',
                  'search' => 'media_searchtab') as $tab => $caption) {
        $tabs[$tab] = array('href'    => media_managerURL(['tab_files' => $tab], '&'),
                            'caption' => $lang[$caption]);
    }

    html_tabs($tabs, $selected_tab);
}

/**
 * Prints tabs for files details actions
 *
 * @author Kate Arzamastseva <pshns@ukr.net>
 * @param string $image filename of the current image
 * @param string $selected_tab opened tab
 */
function media_tabs_details($image, $selected_tab = '') {
    global $lang, $conf;

    $tabs = array();
    $tabs['view'] = array('href'    => media_managerURL(['tab_details' => 'view'], '&'),
                          'caption' => $lang['media_viewtab']);

    list(, $mime) = mimetype($image);
    if ($mime == 'image/jpeg' && file_exists(mediaFN($image))) {
        $tabs['edit'] = array('href'    => media_managerURL(['tab_details' => 'edit'], '&'),
                              'caption' => $lang['media_edittab']);
    }
    if ($conf['mediarevisions']) {
        $tabs['history'] = array('href'    => media_managerURL(['tab_details' => 'history'], '&'),
                                 'caption' => $lang['media_historytab']);
    }

    html_tabs($tabs, $selected_tab);
}

/**
 * Prints options for the tab that displays a list of all files
 *
 * @author Kate Arzamastseva <pshns@ukr.net>
 */
function media_tab_files_options() {
    global $lang;
    global $INPUT;
    global $ID;

    $form = new Form([
            'method' => 'get',
            'action' => wl($ID),
            'class' => 'options'
    ]);
    $form->addTagOpen('div')->addClass('no');
    $form->setHiddenField('sectok', null);
    $media_manager_params = media_managerURL([], '', false, true);
    foreach ($media_manager_params as $pKey => $pVal) {
        $form->setHiddenField($pKey, $pVal);
    }
    if ($INPUT->has('q')) {
        $form->setHiddenField('q', $INPUT->str('q'));
    }
    $form->addHTML('<ul>'.NL);
    foreach (array('list' => array('listType', array('thumbs', 'rows')),
                  'sort' => array('sortBy', array('name', 'date')))
            as $group => $content) {
        $checked = "_media_get_${group}_type";
        $checked = $checked();

        $form->addHTML('<li class="'. $content[0] .'">');
        foreach ($content[1] as $option) {
            $attrs = array();
            if ($checked == $option) {
                $attrs['checked'] = 'checked';
            }
            $radio = $form->addRadioButton(
                $group.'_dwmedia',
                $lang['media_'.$group.'_'.$option]
            )->val($option)->id($content[0].'__'.$option)->addClass($option);
            $radio->attrs($attrs);
        }
        $form->addHTML('</li>'.NL);
    }
    $form->addHTML('<li>');
    $form->addButton('', $lang['btn_apply'])->attr('type', 'submit');
    $form->addHTML('</li>'.NL);
    $form->addHTML('</ul>'.NL);
    $form->addTagClose('div');
    print $form->toHTML();
}

/**
 * Returns type of sorting for the list of files in media manager
 *
 * @author Kate Arzamastseva <pshns@ukr.net>
 *
 * @return string - sort type
 */
function _media_get_sort_type() {
    return _media_get_display_param('sort', array('default' => 'name', 'date'));
}

/**
 * Returns type of listing for the list of files in media manager
 *
 * @author Kate Arzamastseva <pshns@ukr.net>
 *
 * @return string - list type
 */
function _media_get_list_type() {
    return _media_get_display_param('list', array('default' => 'thumbs', 'rows'));
}

/**
 * Get display parameters
 *
 * @param string $param   name of parameter
 * @param array  $values  allowed values, where default value has index key 'default'
 * @return string the parameter value
 */
function _media_get_display_param($param, $values) {
    global $INPUT;
    if (in_array($INPUT->str($param), $values)) {
        // FIXME: Set cookie
        return $INPUT->str($param);
    } else {
        $val = get_doku_pref($param, $values['default']);
        if (!in_array($val, $values)) {
            $val = $values['default'];
        }
        return $val;
    }
}

/**
 * Prints tab that displays a list of all files
 *
 * @author Kate Arzamastseva <pshns@ukr.net>
 *
 * @param string    $ns
 * @param null|int  $auth permission level
 * @param string    $jump item id
 */
function media_tab_files($ns,$auth=null,$jump='') {
    global $lang;
    if(is_null($auth)) $auth = auth_quickaclcheck("$ns:*");

    if($auth < AUTH_READ){
        echo '<div class="nothing">'.$lang['media_perm_read'].'</div>'.NL;
    }else{
        media_filelist($ns,$auth,$jump,true,_media_get_sort_type());
    }
}

/**
 * Prints tab that displays uploading form
 *
 * @author Kate Arzamastseva <pshns@ukr.net>
 *
 * @param string   $ns
 * @param null|int $auth permission level
 * @param string   $jump item id
 */
function media_tab_upload($ns,$auth=null,$jump='') {
    global $lang;
    if(is_null($auth)) $auth = auth_quickaclcheck("$ns:*");

    echo '<div class="upload">'.NL;
    if ($auth >= AUTH_UPLOAD) {
        echo '<p>' . $lang['mediaupload'] . '</p>';
    }
    media_uploadform($ns, $auth, true);
    echo '</div>'.NL;
}

/**
 * Prints tab that displays search form
 *
 * @author Kate Arzamastseva <pshns@ukr.net>
 *
 * @param string $ns
 * @param null|int $auth permission level
 */
function media_tab_search($ns,$auth=null) {
    global $INPUT;

    $do = $INPUT->str('mediado');
    $query = $INPUT->str('q');
    echo '<div class="search">'.NL;

    media_searchform($ns, $query, true);
    if ($do == 'searchlist' || $query) {
        media_searchlist($query,$ns,$auth,true,_media_get_sort_type());
    }
    echo '</div>'.NL;
}

/**
 * Prints tab that displays mediafile details
 *
 * @author Kate Arzamastseva <pshns@ukr.net>
 *
 * @param string     $image media id
 * @param string     $ns
 * @param null|int   $auth  permission level
 * @param string|int $rev   revision timestamp or empty string
 */
function media_tab_view($image, $ns, $auth=null, $rev='') {
    global $lang;
    if(is_null($auth)) $auth = auth_quickaclcheck("$ns:*");

    if ($image && $auth >= AUTH_READ) {
        $meta = new JpegMeta(mediaFN($image, $rev));
        media_preview($image, $auth, $rev, $meta);
        media_preview_buttons($image, $auth, $rev);
        media_details($image, $auth, $rev, $meta);

    } else {
        echo '<div class="nothing">'.$lang['media_perm_read'].'</div>'.NL;
    }
}

/**
 * Prints tab that displays form for editing mediafile metadata
 *
 * @author Kate Arzamastseva <pshns@ukr.net>
 *
 * @param string     $image media id
 * @param string     $ns
 * @param null|int   $auth permission level
 */
function media_tab_edit($image, $ns, $auth=null) {
    if(is_null($auth)) $auth = auth_quickaclcheck("$ns:*");

    if ($image) {
        list(, $mime) = mimetype($image);
        if ($mime == 'image/jpeg') media_metaform($image,$auth);
    }
}

/**
 * Prints tab that displays mediafile revisions
 *
 * @author Kate Arzamastseva <pshns@ukr.net>
 *
 * @param string     $image media id
 * @param string     $ns
 * @param null|int   $auth permission level
 */
function media_tab_history($image, $ns, $auth=null) {
    global $lang;
    global $INPUT;

    if(is_null($auth)) $auth = auth_quickaclcheck("$ns:*");
    $do = $INPUT->str('mediado');

    if ($auth >= AUTH_READ && $image) {
        if ($do == 'diff'){
            (new dokuwiki\Ui\MediaDiff($image))->show(); //media_diff($image, $ns, $auth);
        } else {
            $first = $INPUT->int('first');
            (new dokuwiki\Ui\MediaRevisions($image))->show($first);
        }
    } else {
        echo '<div class="nothing">'.$lang['media_perm_read'].'</div>'.NL;
    }
}

/**
 * Prints mediafile details
 *
 * @param string         $image media id
 * @param int            $auth permission level
 * @param int|string     $rev revision timestamp or empty string
 * @param JpegMeta|bool  $meta
 *
 * @author Kate Arzamastseva <pshns@ukr.net>
 */
function media_preview($image, $auth, $rev = '', $meta = false) {

    $size = media_image_preview_size($image, $rev, $meta);

    if ($size) {
        global $lang;
        echo '<div class="image">';

        $more = array();
        if ($rev) {
            $more['rev'] = $rev;
        } else {
            $t = @filemtime(mediaFN($image));
            $more['t'] = $t;
        }

        $more['w'] = $size[0];
        $more['h'] = $size[1];
        $src = ml($image, $more);

        echo '<a href="'.$src.'" target="_blank" title="'.$lang['mediaview'].'">';
        echo '<img src="'.$src.'" alt="" style="max-width: '.$size[0].'px;" />';
        echo '</a>';

        echo '</div>';
    }
}

/**
 * Prints mediafile action buttons
 *
 * @author Kate Arzamastseva <pshns@ukr.net>
 *
 * @param string     $image media id
 * @param int        $auth  permission level
 * @param int|string $rev   revision timestamp, or empty string
 */
function media_preview_buttons($image, $auth, $rev = '') {
    global $lang, $conf;

    echo '<ul class="actions">';

    if ($auth >= AUTH_DELETE && !$rev && file_exists(mediaFN($image))) {

        // delete button
        $form = new Form([
            'id' => 'mediamanager__btn_delete',
            'action' => media_managerURL(['delete' => $image], '&'),
        ]);
        $form->addTagOpen('div')->addClass('no');
        $form->addButton('', $lang['btn_delete'])->attr('type', 'submit');
        $form->addTagClose('div');
        echo '<li>';
        echo $form->toHTML();
        echo '</li>';
    }

    $auth_ow = (($conf['mediarevisions']) ? AUTH_UPLOAD : AUTH_DELETE);
    if ($auth >= $auth_ow && !$rev) {

        // upload new version button
        $form = new Form([
            'id' => 'mediamanager__btn_update',
            'action' => media_managerURL(['image' => $image, 'mediado' => 'update'], '&'),
        ]);
        $form->addTagOpen('div')->addClass('no');
        $form->addButton('', $lang['media_update'])->attr('type', 'submit');
        $form->addTagClose('div');
        echo '<li>';
        echo $form->toHTML();
        echo '</li>';
    }

    if ($auth >= AUTH_UPLOAD && $rev && $conf['mediarevisions'] && file_exists(mediaFN($image, $rev))) {

        // restore button
        $form = new Form([
            'id' => 'mediamanager__btn_restore',
            'action'=>media_managerURL(['image' => $image], '&'),
        ]);
        $form->addTagOpen('div')->addClass('no');
        $form->setHiddenField('mediado', 'restore');
        $form->setHiddenField('rev', $rev);
        $form->addButton('', $lang['media_restore'])->attr('type', 'submit');
        $form->addTagClose('div');
        echo '<li>';
        echo $form->toHTML();
        echo '</li>';
    }

    echo '</ul>';
}

/**
 * Returns image width and height for mediamanager preview panel
 *
 * @author Kate Arzamastseva <pshns@ukr.net>
 * @param string         $image
 * @param int|string     $rev
 * @param JpegMeta|bool  $meta
 * @param int            $size
 * @return array
 */
function media_image_preview_size($image, $rev, $meta = false, $size = 500) {
    if (!preg_match("/\.(jpe?g|gif|png)$/", $image)
      || !file_exists($filename = mediaFN($image, $rev))
    ) return array();

    $info = getimagesize($filename);
    $w = (int) $info[0];
    $h = (int) $info[1];

    if ($meta && ($w > $size || $h > $size)) {
        $ratio = $meta->getResizeRatio($size, $size);
        $w = floor($w * $ratio);
        $h = floor($h * $ratio);
    }
    return array($w, $h);
}

/**
 * Returns the requested EXIF/IPTC tag from the image meta
 *
 * @author Kate Arzamastseva <pshns@ukr.net>
 *
 * @param array    $tags array with tags, first existing is returned
 * @param JpegMeta $meta
 * @param string   $alt  alternative value
 * @return string
 */
function media_getTag($tags, $meta = false, $alt = '') {
    if (!$meta) return $alt;
    $info = $meta->getField($tags);
    if (!$info) return $alt;
    return $info;
}

/**
 * Returns mediafile tags
 *
 * @author Kate Arzamastseva <pshns@ukr.net>
 *
 * @param JpegMeta $meta
 * @return array list of tags of the mediafile
 */
function media_file_tags($meta) {
    // load the field descriptions
    static $fields = null;
    if (is_null($fields)) {
        $config_files = getConfigFiles('mediameta');
        foreach ($config_files as $config_file) {
            if (file_exists($config_file)) include($config_file);
        }
    }

    $tags = array();

    foreach ($fields as $key => $tag) {
        $t = array();
        if (!empty($tag[0])) $t = array($tag[0]);
        if (isset($tag[3]) && is_array($tag[3])) $t = array_merge($t,$tag[3]);
        $value = media_getTag($t, $meta);
        $tags[] = array('tag' => $tag, 'value' => $value);
    }

    return $tags;
}

/**
 * Prints mediafile tags
 *
 * @author Kate Arzamastseva <pshns@ukr.net>
 *
 * @param string        $image image id
 * @param int           $auth  permission level
 * @param string|int    $rev   revision timestamp, or empty string
 * @param bool|JpegMeta $meta  image object, or create one if false
 */
function media_details($image, $auth, $rev='', $meta=false) {
    global $lang;

    if (!$meta) $meta = new JpegMeta(mediaFN($image, $rev));
    $tags = media_file_tags($meta);

    echo '<dl>'.NL;
    foreach($tags as $tag){
        if ($tag['value']) {
            $value = cleanText($tag['value']);
            echo '<dt>'.$lang[$tag['tag'][1]].'</dt><dd>';
            if ($tag['tag'][2] == 'date') echo dformat($value);
            else echo hsc($value);
            echo '</dd>'.NL;
        }
    }
    echo '</dl>'.NL;
    echo '<dl>'.NL;
    echo '<dt>'.$lang['reference'].':</dt>';
    $media_usage = ft_mediause($image,true);
    if(count($media_usage) > 0){
        foreach($media_usage as $path){
            echo '<dd>'.html_wikilink($path).'</dd>';
        }
    }else{
        echo '<dd>'.$lang['nothingfound'].'</dd>';
    }
    echo '</dl>'.NL;

}

/**
 * Shows difference between two revisions of file
 *
 * @author Kate Arzamastseva <pshns@ukr.net>
 *
 * @param string $image  image id
 * @param string $ns
 * @param int $auth permission level
 * @param bool $fromajax
 * @return false|null|string
 * @deprecated 2020-12-31
 */
function media_diff($image, $ns, $auth, $fromajax = false) {
    dbg_deprecated('see '. \dokuwiki\Ui\MediaDiff::class .'::show()');
}

/**
 * Callback for media file diff
 *
 * @param array $data event data
 * @return false|null
 * @deprecated 2020-12-31
 */
function _media_file_diff($data) {
    dbg_deprecated('see '. \dokuwiki\Ui\MediaDiff::class .'::show()');
}

/**
 * Shows difference between two revisions of image
 *
 * @author Kate Arzamastseva <pshns@ukr.net>
 *
 * @param string $image
 * @param string|int $l_rev revision timestamp, or empty string
 * @param string|int $r_rev revision timestamp, or empty string
 * @param string $ns
 * @param int $auth permission level
 * @param bool $fromajax
 * @deprecated 2020-12-31
 */
function media_file_diff($image, $l_rev, $r_rev, $ns, $auth, $fromajax) {
    dbg_deprecated('see '. \dokuwiki\Ui\MediaDiff::class .'::showFileDiff()');
}

/**
 * Prints two images side by side
 * and slider
 *
 * @author Kate Arzamastseva <pshns@ukr.net>
 *
 * @param string $image   image id
 * @param int    $l_rev   revision timestamp, or empty string
 * @param int    $r_rev   revision timestamp, or empty string
 * @param array  $l_size  array with width and height
 * @param array  $r_size  array with width and height
 * @param string $type
 * @deprecated 2020-12-31
 */
function media_image_diff($image, $l_rev, $r_rev, $l_size, $r_size, $type) {
    dbg_deprecated('see '. \dokuwiki\Ui\MediaDiff::class .'::showImageDiff()');
}

/**
 * Restores an old revision of a media file
 *
 * @param string $image media id
 * @param int    $rev   revision timestamp or empty string
 * @param int    $auth
 * @return string - file's id
 *
 * @author Kate Arzamastseva <pshns@ukr.net>
 */
function media_restore($image, $rev, $auth){
    global $conf;
    if ($auth < AUTH_UPLOAD || !$conf['mediarevisions']) return false;
    $removed = (!file_exists(mediaFN($image)) && file_exists(mediaMetaFN($image, '.changes')));
    if (!$image || (!file_exists(mediaFN($image)) && !$removed)) return false;
    if (!$rev || !file_exists(mediaFN($image, $rev))) return false;
    list(,$imime,) = mimetype($image);
    $res = media_upload_finish(mediaFN($image, $rev),
        mediaFN($image),
        $image,
        $imime,
        true,
        'copy');
    if (is_array($res)) {
        msg($res[0], $res[1]);
        return false;
    }
    return $res;
}

/**
 * List all files found by the search request
 *
 * @author Tobias Sarnowski <sarnowski@cosmocode.de>
 * @author Andreas Gohr <gohr@cosmocode.de>
 * @author Kate Arzamastseva <pshns@ukr.net>
 * @triggers MEDIA_SEARCH
 *
 * @param string $query
 * @param string $ns
 * @param null|int $auth
 * @param bool $fullscreen
 * @param string $sort
 */
function media_searchlist($query,$ns,$auth=null,$fullscreen=false,$sort='natural'){
    global $conf;
    global $lang;

    $ns = cleanID($ns);
    $evdata = array(
        'ns'    => $ns,
        'data'  => array(),
        'query' => $query
    );
    if (!blank($query)) {
        $evt = new Event('MEDIA_SEARCH', $evdata);
        if ($evt->advise_before()) {
            $dir = utf8_encodeFN(str_replace(':','/',$evdata['ns']));
            $quoted = preg_quote($evdata['query'],'/');
            //apply globbing
            $quoted = str_replace(array('\*', '\?'), array('.*', '.'), $quoted, $count);

            //if we use globbing file name must match entirely but may be preceded by arbitrary namespace
            if ($count > 0) $quoted = '^([^:]*:)*'.$quoted.'$';

            $pattern = '/'.$quoted.'/i';
            search($evdata['data'],
                    $conf['mediadir'],
                    'search_mediafiles',
                    array('showmsg'=>false,'pattern'=>$pattern),
                    $dir,
                    1,
                    $sort);
        }
        $evt->advise_after();
        unset($evt);
    }

    if (!$fullscreen) {
        echo '<h1 id="media__ns">'.sprintf($lang['searchmedia_in'],hsc($ns).':*').'</h1>'.NL;
        media_searchform($ns,$query);
    }

    if(!count($evdata['data'])){
        echo '<div class="nothing">'.$lang['nothingfound'].'</div>'.NL;
    }else {
        if ($fullscreen) {
            echo '<ul class="' . _media_get_list_type() . '">';
        }
        foreach($evdata['data'] as $item){
            if (!$fullscreen) {
                // FIXME old call: media_printfile($item,$item['perm'],'',true);
                $display = new \dokuwiki\Ui\Media\DisplayRow($item);
                $display->relativeDisplay($ns);
                $display->show();
            } else {
                // FIXME old call: media_printfile_thumbs($item,$item['perm'],false,true);
                $display = new \dokuwiki\Ui\Media\DisplayTile($item);
                $display->relativeDisplay($ns);
                echo '<li>';
                $display->show();
                echo '</li>';
            }
        }
        if ($fullscreen) echo '</ul>'.NL;
    }
}

/**
 * Display a media icon
 *
 * @param string $filename media id
 * @param string $size     the size subfolder, if not specified 16x16 is used
 * @return string html
 */
function media_printicon($filename, $size=''){
    list($ext) = mimetype(mediaFN($filename),false);

    if (file_exists(DOKU_INC.'lib/images/fileicons/'.$size.'/'.$ext.'.png')) {
        $icon = DOKU_BASE.'lib/images/fileicons/'.$size.'/'.$ext.'.png';
    } else {
        $icon = DOKU_BASE.'lib/images/fileicons/'.$size.'/file.png';
    }

    return '<img src="'.$icon.'" alt="'.$filename.'" class="icon" />';
}

/**
 * Build link based on the current, adding/rewriting parameters
 *
 * @author Kate Arzamastseva <pshns@ukr.net>
 *
 * @param array|bool $params
 * @param string     $amp           separator
 * @param bool       $abs           absolute url?
 * @param bool       $params_array  return the parmeters array?
 * @return string|array - link or link parameters
 */
function media_managerURL($params = false, $amp = '&amp;', $abs = false, $params_array = false) {
    global $ID;
    global $INPUT;

    $gets = array('do' => 'media');
    $media_manager_params = array('tab_files', 'tab_details', 'image', 'ns', 'list', 'sort');
    foreach ($media_manager_params as $x) {
        if ($INPUT->has($x)) $gets[$x] = $INPUT->str($x);
    }

    if ($params) {
        $gets = $params + $gets;
    }
    unset($gets['id']);
    if (isset($gets['delete'])) {
        unset($gets['image']);
        unset($gets['tab_details']);
    }

    if ($params_array) return $gets;

    return wl($ID,$gets,$abs,$amp);
}

/**
 * Print the media upload form if permissions are correct
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 * @author Kate Arzamastseva <pshns@ukr.net>
 *
 * @param string $ns
 * @param int    $auth permission level
 * @param bool  $fullscreen
 */
function media_uploadform($ns, $auth, $fullscreen = false) {
    global $lang;
    global $conf;
    global $INPUT;

    if ($auth < AUTH_UPLOAD) {
        echo '<div class="nothing">'.$lang['media_perm_upload'].'</div>'.NL;
        return;
    }
    $auth_ow = (($conf['mediarevisions']) ? AUTH_UPLOAD : AUTH_DELETE);

    $update = false;
    $id = '';
    if ($auth >= $auth_ow && $fullscreen && $INPUT->str('mediado') == 'update') {
        $update = true;
        $id = cleanID($INPUT->str('image'));
    }

    // The default HTML upload form
    $form = new Form([
        'id' => 'dw__upload',
        'enctype' => 'multipart/form-data',
        'action' => ($fullscreen)
                    ? media_managerURL(['tab_files' => 'files', 'tab_details' => 'view'], '&')
                    : DOKU_BASE.'lib/exe/mediamanager.php',
    ]);
    $form->addTagOpen('div')->addClass('no');
    $form->setHiddenField('ns', hsc($ns));  // FIXME hsc required?
    $form->addTagOpen('p');
    $form->addTextInput('upload', $lang['txt_upload'])->id('upload__file')
            ->attrs(['type' => 'file']);
    $form->addTagClose('p');
    $form->addTagOpen('p');
    $form->addTextInput('mediaid', $lang['txt_filename'])->id('upload__name')
            ->val(noNS($id));
    $form->addButton('', $lang['btn_upload'])->attr('type', 'submit');
    $form->addTagClose('p');
    if ($auth >= $auth_ow){
        $form->addTagOpen('p');
        $attrs = array();
        if ($update) $attrs['checked'] = 'checked';
        $form->addCheckbox('ow', $lang['txt_overwrt'])->id('dw__ow')->val('1')
            ->addClass('check')->attrs($attrs);
        $form->addTagClose('p');
    }
    $form->addTagClose('div');

    if (!$fullscreen) {
        echo '<div class="upload">'. $lang['mediaupload'] .'</div>'.DOKU_LF;
    } else {
        echo DOKU_LF;
    }

    echo '<div id="mediamanager__uploader">'.DOKU_LF;
    echo $form->toHTML('Upload');
    echo '</div>'.DOKU_LF;

    echo '<p class="maxsize">';
    printf($lang['maxuploadsize'], filesize_h(media_getuploadsize()));
    echo ' <a class="allowedmime" href="#">'. $lang['allowedmime'] .'</a>';
    echo ' <span>'. implode(', ', array_keys(getMimeTypes())) .'</span>';
    echo '</p>'.DOKU_LF;
}

/**
 * Returns the size uploaded files may have
 *
 * This uses a conservative approach using the lowest number found
 * in any of the limiting ini settings
 *
 * @returns int size in bytes
 */
function media_getuploadsize(){
    $okay = 0;

    $post = (int) php_to_byte(@ini_get('post_max_size'));
    $suho = (int) php_to_byte(@ini_get('suhosin.post.max_value_length'));
    $upld = (int) php_to_byte(@ini_get('upload_max_filesize'));

    if($post && ($post < $okay || $okay == 0)) $okay = $post;
    if($suho && ($suho < $okay || $okay == 0)) $okay = $suho;
    if($upld && ($upld < $okay || $okay == 0)) $okay = $upld;

    return $okay;
}

/**
 * Print the search field form
 *
 * @author Tobias Sarnowski <sarnowski@cosmocode.de>
 * @author Kate Arzamastseva <pshns@ukr.net>
 *
 * @param string $ns
 * @param string $query
 * @param bool $fullscreen
 */
function media_searchform($ns, $query = '', $fullscreen = false) {
    global $lang;

    // The default HTML search form
    $form = new Form([
        'id'     => 'dw__mediasearch',
        'action' => ($fullscreen)
                    ? media_managerURL([], '&')
                    : DOKU_BASE.'lib/exe/mediamanager.php',
    ]);
    $form->addTagOpen('div')->addClass('no');
    $form->setHiddenField('ns', $ns);
    $form->setHiddenField($fullscreen ? 'mediado' : 'do', 'searchlist');

    $form->addTagOpen('p');
    $form->addTextInput('q', $lang['searchmedia'])
            ->attr('title', sprintf($lang['searchmedia_in'], hsc($ns) .':*'))
            ->val($query);
    $form->addHTML(' ');
    $form->addButton('', $lang['btn_search'])->attr('type', 'submit');
    $form->addTagClose('p');
    $form->addTagClose('div');
    print $form->toHTML('SearchMedia');
}

/**
 * Build a tree outline of available media namespaces
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 *
 * @param string $ns
 */
function media_nstree($ns){
    global $conf;
    global $lang;

    // currently selected namespace
    $ns  = cleanID($ns);
    if(empty($ns)){
        global $ID;
        $ns = (string)getNS($ID);
    }

    $ns_dir  = utf8_encodeFN(str_replace(':','/',$ns));

    $data = array();
    search($data,$conf['mediadir'],'search_index',array('ns' => $ns_dir, 'nofiles' => true));

    // wrap a list with the root level around the other namespaces
    array_unshift($data, array('level' => 0, 'id' => '', 'open' =>'true',
                               'label' => '['.$lang['mediaroot'].']'));

    // insert the current ns into the hierarchy if it isn't already part of it
    $ns_parts = explode(':', $ns);
    $tmp_ns = '';
    $pos = 0;
    foreach ($ns_parts as $level => $part) {
        if ($tmp_ns) $tmp_ns .= ':'.$part;
        else $tmp_ns = $part;

        // find the namespace parts or insert them
        while ($data[$pos]['id'] != $tmp_ns) {
            if (
                $pos >= count($data) ||
                ($data[$pos]['level'] <= $level+1 && Sort::strcmp($data[$pos]['id'], $tmp_ns) > 0)
            ) {
                array_splice($data, $pos, 0, array(array('level' => $level+1, 'id' => $tmp_ns, 'open' => 'true')));
                break;
            }
            ++$pos;
        }
    }

    echo html_buildlist($data,'idx','media_nstree_item','media_nstree_li');
}

/**
 * Userfunction for html_buildlist
 *
 * Prints a media namespace tree item
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 *
 * @param array $item
 * @return string html
 */
function media_nstree_item($item){
    global $INPUT;
    $pos   = strrpos($item['id'], ':');
    $label = substr($item['id'], $pos > 0 ? $pos + 1 : 0);
    if(empty($item['label'])) $item['label'] = $label;

    $ret  = '';
    if (!($INPUT->str('do') == 'media'))
    $ret .= '<a href="'.DOKU_BASE.'lib/exe/mediamanager.php?ns='.idfilter($item['id']).'" class="idx_dir">';
    else $ret .= '<a href="'.media_managerURL(['ns' => idfilter($item['id'], false), 'tab_files' => 'files'])
        .'" class="idx_dir">';
    $ret .= $item['label'];
    $ret .= '</a>';
    return $ret;
}

/**
 * Userfunction for html_buildlist
 *
 * Prints a media namespace tree item opener
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 *
 * @param array $item
 * @return string html
 */
function media_nstree_li($item){
    $class='media level'.$item['level'];
    if($item['open']){
        $class .= ' open';
        $img   = DOKU_BASE.'lib/images/minus.gif';
        $alt   = '−';
    }else{
        $class .= ' closed';
        $img   = DOKU_BASE.'lib/images/plus.gif';
        $alt   = '+';
    }
    // TODO: only deliver an image if it actually has a subtree...
    return '<li class="'.$class.'">'.
        '<img src="'.$img.'" alt="'.$alt.'" />';
}

/**
 * Resizes the given image to the given size
 *
 * @author  Andreas Gohr <andi@splitbrain.org>
 *
 * @param string $file filename, path to file
 * @param string $ext  extension
 * @param int    $w    desired width
 * @param int    $h    desired height
 * @return string path to resized or original size if failed
 */
function media_resize_image($file, $ext, $w, $h=0){
    global $conf;
    if(!$h) $h = $w;
    // we wont scale up to infinity
    if($w > 2000 || $h > 2000) return $file;

    //cache
    $local = getCacheName($file,'.media.'.$w.'x'.$h.'.'.$ext);
    $mtime = (int) @filemtime($local); // 0 if not exists

    $options = [
        'quality' => $conf['jpg_quality'],
        'imconvert' => $conf['im_convert'],
    ];

    if( $mtime <= (int) @filemtime($file) ) {
        try {
            \splitbrain\slika\Slika::run($file, $options)
                                   ->autorotate()
                                   ->resize($w, $h)
                                   ->save($local, $ext);
            if($conf['fperm']) @chmod($local, $conf['fperm']);
        } catch (\splitbrain\slika\Exception $e) {
            dbglog($e->getMessage());
            return $file;
        }
    }

    return $local;
}

/**
 * Center crops the given image to the wanted size
 *
 * @author  Andreas Gohr <andi@splitbrain.org>
 *
 * @param string $file filename, path to file
 * @param string $ext  extension
 * @param int    $w    desired width
 * @param int    $h    desired height
 * @return string path to resized or original size if failed
 */
function media_crop_image($file, $ext, $w, $h=0){
    global $conf;
    if(!$h) $h = $w;
    // we wont scale up to infinity
    if($w > 2000 || $h > 2000) return $file;

    //cache
    $local = getCacheName($file,'.media.'.$w.'x'.$h.'.crop.'.$ext);
    $mtime = (int) @filemtime($local); // 0 if not exists

    $options = [
        'quality' => $conf['jpg_quality'],
        'imconvert' => $conf['im_convert'],
    ];

    if( $mtime <= (int) @filemtime($file) ) {
        try {
            \splitbrain\slika\Slika::run($file, $options)
                                   ->autorotate()
                                    ->crop($w, $h)
                                    ->save($local, $ext);
            if($conf['fperm']) @chmod($local, $conf['fperm']);
        } catch (\splitbrain\slika\Exception $e) {
            dbglog($e->getMessage());
            return $file;
        }
    }

    return $local;
}

/**
 * Calculate a token to be used to verify fetch requests for resized or
 * cropped images have been internally generated - and prevent external
 * DDOS attacks via fetch
 *
 * @author Christopher Smith <chris@jalakai.co.uk>
 *
 * @param string  $id    id of the image
 * @param int     $w     resize/crop width
 * @param int     $h     resize/crop height
 * @return string token or empty string if no token required
 */
function media_get_token($id,$w,$h){
    // token is only required for modified images
    if ($w || $h || media_isexternal($id)) {
        $token = $id;
        if ($w) $token .= '.'.$w;
        if ($h) $token .= '.'.$h;

        return substr(\dokuwiki\PassHash::hmac('md5', $token, auth_cookiesalt()),0,6);
    }

    return '';
}

/**
 * Download a remote file and return local filename
 *
 * returns false if download fails. Uses cached file if available and
 * wanted
 *
 * @author  Andreas Gohr <andi@splitbrain.org>
 * @author  Pavel Vitis <Pavel.Vitis@seznam.cz>
 *
 * @param string $url
 * @param string $ext   extension
 * @param int    $cache cachetime in seconds
 * @return false|string path to cached file
 */
function media_get_from_URL($url,$ext,$cache){
    global $conf;

    // if no cache or fetchsize just redirect
    if ($cache==0)           return false;
    if (!$conf['fetchsize']) return false;

    $local = getCacheName(strtolower($url),".media.$ext");
    $mtime = @filemtime($local); // 0 if not exists

    //decide if download needed:
    if(($mtime == 0) || // cache does not exist
        ($cache != -1 && $mtime < time() - $cache) // 'recache' and cache has expired
    ) {
        if(media_image_download($url, $local)) {
            return $local;
        } else {
            return false;
        }
    }

    //if cache exists use it else
    if($mtime) return $local;

    //else return false
    return false;
}

/**
 * Download image files
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 *
 * @param string $url
 * @param string $file path to file in which to put the downloaded content
 * @return bool
 */
function media_image_download($url,$file){
    global $conf;
    $http = new DokuHTTPClient();
    $http->keep_alive = false; // we do single ops here, no need for keep-alive

    $http->max_bodysize = $conf['fetchsize'];
    $http->timeout = 25; //max. 25 sec
    $http->header_regexp = '!\r\nContent-Type: image/(jpe?g|gif|png)!i';

    $data = $http->get($url);
    if(!$data) return false;

    $fileexists = file_exists($file);
    $fp = @fopen($file,"w");
    if(!$fp) return false;
    fwrite($fp,$data);
    fclose($fp);
    if(!$fileexists and $conf['fperm']) chmod($file, $conf['fperm']);

    // check if it is really an image
    $info = @getimagesize($file);
    if(!$info){
        @unlink($file);
        return false;
    }

    return true;
}

/**
 * resize images using external ImageMagick convert program
 *
 * @author Pavel Vitis <Pavel.Vitis@seznam.cz>
 * @author Andreas Gohr <andi@splitbrain.org>
 *
 * @param string $ext     extension
 * @param string $from    filename path to file
 * @param int    $from_w  original width
 * @param int    $from_h  original height
 * @param string $to      path to resized file
 * @param int    $to_w    desired width
 * @param int    $to_h    desired height
 * @return bool
 */
function media_resize_imageIM($ext,$from,$from_w,$from_h,$to,$to_w,$to_h){
    global $conf;

    // check if convert is configured
    if(!$conf['im_convert']) return false;

    // prepare command
    $cmd  = $conf['im_convert'];
    $cmd .= ' -resize '.$to_w.'x'.$to_h.'!';
    if ($ext == 'jpg' || $ext == 'jpeg') {
        $cmd .= ' -quality '.$conf['jpg_quality'];
    }
    $cmd .= " $from $to";

    @exec($cmd,$out,$retval);
    if ($retval == 0) return true;
    return false;
}

/**
 * crop images using external ImageMagick convert program
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 *
 * @param string $ext     extension
 * @param string $from    filename path to file
 * @param int    $from_w  original width
 * @param int    $from_h  original height
 * @param string $to      path to resized file
 * @param int    $to_w    desired width
 * @param int    $to_h    desired height
 * @param int    $ofs_x   offset of crop centre
 * @param int    $ofs_y   offset of crop centre
 * @return bool
 * @deprecated 2020-09-01
 */
function media_crop_imageIM($ext,$from,$from_w,$from_h,$to,$to_w,$to_h,$ofs_x,$ofs_y){
    global $conf;
    dbg_deprecated('splitbrain\\Slika');

    // check if convert is configured
    if(!$conf['im_convert']) return false;

    // prepare command
    $cmd  = $conf['im_convert'];
    $cmd .= ' -crop '.$to_w.'x'.$to_h.'+'.$ofs_x.'+'.$ofs_y;
    if ($ext == 'jpg' || $ext == 'jpeg') {
        $cmd .= ' -quality '.$conf['jpg_quality'];
    }
    $cmd .= " $from $to";

    @exec($cmd,$out,$retval);
    if ($retval == 0) return true;
    return false;
}

/**
 * resize or crop images using PHP's libGD support
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 * @author Sebastian Wienecke <s_wienecke@web.de>
 *
 * @param string $ext     extension
 * @param string $from    filename path to file
 * @param int    $from_w  original width
 * @param int    $from_h  original height
 * @param string $to      path to resized file
 * @param int    $to_w    desired width
 * @param int    $to_h    desired height
 * @param int    $ofs_x   offset of crop centre
 * @param int    $ofs_y   offset of crop centre
 * @return bool
 * @deprecated 2020-09-01
 */
function media_resize_imageGD($ext,$from,$from_w,$from_h,$to,$to_w,$to_h,$ofs_x=0,$ofs_y=0){
    global $conf;
    dbg_deprecated('splitbrain\\Slika');

    if($conf['gdlib'] < 1) return false; //no GDlib available or wanted

    // check available memory
    if(!is_mem_available(($from_w * $from_h * 4) + ($to_w * $to_h * 4))){
        return false;
    }

    // create an image of the given filetype
    $image = false;
    if ($ext == 'jpg' || $ext == 'jpeg'){
        if(!function_exists("imagecreatefromjpeg")) return false;
        $image = @imagecreatefromjpeg($from);
    }elseif($ext == 'png') {
        if(!function_exists("imagecreatefrompng")) return false;
        $image = @imagecreatefrompng($from);

    }elseif($ext == 'gif') {
        if(!function_exists("imagecreatefromgif")) return false;
        $image = @imagecreatefromgif($from);
    }
    if(!$image) return false;

    $newimg = false;
    if(($conf['gdlib']>1) && function_exists("imagecreatetruecolor") && $ext != 'gif'){
        $newimg = @imagecreatetruecolor ($to_w, $to_h);
    }
    if(!$newimg) $newimg = @imagecreate($to_w, $to_h);
    if(!$newimg){
        imagedestroy($image);
        return false;
    }

    //keep png alpha channel if possible
    if($ext == 'png' && $conf['gdlib']>1 && function_exists('imagesavealpha')){
        imagealphablending($newimg, false);
        imagesavealpha($newimg,true);
    }

    //keep gif transparent color if possible
    if($ext == 'gif' && function_exists('imagefill') && function_exists('imagecolorallocate')) {
        if(function_exists('imagecolorsforindex') && function_exists('imagecolortransparent')) {
            $transcolorindex = @imagecolortransparent($image);
            if($transcolorindex >= 0 ) { //transparent color exists
                $transcolor = @imagecolorsforindex($image, $transcolorindex);
                $transcolorindex = @imagecolorallocate(
                    $newimg,
                    $transcolor['red'],
                    $transcolor['green'],
                    $transcolor['blue']
                );
                @imagefill($newimg, 0, 0, $transcolorindex);
                @imagecolortransparent($newimg, $transcolorindex);
            }else{ //filling with white
                $whitecolorindex = @imagecolorallocate($newimg, 255, 255, 255);
                @imagefill($newimg, 0, 0, $whitecolorindex);
            }
        }else{ //filling with white
            $whitecolorindex = @imagecolorallocate($newimg, 255, 255, 255);
            @imagefill($newimg, 0, 0, $whitecolorindex);
        }
    }

    //try resampling first
    if(function_exists("imagecopyresampled")){
        if(!@imagecopyresampled($newimg, $image, 0, 0, $ofs_x, $ofs_y, $to_w, $to_h, $from_w, $from_h)) {
            imagecopyresized($newimg, $image, 0, 0, $ofs_x, $ofs_y, $to_w, $to_h, $from_w, $from_h);
        }
    }else{
        imagecopyresized($newimg, $image, 0, 0, $ofs_x, $ofs_y, $to_w, $to_h, $from_w, $from_h);
    }

    $okay = false;
    if ($ext == 'jpg' || $ext == 'jpeg'){
        if(!function_exists('imagejpeg')){
            $okay = false;
        }else{
            $okay = imagejpeg($newimg, $to, $conf['jpg_quality']);
        }
    }elseif($ext == 'png') {
        if(!function_exists('imagepng')){
            $okay = false;
        }else{
            $okay =  imagepng($newimg, $to);
        }
    }elseif($ext == 'gif') {
        if(!function_exists('imagegif')){
            $okay = false;
        }else{
            $okay = imagegif($newimg, $to);
        }
    }

    // destroy GD image ressources
    if($image) imagedestroy($image);
    if($newimg) imagedestroy($newimg);

    return $okay;
}

/**
 * Return other media files with the same base name
 * but different extensions.
 *
 * @param string   $src     - ID of media file
 * @param string[] $exts    - alternative extensions to find other files for
 * @return array            - array(mime type => file ID)
 *
 * @author Anika Henke <anika@selfthinker.org>
 */
function media_alternativefiles($src, $exts){

    $files = array();
    list($srcExt, /* $srcMime */) = mimetype($src);
    $filebase = substr($src, 0, -1 * (strlen($srcExt)+1));

    foreach($exts as $ext) {
        $fileid = $filebase.'.'.$ext;
        $file = mediaFN($fileid);
        if(file_exists($file)) {
            list(/* $fileExt */, $fileMime) = mimetype($file);
            $files[$fileMime] = $fileid;
        }
    }
    return $files;
}

/**
 * Check if video/audio is supported to be embedded.
 *
 * @param string $mime      - mimetype of media file
 * @param string $type      - type of media files to check ('video', 'audio', or null for all)
 * @return boolean
 *
 * @author Anika Henke <anika@selfthinker.org>
 */
function media_supportedav($mime, $type=NULL){
    $supportedAudio = array(
        'ogg' => 'audio/ogg',
        'mp3' => 'audio/mpeg',
        'wav' => 'audio/wav',
    );
    $supportedVideo = array(
        'webm' => 'video/webm',
        'ogv' => 'video/ogg',
        'mp4' => 'video/mp4',
    );
    if ($type == 'audio') {
        $supportedAv = $supportedAudio;
    } elseif ($type == 'video') {
        $supportedAv = $supportedVideo;
    } else {
        $supportedAv = array_merge($supportedAudio, $supportedVideo);
    }
    return in_array($mime, $supportedAv);
}

/**
 * Return track media files with the same base name
 * but extensions that indicate kind and lang.
 * ie for foo.webm search foo.sub.lang.vtt, foo.cap.lang.vtt...
 *
 * @param string   $src     - ID of media file
 * @return array            - array(mediaID => array( kind, srclang ))
 *
 * @author Schplurtz le Déboulonné <Schplurtz@laposte.net>
 */
function media_trackfiles($src){
    $kinds=array(
        'sub' => 'subtitles',
        'cap' => 'captions',
        'des' => 'descriptions',
        'cha' => 'chapters',
        'met' => 'metadata'
    );

    $files = array();
    $re='/\\.(sub|cap|des|cha|met)\\.([^.]+)\\.vtt$/';
    $baseid=pathinfo($src, PATHINFO_FILENAME);
    $pattern=mediaFN($baseid).'.*.*.vtt';
    $list=glob($pattern);
    foreach($list as $track) {
        if(preg_match($re, $track, $matches)){
            $files[$baseid.'.'.$matches[1].'.'.$matches[2].'.vtt']=array(
                $kinds[$matches[1]],
                $matches[2],
            );
        }
    }
    return $files;
}

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */
