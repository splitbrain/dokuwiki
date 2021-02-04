<?php

namespace dokuwiki\Remote;

use Doku_Renderer_xhtml;
use dokuwiki\ChangeLog\MediaChangeLog;
use dokuwiki\ChangeLog\PageChangeLog;
use dokuwiki\Extension\Event;
use dokuwiki\Utf8\Sort;

define('DOKU_API_VERSION', 10);

/**
 * Provides the core methods for the remote API.
 * The methods are ordered in 'wiki.<method>' and 'dokuwiki.<method>' namespaces
 */
class ApiCore
{
    /** @var int Increased whenever the API is changed */
    const API_VERSION = 10;


    /** @var Api */
    private $api;

    /**
     * @param Api $api
     */
    public function __construct(Api $api)
    {
        $this->api = $api;
    }

    /**
     * Returns details about the core methods
     *
     * @return array
     */
    public function getRemoteInfo()
    {
        return array(
            'dokuwiki.getVersion' => array(
                'args' => array(),
                'return' => 'string',
                'doc' => 'Returns the running DokuWiki version.'
            ), 'dokuwiki.login' => array(
                'args' => array('string', 'string'),
                'return' => 'int',
                'doc' => 'Tries to login with the given credentials and sets auth cookies.',
                'public' => '1'
            ), 'dokuwiki.logoff' => array(
                'args' => array(),
                'return' => 'int',
                'doc' => 'Tries to logoff by expiring auth cookies and the associated PHP session.'
            ), 'dokuwiki.getPagelist' => array(
                'args' => array('string', 'array'),
                'return' => 'array',
                'doc' => 'List all pages within the given namespace.',
                'name' => 'readNamespace'
            ), 'dokuwiki.search' => array(
                'args' => array('string'),
                'return' => 'array',
                'doc' => 'Perform a fulltext search and return a list of matching pages'
            ), 'dokuwiki.getTime' => array(
                'args' => array(),
                'return' => 'int',
                'doc' => 'Returns the current time at the remote wiki server as Unix timestamp.',
            ), 'dokuwiki.setLocks' => array(
                'args' => array('array'),
                'return' => 'array',
                'doc' => 'Lock or unlock pages.'
            ), 'dokuwiki.getTitle' => array(
                'args' => array(),
                'return' => 'string',
                'doc' => 'Returns the wiki title.',
                'public' => '1'
            ), 'dokuwiki.appendPage' => array(
                'args' => array('string', 'string', 'array'),
                'return' => 'bool',
                'doc' => 'Append text to a wiki page.'
            ), 'dokuwiki.deleteUsers' => array(
                'args' => array('array'),
                'return' => 'bool',
                'doc' => 'Remove one or more users from the list of registered users.'
            ),  'wiki.getPage' => array(
                'args' => array('string'),
                'return' => 'string',
                'doc' => 'Get the raw Wiki text of page, latest version.',
                'name' => 'rawPage',
            ), 'wiki.getPageVersion' => array(
                'args' => array('string', 'int'),
                'name' => 'rawPage',
                'return' => 'string',
                'doc' => 'Return a raw wiki page'
            ), 'wiki.getPageHTML' => array(
                'args' => array('string'),
                'return' => 'string',
                'doc' => 'Return page in rendered HTML, latest version.',
                'name' => 'htmlPage'
            ), 'wiki.getPageHTMLVersion' => array(
                'args' => array('string', 'int'),
                'return' => 'string',
                'doc' => 'Return page in rendered HTML.',
                'name' => 'htmlPage'
            ), 'wiki.getAllPages' => array(
                'args' => array(),
                'return' => 'array',
                'doc' => 'Returns a list of all pages. The result is an array of utf8 pagenames.',
                'name' => 'listPages'
            ), 'wiki.getAttachments' => array(
                'args' => array('string', 'array'),
                'return' => 'array',
                'doc' => 'Returns a list of all media files.',
                'name' => 'listAttachments'
            ), 'wiki.getBackLinks' => array(
                'args' => array('string'),
                'return' => 'array',
                'doc' => 'Returns the pages that link to this page.',
                'name' => 'listBackLinks'
            ), 'wiki.getPageInfo' => array(
                'args' => array('string'),
                'return' => 'array',
                'doc' => 'Returns a struct with info about the page, latest version.',
                'name' => 'pageInfo'
            ), 'wiki.getPageInfoVersion' => array(
                'args' => array('string', 'int'),
                'return' => 'array',
                'doc' => 'Returns a struct with info about the page.',
                'name' => 'pageInfo'
            ), 'wiki.getPageVersions' => array(
                'args' => array('string', 'int'),
                'return' => 'array',
                'doc' => 'Returns the available revisions of the page.',
                'name' => 'pageVersions'
            ), 'wiki.putPage' => array(
                'args' => array('string', 'string', 'array'),
                'return' => 'bool',
                'doc' => 'Saves a wiki page.'
            ), 'wiki.listLinks' => array(
                'args' => array('string'),
                'return' => 'array',
                'doc' => 'Lists all links contained in a wiki page.'
            ), 'wiki.getRecentChanges' => array(
                'args' => array('int'),
                'return' => 'array',
                'doc' => 'Returns a struct about all recent changes since given timestamp.'
            ), 'wiki.getRecentMediaChanges' => array(
                'args' => array('int'),
                'return' => 'array',
                'doc' => 'Returns a struct about all recent media changes since given timestamp.'
            ), 'wiki.aclCheck' => array(
                'args' => array('string', 'string', 'array'),
                'return' => 'int',
                'doc' => 'Returns the permissions of a given wiki page. By default, for current user/groups'
            ), 'wiki.putAttachment' => array(
                'args' => array('string', 'file', 'array'),
                'return' => 'array',
                'doc' => 'Upload a file to the wiki.'
            ), 'wiki.deleteAttachment' => array(
                'args' => array('string'),
                'return' => 'int',
                'doc' => 'Delete a file from the wiki.'
            ), 'wiki.getAttachment' => array(
                'args' => array('string'),
                'doc' => 'Return a media file',
                'return' => 'file',
                'name' => 'getAttachment',
            ), 'wiki.getAttachmentInfo' => array(
                'args' => array('string'),
                'return' => 'array',
                'doc' => 'Returns a struct with info about the attachment.'
            ), 'dokuwiki.getXMLRPCAPIVersion' => array(
                'args' => array(),
                'name' => 'getAPIVersion',
                'return' => 'int',
                'doc' => 'Returns the XMLRPC API version.',
                'public' => '1',
            ), 'wiki.getRPCVersionSupported' => array(
                'args' => array(),
                'name' => 'wikiRpcVersion',
                'return' => 'int',
                'doc' => 'Returns 2 with the supported RPC API version.',
                'public' => '1'
            ),

        );
    }

