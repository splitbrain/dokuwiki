<?php
/**
 * Metadata for configuration manager plugin
 *
 * Note: This file is loaded in Loader::loadMeta().
 *
 * Format:
 *   $meta[<setting name>] = array(<handler class id>,<param name> => <param value>);
 *
 *   <handler class id>  is the handler class name without the "setting_" prefix
 *
 * Defined classes (see core/Setting/*):
 *   Generic
 *   -------------------------------------------
 *   ''             - default class ('setting'), textarea, minimal input validation, setting output in quotes
 *   'string'       - single line text input, minimal input validation, setting output in quotes
 *   'numeric'      - text input, accepts numbers and arithmetic operators, setting output without quotes
 *                    if given the '_min' and '_max' parameters are used for validation
 *   'numericopt'   - like above, but accepts empty values
 *   'onoff'        - checkbox input, setting output  0|1
 *   'multichoice'  - select input (single choice), setting output with quotes, required _choices parameter
 *   'email'        - text input, input must conform to email address format, supports optional '_multiple'
 *                    parameter for multiple comma separated email addresses
 *   'password'     - password input, minimal input validation, setting output text in quotes, maybe encoded
 *                    according to the _code parameter
 *   'dirchoice'    - as multichoice, selection choices based on folders found at location specified in _dir
 *                    parameter (required). A pattern can be used to restrict the folders to only those which
 *                    match the pattern.
 *   'multicheckbox'- a checkbox for each choice plus an "other" string input, config file setting is a comma
 *                    separated list of checked choices
 *   'fieldset'     - used to group configuration settings, but is not itself a setting. To make this clear in
 *                    the language files the keys for this type should start with '_'.
 *   'array'        - a simple (one dimensional) array of string values, shown as comma separated list in the
 *                    config manager but saved as PHP array(). Values may not contain commas themselves.
 *                    _pattern matching on the array values supported.
 *   'regex'        - regular expression string, normally without delimiters; as for string, in addition tested
 *                    to see if will compile & run as a regex.  in addition to _pattern, also accepts _delimiter
 *                    (default '/') and _pregflags (default 'ui')
 *
 *  Single Setting
 *  -------------------------------------------------
 *   'savedir'     - as 'setting', input tested against initpath() (inc/init.php)
 *   'sepchar'     - as multichoice, selection constructed from string of valid values
 *   'authtype'    - as 'setting', input validated against a valid php file at expected location for auth files
 *   'im_convert'  - as 'setting', input must exist and be an im_convert module
 *   'disableactions' - as 'setting'
 *   'compression' - no additional parameters. checks php installation supports possible compression alternatives
 *   'licence'     - as multichoice, selection constructed from licence strings in language files
 *   'renderer'    - as multichoice, selection constructed from enabled renderer plugins which canRender()
 *   'authtype'    - as multichoice, selection constructed from the enabled auth plugins
 *
 *  Any setting commented or missing will use 'setting' class - text input, minimal validation, quoted output
 *
 * Defined parameters:
 *   '_caution'    - no value (default) or 'warning', 'danger', 'security'. display an alert along with the setting
 *   '_pattern'    - string, a preg pattern. input is tested against this pattern before being accepted
 *                   optional all classes, except onoff & multichoice which ignore it
 *   '_choices'    - array of choices. used to populate a selection box. choice will be replaced by a localised
 *                   language string, indexed by  <setting name>_o_<choice>, if one exists
 *                   required by 'multichoice' & 'multicheckbox' classes, ignored by others
 *   '_dir'        - location of directory to be used to populate choice list
 *                   required by 'dirchoice' class, ignored by other classes
 *   '_combine'    - complimentary output setting values which can be combined into a single display checkbox
 *                   optional for 'multicheckbox', ignored by other classes
 *   '_code'       - encoding method to use, accepted values: 'base64','uuencode','plain'.  defaults to plain.
 *   '_min'        - minimum numeric value, optional for 'numeric' and 'numericopt', ignored by others
 *   '_max'        - maximum numeric value, optional for 'numeric' and 'numericopt', ignored by others
 *   '_delimiter'  - string, default '/', a single character used as a delimiter for testing regex input values
 *   '_pregflags'  - string, default 'ui', valid preg pattern modifiers used when testing regex input values, for more
 *                   information see http://php.net/manual/en/reference.pcre.pattern.modifiers.php
 *   '_multiple'   - bool, allow multiple comma separated email values; optional for 'email', ignored by others
 *   '_other'      - how to handle other values (not listed in _choices). accepted values: 'always','exists','never'
 *                   default value 'always'. 'exists' only shows 'other' input field when the setting contains value(s)
 *                   not listed in choices (e.g. due to manual editing or update changing _choices).  This is safer than
 *                   'never' as it will not discard unknown/other values.
 *                   optional for 'multicheckbox', ignored by others
 *
 * The order of the settings influences the order in which they apppear in the config manager
 *
 * @author    Chris Smith <chris@jalakai.co.uk>
 */

