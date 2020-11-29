<?php
// phpcs:ignoreFile -- this file violates PSR-12 by definition
/**
 * These classes and functions are deprecated and will be removed in future releases
 */

use dokuwiki\Debug\DebugHelper;
use dokuwiki\Subscriptions\BulkSubscriptionSender;
use dokuwiki\Subscriptions\MediaSubscriptionSender;
use dokuwiki\Subscriptions\PageSubscriptionSender;
use dokuwiki\Subscriptions\RegistrationSubscriptionSender;
use dokuwiki\Subscriptions\SubscriberManager;

/**
 * @inheritdoc
 * @deprecated 2018-05-07
 */
class RemoteAccessDeniedException extends \dokuwiki\Remote\AccessDeniedException
{
    /** @inheritdoc */
    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        dbg_deprecated(\dokuwiki\Remote\AccessDeniedException::class);
        parent::__construct($message, $code, $previous);
    }

}

/**
 * @inheritdoc
 * @deprecated 2018-05-07
 */
class RemoteException extends \dokuwiki\Remote\RemoteException
{
    /** @inheritdoc */
    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        dbg_deprecated(\dokuwiki\Remote\RemoteException::class);
        parent::__construct($message, $code, $previous);
    }

}

/**
 * Escapes regex characters other than (, ) and /
 *
 * @param string $str
 * @return string
 * @deprecated 2018-05-04
 */
function Doku_Lexer_Escape($str)
{
    dbg_deprecated('\\dokuwiki\\Parsing\\Lexer\\Lexer::escape()');
    return \dokuwiki\Parsing\Lexer\Lexer::escape($str);
}

/**
 * @inheritdoc
 * @deprecated 2018-06-01
 */
class setting extends \dokuwiki\plugin\config\core\Setting\Setting
{
    /** @inheritdoc */
    public function __construct($key, array $params = null)
    {
        dbg_deprecated(\dokuwiki\plugin\config\core\Setting\Setting::class);
        parent::__construct($key, $params);
    }
}

/**
 * @inheritdoc
 * @deprecated 2018-06-01
 */
class setting_authtype extends \dokuwiki\plugin\config\core\Setting\SettingAuthtype
{
    /** @inheritdoc */
    public function __construct($key, array $params = null)
    {
        dbg_deprecated(\dokuwiki\plugin\config\core\Setting\SettingAuthtype::class);
        parent::__construct($key, $params);
    }
}

/**
 * @inheritdoc
 * @deprecated 2018-06-01
 */
class setting_string extends \dokuwiki\plugin\config\core\Setting\SettingString
{
    /** @inheritdoc */
    public function __construct($key, array $params = null)
    {
        dbg_deprecated(\dokuwiki\plugin\config\core\Setting\SettingString::class);
        parent::__construct($key, $params);
    }
}

/**
 * @inheritdoc
 * @deprecated 2018-06-15
 */
class PageChangelog extends \dokuwiki\ChangeLog\PageChangeLog
{
    /** @inheritdoc */
    public function __construct($id, $chunk_size = 8192)
    {
        dbg_deprecated(\dokuwiki\ChangeLog\PageChangeLog::class);
        parent::__construct($id, $chunk_size);
    }
}

/**
 * @inheritdoc
 * @deprecated 2018-06-15
 */
class MediaChangelog extends \dokuwiki\ChangeLog\MediaChangeLog
{
    /** @inheritdoc */
    public function __construct($id, $chunk_size = 8192)
    {
        dbg_deprecated(\dokuwiki\ChangeLog\MediaChangeLog::class);
        parent::__construct($id, $chunk_size);
    }
}

/** Behavior switch for JSON::decode() */
define('JSON_LOOSE_TYPE', 16);

/** Behavior switch for JSON::decode() */
define('JSON_STRICT_TYPE', 0);

/**
 * Encode/Decode JSON
 * @deprecated 2018-07-27
 */
class JSON
{
    protected $use = 0;

    /**
     * @param int $use JSON_*_TYPE flag
     * @deprecated  2018-07-27
     */
    public function __construct($use = JSON_STRICT_TYPE)
    {
        $this->use = $use;
    }

    /**
     * Encode given structure to JSON
     *
     * @param mixed $var
     * @return string
     * @deprecated  2018-07-27
     */
    public function encode($var)
    {
        dbg_deprecated('json_encode');
        return json_encode($var);
    }