    /**
     * @return string
     */
    public function getVersion()
    {
        return getVersion();
    }

    /**
     * @return int unix timestamp
     */
    public function getTime()
    {
        return time();
    }

    /**
     * Return a raw wiki page
     *
     * @param string $id wiki page id
     * @param int|string $rev revision timestamp of the page or empty string
     * @return string page text.
     * @throws AccessDeniedException if no permission for page
     */
    public function rawPage($id, $rev = '')
    {
        $id = $this->resolvePageId($id);
        if (auth_quickaclcheck($id) < AUTH_READ) {
            throw new AccessDeniedException('You are not allowed to read this file', 111);
        }
        $text = rawWiki($id, $rev);
        if (!$text) {
            return pageTemplate($id);
        } else {
            return $text;
        }
    }

    /**
     * Return a media file
     *
     * @author Gina Haeussge <osd@foosel.net>
     *
     * @param string $id file id
     * @return mixed media file
     * @throws AccessDeniedException no permission for media
     * @throws RemoteException not exist
     */
    public function getAttachment($id)
    {
        $id = cleanID($id);
        if (auth_quickaclcheck(getNS($id) . ':*') < AUTH_READ) {
            throw new AccessDeniedException('You are not allowed to read this file', 211);
        }

        $file = mediaFN($id);
        if (!@ file_exists($file)) {
            throw new RemoteException('The requested file does not exist', 221);
        }

        $data = io_readFile($file, false);
        return $this->api->toFile($data);
    }