$meta['_basic']   = array('fieldset');
$meta['title']    = array('string');
$meta['start']    = array('string','_caution' => 'warning','_pattern' => '!^[^:;/]+$!'); // don't accept namespaces
$meta['lang']     = array('dirchoice','_dir' => DOKU_INC.'inc/lang/');
$meta['template'] = array('dirchoice','_dir' => DOKU_INC.'lib/tpl/','_pattern' => '/^[\w-]+$/');
$meta['tagline']  = array('string');
$meta['sidebar']  = array('string');
$meta['license']  = array('license');
$meta['savedir']  = array('savedir','_caution' => 'danger');
$meta['basedir']  = array('string','_caution' => 'danger');
$meta['baseurl']  = array('string','_caution' => 'danger');
$meta['cookiedir'] = array('string','_caution' => 'danger');
$meta['dmode']    = array('numeric','_pattern' => '/0[0-7]{3,4}/'); // only accept octal representation
$meta['fmode']    = array('numeric','_pattern' => '/0[0-7]{3,4}/'); // only accept octal representation
$meta['allowdebug']  = array('onoff','_caution' => 'security');

$meta['_display']    = array('fieldset');
$meta['recent']      = array('numeric');
$meta['recent_days'] = array('numeric');
$meta['breadcrumbs'] = array('numeric','_min' => 0);
$meta['youarehere']  = array('onoff');
$meta['fullpath']    = array('onoff','_caution' => 'security');
$meta['typography']  = array('multichoice','_choices' => array(0,1,2));
$meta['dformat']     = array('string');
$meta['signature']   = array('string');
$meta['showuseras'] = array(
    'multichoice',
    '_choices' => array('loginname', 'username', 'username_link', 'email', 'email_link')
);
$meta['toptoclevel'] = array('multichoice','_choices' => array(1,2,3,4,5));   // 5 toc levels
$meta['tocminheads'] = array('multichoice','_choices' => array(0,1,2,3,4,5,10,15,20));
$meta['maxtoclevel'] = array('multichoice','_choices' => array(0,1,2,3,4,5));
$meta['maxseclevel'] = array('multichoice','_choices' => array(0,1,2,3,4,5)); // 0 for no sec edit buttons
$meta['camelcase']   = array('onoff','_caution' => 'warning');
$meta['deaccent']    = array('multichoice','_choices' => array(0,1,2),'_caution' => 'warning');
$meta['useheading']  = array('multichoice','_choices' => array(0,'navigation','content',1));
$meta['sneaky_index'] = array('onoff');
$meta['hidepages']   = array('regex');

$meta['_authentication'] = array('fieldset');
$meta['useacl']      = array('onoff','_caution' => 'danger');
$meta['autopasswd']  = array('onoff');
$meta['authtype']    = array('authtype','_caution' => 'danger');
$meta['passcrypt']   = array('multichoice','_choices' => array(
    'smd5','md5','apr1','sha1','ssha','lsmd5','crypt','mysql','my411','kmd5','pmd5','hmd5',
    'mediawiki','bcrypt','djangomd5','djangosha1','djangopbkdf2_sha1','djangopbkdf2_sha256',
    'sha512','argon2i','argon2id'
));
$meta['defaultgroup']= array('string');
$meta['superuser']   = array('string','_caution' => 'danger');
$meta['manager']     = array('string');
$meta['profileconfirm'] = array('onoff');
$meta['rememberme'] = array('onoff');
$meta['disableactions'] = array(
    'disableactions',
    '_choices' => array(
        'backlink',
        'index',
        'recent',
        'revisions',
        'search',
        'subscription',
        'register',
        'resendpwd',
        'profile',
        'profile_delete',
        'edit',
        'wikicode',
        'check',
        'rss'
    ),
    '_combine' => array(
        'subscription' => array('subscribe', 'unsubscribe'),
        'wikicode' => array('source', 'export_raw')
    )
);
$meta['auth_security_timeout'] = array('numeric');
$meta['securecookie'] = array('onoff');
$meta['remote']       = array('onoff','_caution' => 'security');
$meta['remoteuser']   = array('string');

$meta['_anti_spam']  = array('fieldset');
$meta['usewordblock']= array('onoff');
$meta['relnofollow'] = array('onoff');
$meta['indexdelay']  = array('numeric');
$meta['mailguard']   = array('multichoice','_choices' => array('visible','hex','none'));
$meta['iexssprotect']= array('onoff','_caution' => 'security');

