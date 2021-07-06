<?php
/**
 * english language file
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Christopher Smith <chris@jalakai.co.uk>
 * @author     Matthias Schulte <dokuwiki@lupo49.de>
 * @author     Schplurtz le Déboulonné <Schplurtz@laposte.net>
 */

// for admin plugins, the menu prompt to be displayed in the admin menu
// if set here, the plugin doesn't need to override the getMenuText() method
$lang['menu']       = 'Configuration Settings';

$lang['error']      = 'Settings not updated due to an invalid value, please review your changes and resubmit.
                       <br />The incorrect value(s) will be shown surrounded by a red border.';
$lang['updated']    = 'Settings updated successfully.';
$lang['nochoice']   = '(no other choices available)';
$lang['locked']     = 'The settings file can not be updated, if this is unintentional, <br />
                       ensure the local settings file name and permissions are correct.';

$lang['danger']     = 'Danger: Changing this option could make your wiki and the configuration menu inaccessible.';
$lang['warning']    = 'Warning: Changing this option could cause unintended behaviour.';
$lang['security']   = 'Security Warning: Changing this option could present a security risk.';

/* --- Config Setting Headers --- */
$lang['_configuration_manager'] = 'Configuration Manager'; //same as heading in intro.txt
$lang['_header_dokuwiki'] = 'DokuWiki';
$lang['_header_plugin'] = 'Plugin';
$lang['_header_template'] = 'Template';
$lang['_header_undefined'] = 'Undefined Settings';

/* --- Config Setting Groups --- */
$lang['_basic'] = 'Basic';
$lang['_display'] = 'Display';
$lang['_authentication'] = 'Authentication';
$lang['_anti_spam'] = 'Anti-Spam';
$lang['_editing'] = 'Editing';
$lang['_links'] = 'Links';
$lang['_media'] = 'Media';
$lang['_notifications'] = 'Notification';
$lang['_syndication']   = 'Syndication (RSS)';
$lang['_advanced'] = 'Advanced';
$lang['_network'] = 'Network';

/* --- Undefined Setting Messages --- */
$lang['_msg_setting_undefined'] = 'No setting metadata.';
$lang['_msg_setting_no_class'] = 'No setting class.';
$lang['_msg_setting_no_known_class'] = 'Setting class not available.';
$lang['_msg_setting_no_default'] = 'No default value.';

/* -------------------- Config Options --------------------------- */

/* Basic Settings */
$lang['title']       = 'Wiki title aka. your wiki\'s name';
$lang['start']       = 'Page name to use as the starting point for each namespace';
$lang['lang']        = 'Interface language';
$lang['template']    = 'Template aka. the design of the wiki.';
$lang['tagline']     = 'Tagline (if template supports it)';
$lang['sidebar']     = 'Sidebar page name (if template supports it), empty field disables the sidebar';
$lang['license']     = 'Under which license should your content be released?';
$lang['savedir']     = 'Directory for saving data';
$lang['basedir']     = 'Server path (eg. <code>/dokuwiki/</code>). Leave blank for autodetection.';
$lang['baseurl']     = 'Server URL (eg. <code>http://www.yourserver.com</code>). Leave blank for autodetection.';
$lang['cookiedir']   = 'Cookie path. Leave blank for using baseurl.';
$lang['dmode']       = 'Directory creation mode';
$lang['fmode']       = 'File creation mode';
$lang['allowdebug']  = 'Allow debug. <b>Disable if not needed!</b>';

