<?php
/**
 * This is DokuWiki's Main Configuration file
 *
 * All the default values are kept here, you should not modify it but use
 * a local.php file instead to override the settings from here.
 *
 * This is a piece of PHP code so PHP syntax applies!
 *
 * For help with the configuration and a more detailed explanation of the various options
 * see https://www.dokuwiki.org/config
 */


/* Basic Settings */
$conf['title']       = 'DokuWiki';        //what to show in the title
$conf['start']       = 'start';           //name of start page
$conf['lang']        = 'en';              //your language
$conf['template']    = 'dokuwiki';         //see lib/tpl directory
$conf['tagline']     = '';                //tagline in header (if template supports it)
$conf['sidebar']     = 'sidebar';         //name of sidebar in root namespace (if template supports it)
$conf['license']     = 'cc-by-nc-sa';     //see conf/license.php
$conf['savedir']     = './data';          //where to store all the files
$conf['basedir']     = '';                //absolute dir from serveroot - blank for autodetection
$conf['baseurl']     = '';                //URL to server including protocol - blank for autodetect
$conf['cookiedir']   = '';                //path to use in cookies - blank for basedir
$conf['dmode']       = 0755;              //set directory creation mode
$conf['fmode']       = 0644;              //set file creation mode
$conf['allowdebug']  = 0;                 //allow debug output, enable if needed 0|1

/* Display Settings */
$conf['recent']      = 20;                //how many entries to show in recent
$conf['recent_days'] = 7;                 //How many days of recent changes to keep. (days)
$conf['breadcrumbs'] = 10;                //how many recent visited pages to show
$conf['youarehere']  = 0;                 //show "You are here" navigation? 0|1
$conf['fullpath']    = 0;                 //show full path of the document or relative to datadir only? 0|1
$conf['typography']  = 1;                 //smartquote conversion 0=off, 1=doublequotes, 2=all quotes
$conf['dformat']     = '%Y/%m/%d %H:%M';  //dateformat accepted by PHPs strftime() function
$conf['signature']   = ' --- //[[@MAIL@|@NAME@]] @DATE@//'; //signature see wiki page for details
$conf['showuseras']  = 'loginname';       // 'loginname' users login name
                                          // 'username' users full name
                                          // 'email' e-mail address (will be obfuscated as per mailguard)
                                          // 'email_link' e-mail address as a mailto: link (obfuscated)
$conf['toptoclevel'] = 1;                 //Level starting with and below to include in AutoTOC (max. 5)
$conf['tocminheads'] = 3;                 //Minimum amount of headlines that determines if a TOC is built
$conf['maxtoclevel'] = 3;                 //Up to which level include into AutoTOC (max. 5)
$conf['maxseclevel'] = 3;                 //Up to which level create editable sections (max. 5)
$conf['camelcase']   = 0;                 //Use CamelCase for linking? (I don't like it) 0|1
$conf['deaccent']    = 1;                 //deaccented chars in pagenames (1) or romanize (2) or keep (0)?
$conf['useheading']  = 0;                 //use the first heading in a page as its name
$conf['sneaky_index']= 0;                 //check for namespace read permission in index view (0|1) (1 might cause unexpected behavior)
$conf['hidepages']   = '';                //Regexp for pages to be skipped from RSS, Search and Recent Changes