    /**
     * Return info about a media file
     *
     * @author Gina Haeussge <osd@foosel.net>
     *
     * @param string $id page id
     * @return array
     */
    public function getAttachmentInfo($id)
    {
        $id = cleanID($id);
        $info = array(
            'lastModified' => $this->api->toDate(0),
            'size' => 0,
        );

        $file = mediaFN($id);
        if (auth_quickaclcheck(getNS($id) . ':*') >= AUTH_READ) {
            if (file_exists($file)) {
                $info['lastModified'] = $this->api->toDate(filemtime($file));
                $info['size'] = filesize($file);
            } else {
                //Is it deleted media with changelog?
                $medialog = new MediaChangeLog($id);
                $revisions = $medialog->getRevisions(0, 1);
                if (!empty($revisions)) {
                    $info['lastModified'] = $this->api->toDate($revisions[0]);
                }
            }
        }

        return $info;
    }

    /**
     * Return a wiki page rendered to html
     *
     * @param string $id page id
     * @param string|int $rev revision timestamp or empty string
     * @return null|string html
     * @throws AccessDeniedException no access to page
     */
    public function htmlPage($id, $rev = '')
    {
        $id = $this->resolvePageId($id);
        if (auth_quickaclcheck($id) < AUTH_READ) {
            throw new AccessDeniedException('You are not allowed to read this page', 111);
        }
        return p_wiki_xhtml($id, $rev, false);
    }

    /**
     * List all pages - we use the indexer list here
     *
     * @return array
     */
    public function listPages()
    {
        $list = array();
        $pages = idx_get_indexer()->getPages();
        $pages = array_filter(array_filter($pages, 'isVisiblePage'), 'page_exists');
        Sort::ksort($pages);

        foreach (array_keys($pages) as $idx) {
            $perm = auth_quickaclcheck($pages[$idx]);
            if ($perm < AUTH_READ) {
                continue;
            }
            $page = array();
            $page['id'] = trim($pages[$idx]);
            $page['perms'] = $perm;
            $page['size'] = @filesize(wikiFN($pages[$idx]));
            $page['lastModified'] = $this->api->toDate(@filemtime(wikiFN($pages[$idx])));
            $list[] = $page;
        }

        return $list;
    }

    /**
     * List all pages in the given namespace (and below)
     *
     * @param string $ns
     * @param array $opts
     *    $opts['depth']   recursion level, 0 for all
     *    $opts['hash']    do md5 sum of content?
     * @return array
     */
    public function readNamespace($ns, $opts = array())
    {
        global $conf;

        if (!is_array($opts)) $opts = array();

        $ns = cleanID($ns);
        $dir = utf8_encodeFN(str_replace(':', '/', $ns));
        $data = array();
        $opts['skipacl'] = 0; // no ACL skipping for XMLRPC
        search($data, $conf['datadir'], 'search_allpages', $opts, $dir);
        return $data;
    }

    /**
     * List all pages in the given namespace (and below)
     *
     * @param string $query
     * @return array
     */
    public function search($query)
    {
        $regex = array();
        $data = ft_pageSearch($query, $regex);
        $pages = array();

        // prepare additional data
        $idx = 0;
        foreach ($data as $id => $score) {
            $file = wikiFN($id);

            if ($idx < FT_SNIPPET_NUMBER) {
                $snippet = ft_snippet($id, $regex);
                $idx++;
            } else {
                $snippet = '';
            }

            $pages[] = array(
                'id' => $id,
                'score' => intval($score),
                'rev' => filemtime($file),
                'mtime' => filemtime($file),
                'size' => filesize($file),
                'snippet' => $snippet,
                'title' => useHeading('navigation') ? p_get_first_heading($id) : $id
            );
        }
        return $pages;
    }

    /**
     * Returns the wiki title.
     *
     * @return string
     */
    public function getTitle()
    {
        global $conf;
        return $conf['title'];
    }