    /**
     * Alias for encode()
     * @param $var
     * @return string
     * @deprecated  2018-07-27
     */
    public function enc($var) {
        return $this->encode($var);
    }

    /**
     * Decode given string from JSON
     *
     * @param string $str
     * @return mixed
     * @deprecated  2018-07-27
     */
    public function decode($str)
    {
        dbg_deprecated('json_encode');
        return json_decode($str, ($this->use == JSON_LOOSE_TYPE));
    }

    /**
     * Alias for decode
     *
     * @param $str
     * @return mixed
     * @deprecated  2018-07-27
     */
    public function dec($str) {
        return $this->decode($str);
    }
}

/**
 * @inheritdoc
 * @deprecated 2019-02-19
 */
class Input extends \dokuwiki\Input\Input {
    /**
     * @inheritdoc
     * @deprecated 2019-02-19
     */
    public function __construct()
    {
        dbg_deprecated(\dokuwiki\Input\Input::class);
        parent::__construct();
    }
}

/**
 * @inheritdoc
 * @deprecated 2019-02-19
 */
class PostInput extends \dokuwiki\Input\Post {
    /**
     * @inheritdoc
     * @deprecated 2019-02-19
     */
    public function __construct()
    {
        dbg_deprecated(\dokuwiki\Input\Post::class);
        parent::__construct();
    }
}

/**
 * @inheritdoc
 * @deprecated 2019-02-19
 */
class GetInput extends \dokuwiki\Input\Get {
    /**
     * @inheritdoc
     * @deprecated 2019-02-19
     */
    public function __construct()
    {
        dbg_deprecated(\dokuwiki\Input\Get::class);
        parent::__construct();
    }
}

/**
 * @inheritdoc
 * @deprecated 2019-02-19
 */
class ServerInput extends \dokuwiki\Input\Server {
    /**
     * @inheritdoc
     * @deprecated 2019-02-19
     */
    public function __construct()
    {
        dbg_deprecated(\dokuwiki\Input\Server::class);
        parent::__construct();
    }
}

/**
 * @inheritdoc
 * @deprecated 2019-03-06
 */
class PassHash extends \dokuwiki\PassHash {
    /**
     * @inheritdoc
     * @deprecated 2019-03-06
     */
    public function __construct()
    {
        dbg_deprecated(\dokuwiki\PassHash::class);
    }
}

/**
 * @deprecated since 2019-03-17 use \dokuwiki\HTTP\HTTPClientException instead!
 */
class HTTPClientException extends \dokuwiki\HTTP\HTTPClientException {

    /**
     * @inheritdoc
     * @deprecated 2019-03-17
     */
    public function __construct($message = '', $code = 0, $previous = null)
    {
        DebugHelper::dbgDeprecatedFunction(dokuwiki\HTTP\HTTPClientException::class);
        parent::__construct($message, $code, $previous);
    }
}

/**
 * @deprecated since 2019-03-17 use \dokuwiki\HTTP\HTTPClient instead!
 */
class HTTPClient extends \dokuwiki\HTTP\HTTPClient {

    /**
     * @inheritdoc
     * @deprecated 2019-03-17
     */
    public function __construct()
    {
        DebugHelper::dbgDeprecatedFunction(dokuwiki\HTTP\HTTPClient::class);
        parent::__construct();
    }
}

/**
 * @deprecated since 2019-03-17 use \dokuwiki\HTTP\DokuHTTPClient instead!
 */
class DokuHTTPClient extends \dokuwiki\HTTP\DokuHTTPClient
{

    /**
     * @inheritdoc
     * @deprecated 2019-03-17
     */
    public function __construct()
    {
        DebugHelper::dbgDeprecatedFunction(dokuwiki\HTTP\DokuHTTPClient::class);
        parent::__construct();
    }
}

/**
 * function wrapper to process (create, trigger and destroy) an event
 *
 * @param  string   $name               name for the event
 * @param  mixed    $data               event data
 * @param  callback $action             (optional, default=NULL) default action, a php callback function
 * @param  bool     $canPreventDefault  (optional, default=true) can hooks prevent the default action
 *
 * @return mixed                        the event results value after all event processing is complete
 *                                      by default this is the return value of the default action however
 *                                      it can be set or modified by event handler hooks
 * @deprecated 2018-06-15
 */
function trigger_event($name, &$data, $action=null, $canPreventDefault=true) {
    dbg_deprecated('\dokuwiki\Extension\Event::createAndTrigger');
    return \dokuwiki\Extension\Event::createAndTrigger($name, $data, $action, $canPreventDefault);
}