/* Authentication Settings */
$conf['useacl']      = 0;                //Use Access Control Lists to restrict access?
$conf['autopasswd']  = 1;                //autogenerate passwords and email them to user
$conf['authtype']    = 'authplain';      //which authentication backend should be used
$conf['passcrypt']   = 'bcrypt';           //Used crypt method (smd5,md5,sha1,ssha,crypt,mysql,my411,bcrypt)
$conf['defaultgroup']= 'user';           //Default groups new Users are added to
$conf['superuser']   = '!!not set!!';    //The admin can be user or @group or comma separated list user1,@group1,user2
$conf['manager']     = '!!not set!!';    //The manager can be user or @group or comma separated list user1,@group1,user2
$conf['profileconfirm'] = 1;             //Require current password to confirm changes to user profile
$conf['rememberme'] = 1;                 //Enable/disable remember me on login
$conf['disableactions'] = '';            //comma separated list of actions to disable
$conf['auth_security_timeout'] = 900;    //time (seconds) auth data is considered valid, set to 0 to recheck on every page view
$conf['securecookie'] = 1;               //never send HTTPS cookies via HTTP
$conf['remote']      = 0;                //Enable/disable remote interfaces
$conf['remoteuser']  = '!!not set!!';    //user/groups that have access to remote interface (comma separated). leave empty to allow all users

/* Antispam Features */
$conf['usewordblock']= 1;                //block spam based on words? 0|1
$conf['relnofollow'] = 1;                //use rel="ugc nofollow" for external links?
$conf['indexdelay']  = 60*60*24*5;       //allow indexing after this time (seconds) default is 5 days
$conf['mailguard']   = 'hex';            //obfuscate email addresses against spam harvesters?
                                         //valid entries are:
                                         //  'visible' - replace @ with [at], . with [dot] and - with [dash]
                                         //  'hex'     - use hex entities to encode the mail address
                                         //  'none'    - do not obfuscate addresses
$conf['iexssprotect']= 1;                // check for JavaScript and HTML in uploaded files 0|1

/* Editing Settings */
$conf['usedraft']    = 1;                //automatically save a draft while editing (0|1)
$conf['htmlok']      = 0;                //may raw HTML be embedded? This may break layout and XHTML validity 0|1
$conf['phpok']       = 0;                //may PHP code be embedded? Never do this on the internet! 0|1
$conf['locktime']    = 15*60;            //maximum age for lockfiles (defaults to 15 minutes)
$conf['cachetime']   = 60*60*24;         //maximum age for cachefile in seconds (defaults to a day)

/* Link Settings */
// Set target to use when creating links - leave empty for same window
$conf['target']['wiki']      = '';
$conf['target']['interwiki'] = '';
$conf['target']['extern']    = '';
$conf['target']['media']     = '';
$conf['target']['windows']   = '';

/* Media Settings */
$conf['mediarevisions'] = 1;             //enable/disable media revisions
$conf['refcheck']    = 1;                //check for references before deleting media files
$conf['gdlib']       = 2;                //the GDlib version (0, 1 or 2) 2 tries to autodetect
$conf['im_convert']  = '';               //path to ImageMagicks convert (will be used instead of GD)
$conf['jpg_quality'] = '70';             //quality of compression when scaling jpg images (0-100)
$conf['fetchsize']   = 0;                //maximum size (bytes) fetch.php may download from extern, disabled by default
$conf['video_settings'] = 'controls';            //comma separated list of video embed attributes

/* Notification Settings */
$conf['subscribers'] = 0;                //enable change notice subscription support
$conf['subscribe_time'] = 24*60*60;      //Time after which digests / lists are sent (in sec, default 1 day)
                                         //Should be smaller than the time specified in recent_days
$conf['notify']      = '';               //send change info to this email (leave blank for nobody)
$conf['registernotify'] = '';            //send info about newly registered users to this email (leave blank for nobody)
$conf['mailfrom']    = '';               //use this email when sending mails
$conf['mailreturnpath']    = '';         //use this email as returnpath for bounce mails
$conf['mailprefix']  = '';               //use this as prefix of outgoing mails
$conf['htmlmail']    = 1;                //send HTML multipart mails
$conf['dontlog'] = 'debug';              //logging facilites that should be disabled

/* Syndication Settings */
$conf['sitemap']     = 0;                //Create a google sitemap? How often? In days.
$conf['rss_type']    = 'rss1';           //type of RSS feed to provide, by default:
                                         //  'rss'  - RSS 0.91
                                         //  'rss1' - RSS 1.0
                                         //  'rss2' - RSS 2.0
                                         //  'atom' - Atom 0.3
                                         //  'atom1' - Atom 1.0