    /**
     * List all media files.
     *
     * Available options are 'recursive' for also including the subnamespaces
     * in the listing, and 'pattern' for filtering the returned files against
     * a regular expression matching their name.
     *
     * @author Gina Haeussge <osd@foosel.net>
     *
     * @param string $ns
     * @param array $options
     *   $options['depth']     recursion level, 0 for all
     *   $options['showmsg']   shows message if invalid media id is used
     *   $options['pattern']   check given pattern
     *   $options['hash']      add hashes to result list
     * @return array
     * @throws AccessDeniedException no access to the media files
     */
    public function listAttachments($ns, $options = array())
    {
        global $conf;

        $ns = cleanID($ns);

        if (!is_array($options)) $options = array();
        $options['skipacl'] = 0; // no ACL skipping for XMLRPC

        if (auth_quickaclcheck($ns . ':*') >= AUTH_READ) {
            $dir = utf8_encodeFN(str_replace(':', '/', $ns));

            $data = array();
            search($data, $conf['mediadir'], 'search_media', $options, $dir);
            $len = count($data);
            if (!$len) return array();

            for ($i = 0; $i < $len; $i++) {
                unset($data[$i]['meta']);
                $data[$i]['perms'] = $data[$i]['perm'];
                unset($data[$i]['perm']);
                $data[$i]['lastModified'] = $this->api->toDate($data[$i]['mtime']);
            }
            return $data;
        } else {
            throw new AccessDeniedException('You are not allowed to list media files.', 215);
        }
    }

    /**
     * Return a list of backlinks
     *
     * @param string $id page id
     * @return array
     */
    public function listBackLinks($id)
    {
        return ft_backlinks($this->resolvePageId($id));
    }

    /**
     * Return some basic data about a page
     *
     * @param string $id page id
     * @param string|int $rev revision timestamp or empty string
     * @return array
     * @throws AccessDeniedException no access for page
     * @throws RemoteException page not exist
     */
    public function pageInfo($id, $rev = '')
    {
        $id = $this->resolvePageId($id);
        if (auth_quickaclcheck($id) < AUTH_READ) {
            throw new AccessDeniedException('You are not allowed to read this page', 111);
        }
        $file = wikiFN($id, $rev);
        $time = @filemtime($file);
        if (!$time) {
            throw new RemoteException('The requested page does not exist', 121);
        }

        // set revision to current version if empty, use revision otherwise
        // as the timestamps of old files are not necessarily correct
        if ($rev === '') {
            $rev = $time;
        }

        $pagelog = new PageChangeLog($id, 1024);
        $info = $pagelog->getRevisionInfo($rev);

        $data = array(
            'name' => $id,
            'lastModified' => $this->api->toDate($rev),
            'author' => is_array($info) ? (($info['user']) ? $info['user'] : $info['ip']) : null,
            'version' => $rev
        );

        return ($data);
    }

    /**
     * Save a wiki page
     *
     * @author Michael Klier <chi@chimeric.de>
     *
     * @param string $id page id
     * @param string $text wiki text
     * @param array $params parameters: summary, minor edit
     * @return bool
     * @throws AccessDeniedException no write access for page
     * @throws RemoteException no id, empty new page or locked
     */
    public function putPage($id, $text, $params = array())
    {
        global $TEXT;
        global $lang;

        $id = $this->resolvePageId($id);
        $TEXT = cleanText($text);
        $sum = $params['sum'];
        $minor = $params['minor'];

        if (empty($id)) {
            throw new RemoteException('Empty page ID', 131);
        }

        if (!page_exists($id) && trim($TEXT) == '') {
            throw new RemoteException('Refusing to write an empty new wiki page', 132);
        }

        if (auth_quickaclcheck($id) < AUTH_EDIT) {
            throw new AccessDeniedException('You are not allowed to edit this page', 112);
        }

        // Check, if page is locked
        if (checklock($id)) {
            throw new RemoteException('The page is currently locked', 133);
        }

        // SPAM check
        if (checkwordblock()) {
            throw new RemoteException('Positive wordblock check', 134);
        }

        // autoset summary on new pages
        if (!page_exists($id) && empty($sum)) {
            $sum = $lang['created'];
        }

        // autoset summary on deleted pages
        if (page_exists($id) && empty($TEXT) && empty($sum)) {
            $sum = $lang['deleted'];
        }

        lock($id);

        saveWikiText($id, $TEXT, $sum, $minor);

        unlock($id);

        // run the indexer if page wasn't indexed yet
        idx_addPage($id);

        return true;
    }