/* Display Settings */
$lang['recent']      = 'Number of entries per page in the recent changes';
$lang['recent_days'] = 'How many recent changes to keep (days)';
$lang['breadcrumbs'] = 'Number of "trace" breadcrumbs. Set to 0 to disable.';
$lang['youarehere']  = 'Use hierarchical breadcrumbs (you probably want to disable the above option then)';
$lang['fullpath']    = 'Reveal full path of pages in the footer';
$lang['typography']  = 'Do typographical replacements';
$lang['dformat']     = 'Date format (see PHP\'s <a href="http://php.net/strftime">strftime</a> function)';
$lang['signature']   = 'What to insert with the signature button in the editor';
$lang['showuseras']  = 'What to display when showing the user that last edited a page';
$lang['toptoclevel'] = 'Top level for table of contents';
$lang['tocminheads'] = 'Minimum amount of headlines that determines whether the TOC is built';
$lang['maxtoclevel'] = 'Maximum level for table of contents';
$lang['maxseclevel'] = 'Maximum section edit level';
$lang['camelcase']   = 'Use CamelCase for links';
$lang['deaccent']    = 'How to clean pagenames';
$lang['useheading']  = 'Use first heading for pagenames';
$lang['sneaky_index'] = 'By default, DokuWiki will show all namespaces in the sitemap. Enabling this option will hide those where the user doesn\'t have read permissions. This might result in hiding of accessable subnamespaces which may make the index unusable with certain ACL setups.';
$lang['hidepages']   = 'Hide pages matching this regular expression from search, the sitemap and other automatic indexes';

/* Authentication Settings */
$lang['useacl']      = 'Use access control lists';
$lang['autopasswd']  = 'Autogenerate passwords';
$lang['authtype']    = 'Authentication backend';
$lang['passcrypt']   = 'Password encryption method';
$lang['defaultgroup']= 'Default group, all new users will be placed in this group';
$lang['superuser']   = 'Superuser - group, user or comma separated list user1,@group1,user2 with full access to all pages and functions regardless of the ACL settings';
$lang['manager']     = 'Manager - group, user or comma separated list user1,@group1,user2 with access to certain management functions';
$lang['profileconfirm'] = 'Confirm profile changes with password';
$lang['rememberme'] = 'Allow permanent login cookies (remember me)';
$lang['disableactions'] = 'Disable DokuWiki actions';
$lang['disableactions_check'] = 'Check';
$lang['disableactions_subscription'] = 'Subscribe/Unsubscribe';
$lang['disableactions_wikicode'] = 'View source/Export Raw';
$lang['disableactions_profile_delete'] = 'Delete Own Account';
$lang['disableactions_other'] = 'Other actions (comma separated)';
$lang['disableactions_rss'] = 'XML Syndication (RSS)';
$lang['auth_security_timeout'] = 'Authentication Security Timeout (seconds)';
$lang['securecookie'] = 'Should cookies set via HTTPS only be sent via HTTPS by the browser? Disable this option when only the login of your wiki is secured with SSL but browsing the wiki is done unsecured.';
$lang['remote']      = 'Enable the remote API system. This allows other applications to access the wiki via XML-RPC or other mechanisms.';
$lang['remoteuser']  = 'Restrict remote API access to the comma separated groups or users given here. Leave empty to give access to everyone.';

/* Anti-Spam Settings */
$lang['usewordblock']= 'Block spam based on wordlist';
$lang['relnofollow'] = 'Use rel="ugc nofollow" on external links';
$lang['indexdelay']  = 'Time delay before indexing (sec)';
$lang['mailguard']   = 'Obfuscate email addresses';
$lang['iexssprotect']= 'Check uploaded files for possibly malicious JavaScript or HTML code';

/* Editing Settings */
$lang['usedraft']    = 'Automatically save a draft while editing';
$lang['htmlok']      = 'Allow embedded HTML';
$lang['phpok']       = 'Allow embedded PHP';
$lang['locktime']    = 'Maximum age for lock files (sec)';
$lang['cachetime']   = 'Maximum age for cache (sec)';

/* Link settings */
$lang['target____wiki']      = 'Target window for internal links';
$lang['target____interwiki'] = 'Target window for interwiki links';
$lang['target____extern']    = 'Target window for external links';
$lang['target____media']     = 'Target window for media links';
$lang['target____windows']   = 'Target window for windows links';

/* Media Settings */
$lang['mediarevisions'] = 'Enable Mediarevisions?';
$lang['refcheck']    = 'Check if a media file is still in use before deleting it';
$lang['gdlib']       = 'GD Lib version';
$lang['im_convert']  = 'Path to ImageMagick\'s convert tool';
$lang['jpg_quality'] = 'JPG compression quality (0-100)';
$lang['fetchsize']   = 'Maximum size (bytes) fetch.php may download from external URLs, eg. to cache and resize external images.';
$lang['video_settings'] = 'Select default attributes for video embeds';