$conf['rss_linkto'] = 'diff';            //what page RSS entries link to:
                                         //  'diff'    - page showing revision differences
                                         //  'page'    - the revised page itself
                                         //  'rev'     - page showing all revisions
                                         //  'current' - most recent revision of page
$conf['rss_content'] = 'abstract';       //what to put in the items by default?
                                         //  'abstract' - plain text, first paragraph or so
                                         //  'diff'     - plain text unified diff wrapped in <pre> tags
                                         //  'htmldiff' - diff as HTML table
                                         //  'html'     - the full page rendered in XHTML
$conf['rss_media']   = 'both';           //what should be listed?
                                         //  'both'     - page and media changes
                                         //  'pages'    - page changes only
                                         //  'media'    - media changes only
$conf['rss_update'] = 5*60;              //Update the RSS feed every n seconds (defaults to 5 minutes)
$conf['rss_show_summary'] = 1;           //Add revision summary to title? 0|1
$conf['rss_show_deleted'] = 1;           //Show deleted items 0|1

/* Advanced Settings */
$conf['updatecheck'] = 1;                //automatically check for new releases?
$conf['userewrite']  = 0;                //this makes nice URLs: 0: off 1: .htaccess 2: internal
$conf['useslash']    = 0;                //use slash instead of colon? only when rewrite is on
$conf['sepchar']     = '_';              //word separator character in page names; may be a
                                         //  letter, a digit, '_', '-', or '.'.
$conf['canonical']   = 0;                //Should all URLs use full canonical http://... style?
$conf['fnencode']    = 'url';            //encode filenames (url|safe|utf-8)
$conf['autoplural']  = 0;                //try (non)plural form of nonexisting files?
$conf['compression'] = 'gz';             //compress old revisions: (0: off) ('gz': gnuzip) ('bz2': bzip)
                                         //  bz2 generates smaller files, but needs more cpu-power
$conf['gzip_output'] = 0;                //use gzip content encodeing for the output xhtml (if allowed by browser)
$conf['compress']    = 1;                //Strip whitespaces and comments from Styles and JavaScript? 1|0
$conf['cssdatauri']  = 512;              //Maximum byte size of small images to embed into CSS, won't work on IE<8
$conf['send404']     = 0;                //Send a HTTP 404 status for non existing pages?
$conf['broken_iua']  = 0;                //Platform with broken ignore_user_abort (IIS+CGI) 0|1
$conf['xsendfile']   = 0;                //Use X-Sendfile (1 = lighttpd, 2 = standard)
$conf['renderer_xhtml'] = 'xhtml';       //renderer to use for main page generation
$conf['readdircache'] = 0;               //time cache in second for the readdir operation, 0 to deactivate.
$conf['search_nslimit'] = 0;             //limit the search to the current X namespaces
$conf['search_fragment'] = 'exact';      //specify the default fragment search behavior
$conf['trustedproxy'] = '^(::1|[fF][eE]80:|127\.|10\.|192\.168\.|172\.((1[6-9])|(2[0-9])|(3[0-1]))\.)';
                                         //Regexp of trusted proxy address when reading IP using HTTP header
                                         //  if blank, do not trust any proxy (including local IP)

/* Feature Flags */
$conf['defer_js'] = 1;                   // Defer javascript to be executed after the page's HTML has been parsed. Setting will be removed in the next release.

/* Network Settings */
$conf['dnslookups'] = 1;                 //disable to disallow IP to hostname lookups
$conf['jquerycdn']  = 0;                 //use a CDN for delivering jQuery?
// Proxy setup - if your Server needs a proxy to access the web set these
$conf['proxy']['host']    = '';
$conf['proxy']['port']    = '';
$conf['proxy']['user']    = '';
$conf['proxy']['pass']    = '';
$conf['proxy']['ssl']     = 0;
$conf['proxy']['except']  = '';