    /**
     * Appends text to a wiki page.
     *
     * @param string $id page id
     * @param string $text wiki text
     * @param array $params such as summary,minor
     * @return bool|string
     * @throws RemoteException
     */
    public function appendPage($id, $text, $params = array())
    {
        $currentpage = $this->rawPage($id);
        if (!is_string($currentpage)) {
            return $currentpage;
        }
        return $this->putPage($id, $currentpage . $text, $params);
    }

    /**
     * Remove one or more users from the list of registered users
     *
     * @param string[] $usernames List of usernames to remove
     *
     * @return bool
     *
     * @throws AccessDeniedException
     */
    public function deleteUsers($usernames)
    {
        if (!auth_isadmin()) {
            throw new AccessDeniedException('Only admins are allowed to delete users', 114);
        }
        /** @var \dokuwiki\Extension\AuthPlugin $auth */
        global $auth;
        return (bool)$auth->triggerUserMod('delete', array($usernames));
    }

    /**
     * Uploads a file to the wiki.
     *
     * Michael Klier <chi@chimeric.de>
     *
     * @param string $id page id
     * @param string $file
     * @param array $params such as overwrite
     * @return false|string
     * @throws RemoteException
     */
    public function putAttachment($id, $file, $params = array())
    {
        $id = cleanID($id);
        $auth = auth_quickaclcheck(getNS($id) . ':*');

        if (!isset($id)) {
            throw new RemoteException('Filename not given.', 231);
        }

        global $conf;

        $ftmp = $conf['tmpdir'] . '/' . md5($id . clientIP());

        // save temporary file
        @unlink($ftmp);
        io_saveFile($ftmp, $file);

        $res = media_save(array('name' => $ftmp), $id, $params['ow'], $auth, 'rename');
        if (is_array($res)) {
            throw new RemoteException($res[0], -$res[1]);
        } else {
            return $res;
        }
    }

    /**
     * Deletes a file from the wiki.
     *
     * @author Gina Haeussge <osd@foosel.net>
     *
     * @param string $id page id
     * @return int
     * @throws AccessDeniedException no permissions
     * @throws RemoteException file in use or not deleted
     */
    public function deleteAttachment($id)
    {
        $id = cleanID($id);
        $auth = auth_quickaclcheck(getNS($id) . ':*');
        $res = media_delete($id, $auth);
        if ($res & DOKU_MEDIA_DELETED) {
            return 0;
        } elseif ($res & DOKU_MEDIA_NOT_AUTH) {
            throw new AccessDeniedException('You don\'t have permissions to delete files.', 212);
        } elseif ($res & DOKU_MEDIA_INUSE) {
            throw new RemoteException('File is still referenced', 232);
        } else {
            throw new RemoteException('Could not delete file', 233);
        }
    }

    /**
     * Returns the permissions of a given wiki page for the current user or another user
     *
     * @param string $id page id
     * @param string|null $user username
     * @param array|null $groups array of groups
     * @return int permission level
     */
    public function aclCheck($id, $user = null, $groups = null)
    {
        /** @var \dokuwiki\Extension\AuthPlugin $auth */
        global $auth;

        $id = $this->resolvePageId($id);
        if ($user === null) {
            return auth_quickaclcheck($id);
        } else {
            if ($groups === null) {
                $userinfo = $auth->getUserData($user);
                if ($userinfo === false) {
                    $groups = array();
                } else {
                    $groups = $userinfo['grps'];
                }
            }
            return auth_aclcheck($id, $user, $groups);
        }
    }