/**
 * @inheritdoc
 * @deprecated 2018-06-15
 */
class Doku_Plugin_Controller extends \dokuwiki\Extension\PluginController {
    /** @inheritdoc */
    public function __construct()
    {
        dbg_deprecated(\dokuwiki\Extension\PluginController::class);
        parent::__construct();
    }
}


/**
 * Class for handling (email) subscriptions
 *
 * @author  Adrian Lang <lang@cosmocode.de>
 * @author  Andreas Gohr <andi@splitbrain.org>
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 *
 * @deprecated 2019-04-22 Use the classes in the \dokuwiki\Subscriptions namespace instead!
 */
class Subscription {

    /**
     * Check if subscription system is enabled
     *
     * @return bool
     *
     * @deprecated 2019-04-20 \dokuwiki\Subscriptions\SubscriberManager::isenabled
     */
    public function isenabled() {
        DebugHelper::dbgDeprecatedFunction('\dokuwiki\Subscriptions\SubscriberManager::isenabled');
        $subscriberManager = new SubscriberManager();
        return $subscriberManager->isenabled();
    }

    /**
     * Recursively search for matching subscriptions
     *
     * This function searches all relevant subscription files for a page or
     * namespace.
     *
     * @author Adrian Lang <lang@cosmocode.de>
     *
     * @param string         $page The target object’s (namespace or page) id
     * @param string|array   $user
     * @param string|array   $style
     * @param string|array   $data
     * @return array
     *
     * @deprecated 2019-04-20 \dokuwiki\Subscriptions\SubscriberManager::subscribers
     */
    public function subscribers($page, $user = null, $style = null, $data = null) {
        DebugHelper::dbgDeprecatedFunction('\dokuwiki\Subscriptions\SubscriberManager::subscribers');
        $manager = new SubscriberManager();
        return $manager->subscribers($page, $user, $style, $data);
    }

    /**
     * Adds a new subscription for the given page or namespace
     *
     * This will automatically overwrite any existent subscription for the given user on this
     * *exact* page or namespace. It will *not* modify any subscription that may exist in higher namespaces.
     *
     * @param string $id The target page or namespace, specified by id; Namespaces
     *                   are identified by appending a colon.
     * @param string $user
     * @param string $style
     * @param string $data
     * @throws Exception when user or style is empty
     * @return bool
     *
     * @deprecated 2019-04-20 \dokuwiki\Subscriptions\SubscriberManager::add
     */
    public function add($id, $user, $style, $data = '') {
        DebugHelper::dbgDeprecatedFunction('\dokuwiki\Subscriptions\SubscriberManager::add');
        $manager = new SubscriberManager();
        return $manager->add($id, $user, $style, $data);
    }

    /**
     * Removes a subscription for the given page or namespace
     *
     * This removes all subscriptions matching the given criteria on the given page or
     * namespace. It will *not* modify any subscriptions that may exist in higher
     * namespaces.
     *
     * @param string         $id   The target object’s (namespace or page) id
     * @param string|array   $user
     * @param string|array   $style
     * @param string|array   $data
     * @return bool
     *
     * @deprecated 2019-04-20 \dokuwiki\Subscriptions\SubscriberManager::remove
     */
    public function remove($id, $user = null, $style = null, $data = null) {
        DebugHelper::dbgDeprecatedFunction('\dokuwiki\Subscriptions\SubscriberManager::remove');
        $manager = new SubscriberManager();
        return $manager->remove($id, $user, $style, $data);
    }

    /**
     * Get data for $INFO['subscribed']
     *
     * $INFO['subscribed'] is either false if no subscription for the current page
     * and user is in effect. Else it contains an array of arrays with the fields
     * “target”, “style”, and optionally “data”.
     *
     * @param string $id  Page ID, defaults to global $ID
     * @param string $user User, defaults to $_SERVER['REMOTE_USER']
     * @return array|false
     * @author Adrian Lang <lang@cosmocode.de>
     *
     * @deprecated 2019-04-20 \dokuwiki\Subscriptions\SubscriberManager::userSubscription
     */
    public function user_subscription($id = '', $user = '') {
        DebugHelper::dbgDeprecatedFunction('\dokuwiki\Subscriptions\SubscriberManager::userSubscription');
        $manager = new SubscriberManager();
        return $manager->userSubscription($id, $user);
    }