/* Notification Settings */
$lang['subscribers'] = 'Allow users to subscribe to page changes by email';
$lang['subscribe_time'] = 'Time after which subscription lists and digests are sent (sec); This should be smaller than the time specified in recent_days.';
$lang['notify']      = 'Always send change notifications to this email address';
$lang['registernotify'] = 'Always send info on newly registered users to this email address';
$lang['mailfrom']    = 'Sender email address to use for automatic mails';
$lang['mailreturnpath']    = 'Recipient email address for non delivery notifications';
$lang['mailprefix']  = 'Email subject prefix to use for automatic mails. Leave blank to use the wiki title';
$lang['htmlmail']    = 'Send better looking, but larger in size HTML multipart emails. Disable for plain text only mails.';
$lang['dontlog'] = 'Disable logging for these types of logs.';

/* Syndication Settings */
$lang['sitemap']           = 'Generate Google sitemap this often (in days). 0 to disable';
$lang['rss_type']          = 'XML feed type';
$lang['rss_linkto']        = 'XML feed links to';
$lang['rss_content']       = 'What to display in the XML feed items?';
$lang['rss_update']        = 'XML feed update interval (sec)';
$lang['rss_show_summary']  = 'XML feed show summary in title';
$lang['rss_show_deleted']  = 'XML feed Show deleted feeds';
$lang['rss_media']         = 'What kind of changes should be listed in the XML feed?';
$lang['rss_media_o_both']  = 'both';
$lang['rss_media_o_pages'] = 'pages';
$lang['rss_media_o_media'] = 'media';


/* Advanced Options */
$lang['updatecheck'] = 'Check for updates and security warnings? DokuWiki needs to contact update.dokuwiki.org for this feature.';
$lang['userewrite']  = 'Use nice URLs';
$lang['useslash']    = 'Use slash as namespace separator in URLs';
$lang['sepchar']     = 'Page name word separator';
$lang['canonical']   = 'Use fully canonical URLs';
$lang['fnencode']    = 'Method for encoding non-ASCII filenames.';
$lang['autoplural']  = 'Check for plural forms in links';
$lang['compression'] = 'Compression method for attic files';
$lang['gzip_output'] = 'Use gzip Content-Encoding for xhtml';
$lang['compress']    = 'Compact CSS and javascript output';
$lang['cssdatauri']  = 'Size in bytes up to which images referenced in CSS files should be embedded right into the stylesheet to reduce HTTP request header overhead. <code>400</code> to <code>600</code> bytes is a good value. Set <code>0</code> to disable.';
$lang['send404']     = 'Send "HTTP 404/Page Not Found" for non existing pages';
$lang['broken_iua']  = 'Is the ignore_user_abort function broken on your system? This could cause a non working search index. IIS+PHP/CGI is known to be broken. See <a href="http://bugs.dokuwiki.org/?do=details&amp;task_id=852">Bug 852</a> for more info.';
$lang['xsendfile']   = 'Use the X-Sendfile header to let the webserver deliver static files? Your webserver needs to support this.';
$lang['renderer_xhtml']   = 'Renderer to use for main (xhtml) wiki output';
$lang['renderer__core']   = '%s (dokuwiki core)';
$lang['renderer__plugin'] = '%s (plugin)';
$lang['search_nslimit'] = 'Limit the search to the current X namespaces. When a search is executed from a page within a deeper namespace, the first X namespaces will be added as filter';
$lang['search_fragment'] = 'Specify the default fragment search behavior';
$lang['search_fragment_o_exact'] = 'exact';
$lang['search_fragment_o_starts_with'] = 'starts with';
$lang['search_fragment_o_ends_with'] = 'ends with';
$lang['search_fragment_o_contains'] = 'contains';
$lang['trustedproxy'] = 'Trust forwarding proxies matching this regular expression about the true client IP they report. The default matches local networks. Leave empty to trust no proxy.';

$lang['_feature_flags'] = 'Feature Flags';
$lang['defer_js'] = 'Defer javascript to be execute after the page\'s HTML has been parsed. Improves perceived page speed but could break a small number of plugins.';