    /**
     * Lists all links contained in a wiki page
     *
     * @author Michael Klier <chi@chimeric.de>
     *
     * @param string $id page id
     * @return array
     * @throws AccessDeniedException  no read access for page
     */
    public function listLinks($id)
    {
        $id = $this->resolvePageId($id);
        if (auth_quickaclcheck($id) < AUTH_READ) {
            throw new AccessDeniedException('You are not allowed to read this page', 111);
        }
        $links = array();

        // resolve page instructions
        $ins = p_cached_instructions(wikiFN($id));

        // instantiate new Renderer - needed for interwiki links
        $Renderer = new Doku_Renderer_xhtml();
        $Renderer->interwiki = getInterwiki();

        // parse parse instructions
        foreach ($ins as $in) {
            $link = array();
            switch ($in[0]) {
                case 'internallink':
                    $link['type'] = 'local';
                    $link['page'] = $in[1][0];
                    $link['href'] = wl($in[1][0]);
                    array_push($links, $link);
                    break;
                case 'externallink':
                    $link['type'] = 'extern';
                    $link['page'] = $in[1][0];
                    $link['href'] = $in[1][0];
                    array_push($links, $link);
                    break;
                case 'interwikilink':
                    $url = $Renderer->_resolveInterWiki($in[1][2], $in[1][3]);
                    $link['type'] = 'extern';
                    $link['page'] = $url;
                    $link['href'] = $url;
                    array_push($links, $link);
                    break;
            }
        }

        return ($links);
    }

    /**
     * Returns a list of recent changes since give timestamp
     *
     * @author Michael Hamann <michael@content-space.de>
     * @author Michael Klier <chi@chimeric.de>
     *
     * @param int $timestamp unix timestamp
     * @return array
     * @throws RemoteException no valid timestamp
     */
    public function getRecentChanges($timestamp)
    {
        if (strlen($timestamp) != 10) {
            throw new RemoteException('The provided value is not a valid timestamp', 311);
        }

        $recents = getRecentsSince($timestamp);

        $changes = array();

        foreach ($recents as $recent) {
            $change = array();
            $change['name'] = $recent['id'];
            $change['lastModified'] = $this->api->toDate($recent['date']);
            $change['author'] = $recent['user'];
            $change['version'] = $recent['date'];
            $change['perms'] = $recent['perms'];
            $change['size'] = @filesize(wikiFN($recent['id']));
            array_push($changes, $change);
        }

        if (!empty($changes)) {
            return $changes;
        } else {
            // in case we still have nothing at this point
            throw new RemoteException('There are no changes in the specified timeframe', 321);
        }
    }

    /**
     * Returns a list of recent media changes since give timestamp
     *
     * @author Michael Hamann <michael@content-space.de>
     * @author Michael Klier <chi@chimeric.de>
     *
     * @param int $timestamp unix timestamp
     * @return array
     * @throws RemoteException no valid timestamp
     */
    public function getRecentMediaChanges($timestamp)
    {
        if (strlen($timestamp) != 10)
            throw new RemoteException('The provided value is not a valid timestamp', 311);

        $recents = getRecentsSince($timestamp, null, '', RECENTS_MEDIA_CHANGES);

        $changes = array();

        foreach ($recents as $recent) {
            $change = array();
            $change['name'] = $recent['id'];
            $change['lastModified'] = $this->api->toDate($recent['date']);
            $change['author'] = $recent['user'];
            $change['version'] = $recent['date'];
            $change['perms'] = $recent['perms'];
            $change['size'] = @filesize(mediaFN($recent['id']));
            array_push($changes, $change);
        }

        if (!empty($changes)) {
            return $changes;
        } else {
            // in case we still have nothing at this point
            throw new RemoteException('There are no changes in the specified timeframe', 321);
        }
    }