    /**
     * Send digest and list subscriptions
     *
     * This sends mails to all subscribers that have a subscription for namespaces above
     * the given page if the needed $conf['subscribe_time'] has passed already.
     *
     * This function is called form lib/exe/indexer.php
     *
     * @param string $page
     * @return int number of sent mails
     *
     * @deprecated 2019-04-20 \dokuwiki\Subscriptions\BulkSubscriptionSender::sendBulk
     */
    public function send_bulk($page) {
        DebugHelper::dbgDeprecatedFunction('\dokuwiki\Subscriptions\BulkSubscriptionSender::sendBulk');
        $subscriptionSender = new BulkSubscriptionSender();
        return $subscriptionSender->sendBulk($page);
    }

    /**
     * Send the diff for some page change
     *
     * @param string   $subscriber_mail The target mail address
     * @param string   $template        Mail template ('subscr_digest', 'subscr_single', 'mailtext', ...)
     * @param string   $id              Page for which the notification is
     * @param int|null $rev             Old revision if any
     * @param string   $summary         Change summary if any
     * @return bool                     true if successfully sent
     *
     * @deprecated 2019-04-20 \dokuwiki\Subscriptions\PageSubscriptionSender::sendPageDiff
     */
    public function send_diff($subscriber_mail, $template, $id, $rev = null, $summary = '') {
        DebugHelper::dbgDeprecatedFunction('\dokuwiki\Subscriptions\PageSubscriptionSender::sendPageDiff');
        $subscriptionSender = new PageSubscriptionSender();
        return $subscriptionSender->sendPageDiff($subscriber_mail, $template, $id, $rev, $summary);
    }

    /**
     * Send the diff for some media change
     *
     * @fixme this should embed thumbnails of images in HTML version
     *
     * @param string   $subscriber_mail The target mail address
     * @param string   $template        Mail template ('uploadmail', ...)
     * @param string   $id              Media file for which the notification is
     * @param int|bool $rev             Old revision if any
     *
     * @deprecated 2019-04-20 \dokuwiki\Subscriptions\MediaSubscriptionSender::sendMediaDiff
     */
    public function send_media_diff($subscriber_mail, $template, $id, $rev = false) {
        DebugHelper::dbgDeprecatedFunction('\dokuwiki\Subscriptions\MediaSubscriptionSender::sendMediaDiff');
        $subscriptionSender = new MediaSubscriptionSender();
        return $subscriptionSender->sendMediaDiff($subscriber_mail, $template, $id, $rev);
    }

    /**
     * Send a notify mail on new registration
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     *
     * @param string $login    login name of the new user
     * @param string $fullname full name of the new user
     * @param string $email    email address of the new user
     * @return bool true if a mail was sent
     *
     * @deprecated 2019-04-20 \dokuwiki\Subscriptions\RegistrationSubscriptionSender::sendRegister
     */
    public function send_register($login, $fullname, $email) {
        DebugHelper::dbgDeprecatedFunction('\dokuwiki\Subscriptions\RegistrationSubscriptionSender::sendRegister');
        $subscriptionSender = new RegistrationSubscriptionSender();
        return $subscriptionSender->sendRegister($login, $fullname, $email);
    }


    /**
     * Default callback for COMMON_NOTIFY_ADDRESSLIST
     *
     * Aggregates all email addresses of user who have subscribed the given page with 'every' style
     *
     * @author Steven Danz <steven-danz@kc.rr.com>
     * @author Adrian Lang <lang@cosmocode.de>
     *
     * @todo move the whole functionality into this class, trigger SUBSCRIPTION_NOTIFY_ADDRESSLIST instead,
     *       use an array for the addresses within it
     *
     * @param array &$data Containing the entries:
     *    - $id (the page id),
     *    - $self (whether the author should be notified,
     *    - $addresslist (current email address list)
     *    - $replacements (array of additional string substitutions, @KEY@ to be replaced by value)
     *
     * @deprecated 2019-04-20 \dokuwiki\Subscriptions\SubscriberManager::notifyAddresses
     */
    public function notifyaddresses(&$data) {
        DebugHelper::dbgDeprecatedFunction('\dokuwiki\Subscriptions\SubscriberManager::notifyAddresses');
        $manager = new SubscriberManager();
        $manager->notifyAddresses($data);
    }
}

/**
 * @deprecated 2019-12-29 use \dokuwiki\Search\Indexer
 */
class Doku_Indexer extends \dokuwiki\Search\Indexer {};

/**
 * Functions to access the fulltext search index
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 * @author     Tom N Harris <tnharris@whoopdedo.org>
 */