/* Network Options */
$lang['dnslookups'] = 'DokuWiki will lookup hostnames for remote IP addresses of users editing pages. If you have a slow or non working DNS server or don\'t want this feature, disable this option';
$lang['jquerycdn'] = 'Should the jQuery and jQuery UI script files be loaded from a CDN? This adds additional HTTP requests, but files may load faster and users may have them cached already.';

/* jQuery CDN options */
$lang['jquerycdn_o_0'] = 'No CDN, local delivery only';
$lang['jquerycdn_o_jquery'] = 'CDN at code.jquery.com';
$lang['jquerycdn_o_cdnjs'] = 'CDN at cdnjs.com';

/* Proxy Options */
$lang['proxy____host']    = 'Proxy servername';
$lang['proxy____port']    = 'Proxy port';
$lang['proxy____user']    = 'Proxy user name';
$lang['proxy____pass']    = 'Proxy password';
$lang['proxy____ssl']     = 'Use SSL to connect to proxy';
$lang['proxy____except']  = 'Regular expression to match URLs for which the proxy should be skipped.';

/* License Options */
$lang['license_o_'] = 'None chosen';

/* typography options */
$lang['typography_o_0'] = 'none';
$lang['typography_o_1'] = 'excluding single quotes';
$lang['typography_o_2'] = 'including single quotes (might not always work)';

/* userewrite options */
$lang['userewrite_o_0'] = 'none';
$lang['userewrite_o_1'] = '.htaccess';
$lang['userewrite_o_2'] = 'DokuWiki internal';

/* deaccent options */
$lang['deaccent_o_0'] = 'off';
$lang['deaccent_o_1'] = 'remove accents';
$lang['deaccent_o_2'] = 'romanize';

/* gdlib options */
$lang['gdlib_o_0'] = 'GD Lib not available';
$lang['gdlib_o_1'] = 'Version 1.x';
$lang['gdlib_o_2'] = 'Autodetection';

/* rss_type options */
$lang['rss_type_o_rss']   = 'RSS 0.91';
$lang['rss_type_o_rss1']  = 'RSS 1.0';
$lang['rss_type_o_rss2']  = 'RSS 2.0';
$lang['rss_type_o_atom']  = 'Atom 0.3';
$lang['rss_type_o_atom1'] = 'Atom 1.0';

/* rss_content options */
$lang['rss_content_o_abstract'] = 'Abstract';
$lang['rss_content_o_diff']     = 'Unified Diff';
$lang['rss_content_o_htmldiff'] = 'HTML formatted diff table';
$lang['rss_content_o_html']     = 'Full HTML page content';

/* rss_linkto options */
$lang['rss_linkto_o_diff']    = 'difference view';
$lang['rss_linkto_o_page']    = 'the revised page';
$lang['rss_linkto_o_rev']     = 'list of revisions';
$lang['rss_linkto_o_current'] = 'the current page';

/* compression options */
$lang['compression_o_0']   = 'none';
$lang['compression_o_gz']  = 'gzip';
$lang['compression_o_bz2'] = 'bz2';

/* xsendfile header */
$lang['xsendfile_o_0'] = "don't use";
$lang['xsendfile_o_1'] = 'Proprietary lighttpd header (before release 1.5)';
$lang['xsendfile_o_2'] = 'Standard X-Sendfile header';
$lang['xsendfile_o_3'] = 'Proprietary Nginx X-Accel-Redirect header';

/* Display user info */
$lang['showuseras_o_loginname']     = 'Login name';
$lang['showuseras_o_username']      = "User's full name";
$lang['showuseras_o_username_link'] = "User's full name as interwiki user link";
$lang['showuseras_o_email']         = "User's e-mail addresss (obfuscated according to mailguard setting)";
$lang['showuseras_o_email_link']    = "User's e-mail addresss as a mailto: link";

/* useheading options */
$lang['useheading_o_0'] = 'Never';
$lang['useheading_o_navigation'] = 'Navigation Only';
$lang['useheading_o_content'] = 'Wiki Content Only';
$lang['useheading_o_1'] = 'Always';

$lang['readdircache'] = 'Maximum age for readdir cache (sec)';