    /**
     * Returns a list of available revisions of a given wiki page
     * Number of returned pages is set by $conf['recent']
     * However not accessible pages are skipped, so less than $conf['recent'] could be returned
     *
     * @author Michael Klier <chi@chimeric.de>
     *
     * @param string $id page id
     * @param int $first skip the first n changelog lines
     *                      0 = from current(if exists)
     *                      1 = from 1st old rev
     *                      2 = from 2nd old rev, etc
     * @return array
     * @throws AccessDeniedException no read access for page
     * @throws RemoteException empty id
     */
    public function pageVersions($id, $first = 0)
    {
        $id = $this->resolvePageId($id);
        if (auth_quickaclcheck($id) < AUTH_READ) {
            throw new AccessDeniedException('You are not allowed to read this page', 111);
        }
        global $conf;

        $versions = array();

        if (empty($id)) {
            throw new RemoteException('Empty page ID', 131);
        }

        $first = (int) $first;
        $first_rev = $first - 1;
        $first_rev = $first_rev < 0 ? 0 : $first_rev;
        $pagelog = new PageChangeLog($id);
        $revisions = $pagelog->getRevisions($first_rev, $conf['recent']);

        if ($first == 0) {
            array_unshift($revisions, '');  // include current revision
            if (count($revisions) > $conf['recent']) {
                array_pop($revisions);          // remove extra log entry
            }
        }

        if (!empty($revisions)) {
            foreach ($revisions as $rev) {
                $file = wikiFN($id, $rev);
                $time = @filemtime($file);
                // we check if the page actually exists, if this is not the
                // case this can lead to less pages being returned than
                // specified via $conf['recent']
                if ($time) {
                    $pagelog->setChunkSize(1024);
                    $info = $pagelog->getRevisionInfo($rev ? $rev : $time);
                    if (!empty($info)) {
                        $data = array();
                        $data['user'] = $info['user'];
                        $data['ip'] = $info['ip'];
                        $data['type'] = $info['type'];
                        $data['sum'] = $info['sum'];
                        $data['modified'] = $this->api->toDate($info['date']);
                        $data['version'] = $info['date'];
                        array_push($versions, $data);
                    }
                }
            }
            return $versions;
        } else {
            return array();
        }
    }

    /**
     * The version of Wiki RPC API supported
     */
    public function wikiRpcVersion()
    {
        return 2;
    }

    /**
     * Locks or unlocks a given batch of pages
     *
     * Give an associative array with two keys: lock and unlock. Both should contain a
     * list of pages to lock or unlock
     *
     * Returns an associative array with the keys locked, lockfail, unlocked and
     * unlockfail, each containing lists of pages.
     *
     * @param array[] $set list pages with array('lock' => array, 'unlock' => array)
     * @return array
     */
    public function setLocks($set)
    {
        $locked = array();
        $lockfail = array();
        $unlocked = array();
        $unlockfail = array();

        foreach ((array) $set['lock'] as $id) {
            $id = $this->resolvePageId($id);
            if (auth_quickaclcheck($id) < AUTH_EDIT || checklock($id)) {
                $lockfail[] = $id;
            } else {
                lock($id);
                $locked[] = $id;
            }
        }

        foreach ((array) $set['unlock'] as $id) {
            $id = $this->resolvePageId($id);
            if (auth_quickaclcheck($id) < AUTH_EDIT || !unlock($id)) {
                $unlockfail[] = $id;
            } else {
                $unlocked[] = $id;
            }
        }

        return array(
            'locked' => $locked,
            'lockfail' => $lockfail,
            'unlocked' => $unlocked,
            'unlockfail' => $unlockfail,
        );
    }

    /**
     * Return API version
     *
     * @return int
     */
    public function getAPIVersion()
    {
        return self::API_VERSION;
    }

    /**
     * Login
     *
     * @param string $user
     * @param string $pass
     * @return int
     */
    public function login($user, $pass)
    {
        global $conf;
        /** @var \dokuwiki\Extension\AuthPlugin $auth */
        global $auth;

        if (!$conf['useacl']) return 0;
        if (!$auth) return 0;

        @session_start(); // reopen session for login
        $ok = null;
        if ($auth->canDo('external')) {
            $ok = $auth->trustExternal($user, $pass, false);
        }
        if ($ok === null){
            $evdata = array(
                'user' => $user,
                'password' => $pass,
                'sticky' => false,
                'silent' => true,
            );
            $ok = Event::createAndTrigger('AUTH_LOGIN_CHECK', $evdata, 'auth_login_wrapper');
        }
        session_write_close(); // we're done with the session

        return $ok;
    }

    /**
     * Log off
     *
     * @return int
     */
    public function logoff()
    {
        global $conf;
        global $auth;
        if (!$conf['useacl']) return 0;
        if (!$auth) return 0;

        auth_logoff();

        return 1;
    }

    /**
     * Resolve page id
     *
     * @param string $id page id
     * @return string
     */
    private function resolvePageId($id)
    {
        $id = cleanID($id);
        if (empty($id)) {
            global $conf;
            $id = cleanID($conf['start']);
        }
        return $id;
    }
}