/** @deprecated 2019-12-16 */
function idx_get_version() {
    dbg_deprecated('\dokuwiki\Search\Indexer::getVersion()');
    return (new \dokuwiki\Search\Indexer())->getVersion();
}

/** @deprecated 2019-12-16 */
function idx_addPage($page, $verbose=false, $force=false) {
    dbg_deprecated('\dokuwiki\Search\Indexer::addPage()');
    return (new \dokuwiki\Search\Indexer($page))->addPage($verbose, $force);
}

/** @deprecated 2019-12-16 */
function idx_getIndex($idx, $suffix) {
    dbg_deprecated('\dokuwiki\Search\Indexer::getIndex()');
    return (new \dokuwiki\Search\Indexer())->getIndex($idx, $suffix);
}

/** @deprecated 2019-12-16 */
function idx_listIndexLengths() {
    dbg_deprecated('\dokuwiki\Search\FulltextIndex::listIndexLengths()');
    return (new \dokuwiki\Search\FulltextIndex())->listIndexLengths();
}

/** @deprecated 2019-12-16 */
function idx_indexLengths($filter) {
    dbg_deprecated('\dokuwiki\Search\FulltextIndex::getIndexLengths()');
    return (new \dokuwiki\Search\FulltextIndex())->getIndexLengths($filter);
}

/**
 * Create MetadataIndex instance, which supports following methods:
 *    addMetaKeys($page, $key, $value=null)
 *    lookupKey($key, &$value, $func=null)
 *    getPages($key=null)
 * @see also https://www.dokuwiki.org/devel:metadata#metadata_index
 *
 * @deprecated 2020-01-30
 */
function idx_get_indexer() {
    dbg_deprecated('\dokuwiki\Search\MetadataIndex()');
    return new \dokuwiki\Search\MetadataIndex();
}

/**
 * DokuWiki fulltextsearch functions using the index
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 */

/**
 * Functions for Fulltext Search
 */
/** @deprecated 2019-12-28 */
function ft_pageSearch($query, &$highlight, $sort = null, $after = null, $before = null) {
    dbg_deprecated('\dokuwiki\Search\FulltextSearch::pageSearch()');
    return (new \dokuwiki\Search\FulltextSearch)->pageSearch($query, $highlight, $sort, $after, $before);
}

/** @deprecated 2019-12-28 */
function ft_snippet($id, $highlight) {
    dbg_deprecated('\dokuwiki\Search\FulltextSearch::snippet()');
    return (new \dokuwiki\Search\FulltextSearch)->snippet($id, $highlight);
}

/** @deprecated 2019-12-28 */
function ft_snippet_re_preprocess($term) {
    dbg_deprecated('\dokuwiki\Search\FulltextSearch::snippetRePreprocess()');
    return (new \dokuwiki\Search\FulltextSearch)->snippetRePreprocess($term);
}

/** @deprecated 2019-12-28 */
function ft_queryParser($Indexer, $query) {
    dbg_deprecated('\dokuwiki\Search\QueryParser::convert()');
    return (new \dokuwiki\Search\QueryParser)->convert($query);
}

/** @deprecated 2019-12-28 */
function ft_queryUnparser_simple(array $and, array $not, array $phrases, array $ns, array $notns) {
    dbg_deprecated('\dokuwiki\Search\QueryParser::revert()');
    return (new \dokuwiki\Search\QueryParser)->revert($and, $not, $phrases, $ns, $notns);
}

/**
 * Functions for metadata lookups
 */
/** @deprecated 2019-12-28 */
function ft_pageLookup($id, $in_ns=false, $in_title=false, $after = null, $before = null) {
    dbg_deprecated('\dokuwiki\Search\MetadataSearch::pageLookup()');
    return (new \dokuwiki\Search\MetadataSearch)->pageLookup($id, $in_ns, $in_title, $after, $before);
}

/** @deprecated 2019-12-28 */
function ft_backlinks($id, $ignore_perms = false) {
    dbg_deprecated('\dokuwiki\Search\MetadataIndex::backlinks()');
    return (new \dokuwiki\Search\MetadataIndex())->backlinks($id, $ignore_perms);
}

/** @deprecated 2019-12-28 */
function ft_mediause($id, $ignore_perms = false) {
    dbg_deprecated('\dokuwiki\Search\MetadataIndex::mediause()');
    return (new \dokuwiki\Search\MetadataIndex())->mediause($id, $ignore_perms);
}