$meta['_editing']    = array('fieldset');
$meta['usedraft']    = array('onoff');
$meta['htmlok']      = array('onoff','_caution' => 'security');
$meta['phpok']       = array('onoff','_caution' => 'security');
$meta['locktime']    = array('numeric');
$meta['cachetime']   = array('numeric');

$meta['_links']    = array('fieldset');
$meta['target____wiki']      = array('string');
$meta['target____interwiki'] = array('string');
$meta['target____extern']    = array('string');
$meta['target____media']     = array('string');
$meta['target____windows']   = array('string');

$meta['_media']      = array('fieldset');
$meta['mediarevisions']  = array('onoff');
$meta['gdlib']       = array('multichoice','_choices' => array(0,1,2));
$meta['im_convert']  = array('im_convert');
$meta['jpg_quality'] = array('numeric','_pattern' => '/^100$|^[1-9]?[0-9]$/');  //(0-100)
$meta['fetchsize']   = array('numeric');
$meta['refcheck']    = array('onoff');
$meta['video_settings'] = array(
    'multicheckbox',
    '_choices' => array(
        'autoplay',
        'controls',
        'loop',
        'muted'
    ),
    '_other' => "never"
);

$meta['_notifications'] = array('fieldset');
$meta['subscribers']    = array('onoff');
$meta['subscribe_time'] = array('numeric');
$meta['notify']         = array('email', '_multiple' => true);
$meta['registernotify'] = array('email', '_multiple' => true);
$meta['mailfrom']       = array('email', '_placeholders' => true);
$meta['mailreturnpath']       = array('email', '_placeholders' => true);
$meta['mailprefix']     = array('string');
$meta['htmlmail']       = array('onoff');
$meta['dontlog'] = array(
    'disableactions',
    '_choices' => array(
        'error',
        'debug',
        'deprecated',
    ),
);

$meta['_syndication'] = array('fieldset');
$meta['sitemap']     = array('numeric');
$meta['rss_type']    = array('multichoice','_choices' => array('rss','rss1','rss2','atom','atom1'));
$meta['rss_linkto']  = array('multichoice','_choices' => array('diff','page','rev','current'));
$meta['rss_content'] = array('multichoice','_choices' => array('abstract','diff','htmldiff','html'));
$meta['rss_media']   = array('multichoice','_choices' => array('both','pages','media'));
$meta['rss_update']  = array('numeric');
$meta['rss_show_summary'] = array('onoff');
$meta['rss_show_deleted'] = array('onoff');

$meta['_advanced']   = array('fieldset');
$meta['updatecheck'] = array('onoff');
$meta['userewrite']  = array('multichoice','_choices' => array(0,1,2),'_caution' => 'danger');
$meta['useslash']    = array('onoff');
$meta['sepchar']     = array('sepchar','_caution' => 'warning');
$meta['canonical']   = array('onoff');
$meta['fnencode']    = array('multichoice','_choices' => array('url','safe','utf-8'),'_caution' => 'warning');
$meta['autoplural']  = array('onoff');
$meta['compress']    = array('onoff');
$meta['cssdatauri']  = array('numeric','_pattern' => '/^\d+$/');
$meta['gzip_output'] = array('onoff');
$meta['send404']     = array('onoff');
$meta['compression'] = array('compression','_caution' => 'warning');
$meta['broken_iua']  = array('onoff');
$meta['xsendfile']   = array('multichoice','_choices' => array(0,1,2,3),'_caution' => 'warning');
$meta['renderer_xhtml'] = array('renderer','_format' => 'xhtml','_choices' => array('xhtml'),'_caution' => 'warning');
$meta['readdircache'] = array('numeric');
$meta['search_nslimit'] = array('numeric', '_min' => 0);
$meta['search_fragment'] = array('multichoice','_choices' => array('exact', 'starts_with', 'ends_with', 'contains'),);
$meta['trustedproxy'] = array('regex');

$meta['_feature_flags'] = ['fieldset'];
$meta['defer_js']       = ['onoff'];

$meta['_network']    = array('fieldset');
$meta['dnslookups']  = array('onoff');
$meta['jquerycdn']   = array('multichoice', '_choices' => array(0,'jquery', 'cdnjs'));
$meta['proxy____host'] = array('string','_pattern' => '#^(|[a-z0-9\-\.+]+)$#i');
$meta['proxy____port'] = array('numericopt');
$meta['proxy____user'] = array('string');
$meta['proxy____pass'] = array('password','_code' => 'base64');
$meta['proxy____ssl']  = array('onoff');
$meta['proxy____except'] = array('string');
