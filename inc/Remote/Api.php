<?php

namespace dokuwiki\Remote;

use dokuwiki\Extension\Event;
use dokuwiki\Extension\RemotePlugin;

/**
 * This class provides information about remote access to the wiki.
 *
 * == Types of methods ==
 * There are two types of remote methods. The first is the core methods.
 * These are always available and provided by dokuwiki.
 * The other is plugin methods. These are provided by remote plugins.
 *
 * == Information structure ==
 * The information about methods will be given in an array with the following structure:
 * array(
 *     'method.remoteName' => array(
 *          'args' => array(
 *              'type eg. string|int|...|date|file',
 *          )
 *          'name' => 'method name in class',
 *          'return' => 'type',
 *          'public' => 1/0 - method bypass default group check (used by login)
 *          ['doc' = 'method documentation'],
 *     )
 * )
 *
 * plugin names are formed the following:
 *   core methods begin by a 'dokuwiki' or 'wiki' followed by a . and the method name itself.
 *   i.e.: dokuwiki.version or wiki.getPage
 *
 * plugin methods are formed like 'plugin.<plugin name>.<method name>'.
 * i.e.: plugin.clock.getTime or plugin.clock_gmt.getTime
 */
class Api
{

    /**
     * @var ApiCore
     */
    private $coreMethods = null;

    /**
     * @var array remote methods provided by dokuwiki plugins - will be filled lazy via
     * {@see dokuwiki\Remote\RemoteAPI#getPluginMethods}
     */
    private $pluginMethods = null;

    /**
     * @var array contains custom calls to the api. Plugins can use the XML_CALL_REGISTER event.
     * The data inside is 'custom.call.something' => array('plugin name', 'remote method name')
     *
     * The remote method name is the same as in the remote name returned by _getMethods().
     */
    private $pluginCustomCalls = null;

    private $dateTransformation;
    private $fileTransformation;

    /**
     * constructor
     */
    public function __construct()
    {
        $this->dateTransformation = array($this, 'dummyTransformation');
        $this->fileTransformation = array($this, 'dummyTransformation');
    }

    /**
     * Get all available methods with remote access.
     *
     * @return array with information to all available methods
     * @throws RemoteException
     */
    public function getMethods()
    {
        return array_merge($this->getCoreMethods(), $this->getPluginMethods());
    }

    /**
     * Call a method via remote api.
     *
     * @param string $method name of the method to call.
     * @param array $args arguments to pass to the given method
     * @return mixed result of method call, must be a primitive type.
     * @throws RemoteException
     */
    public function call($method, $args = array())
    {
        if ($args === null) {
            $args = array();
        }
        list($type, $pluginName, /* $call */) = explode('.', $method, 3);
        if ($type === 'plugin') {
            return $this->callPlugin($pluginName, $method, $args);
        }
        if ($this->coreMethodExist($method)) {
            return $this->callCoreMethod($method, $args);
        }
        return $this->callCustomCallPlugin($method, $args);
    }

    /**
     * Check existance of core methods
     *
     * @param string $name name of the method
     * @return bool if method exists
     */
    private function coreMethodExist($name)
    {
        $coreMethods = $this->getCoreMethods();
        return array_key_exists($name, $coreMethods);
    }

    /**
     * Try to call custom methods provided by plugins
     *
     * @param string $method name of method
     * @param array $args
     * @return mixed
     * @throws RemoteException if method not exists
     */
    private function callCustomCallPlugin($method, $args)
    {
        $customCalls = $this->getCustomCallPlugins();
        if (!array_key_exists($method, $customCalls)) {
            throw new RemoteException('Method does not exist', -32603);
        }
        $customCall = $customCalls[$method];
        return $this->callPlugin($customCall[0], $customCall[1], $args);
    }

    /**
     * Returns plugin calls that are registered via RPC_CALL_ADD action
     *
     * @return array with pairs of custom plugin calls
     * @triggers RPC_CALL_ADD
     */
    private function getCustomCallPlugins()
    {
        if ($this->pluginCustomCalls === null) {
            $data = array();
            Event::createAndTrigger('RPC_CALL_ADD', $data);
            $this->pluginCustomCalls = $data;
        }
        return $this->pluginCustomCalls;
    }

    /**
     * Call a plugin method
     *
     * @param string $pluginName
     * @param string $method method name
     * @param array $args
     * @return mixed return of custom method
     * @throws RemoteException
     */
    private function callPlugin($pluginName, $method, $args)
    {
        $plugin = plugin_load('remote', $pluginName);
        $methods = $this->getPluginMethods();
        if (!$plugin) {
            throw new RemoteException('Method does not exist', -32603);
        }
        $this->checkAccess($methods[$method]);
        $name = $this->getMethodName($methods, $method);
        try {
            set_error_handler(array($this, "argumentWarningHandler"), E_WARNING); // for PHP <7.1
            return call_user_func_array(array($plugin, $name), $args);
        } catch (\ArgumentCountError $th) {
            throw new RemoteException('Method does not exist - wrong parameter count.', -32603);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Call a core method
     *
     * @param string $method name of method
     * @param array $args
     * @return mixed
     * @throws RemoteException if method not exist
     */
    private function callCoreMethod($method, $args)
    {
        $coreMethods = $this->getCoreMethods();
        $this->checkAccess($coreMethods[$method]);
        if (!isset($coreMethods[$method])) {
            throw new RemoteException('Method does not exist', -32603);
        }
        $this->checkArgumentLength($coreMethods[$method], $args);
        try {
            set_error_handler(array($this, "argumentWarningHandler"), E_WARNING); // for PHP <7.1
            return call_user_func_array(array($this->coreMethods, $this->getMethodName($coreMethods, $method)), $args);
        } catch (\ArgumentCountError $th) {
            throw new RemoteException('Method does not exist - wrong parameter count.', -32603);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Check if access should be checked
     *
     * @param array $methodMeta data about the method
     * @throws AccessDeniedException
     */
    private function checkAccess($methodMeta)
    {
        if (!isset($methodMeta['public'])) {
            $this->forceAccess();
        } else {
            if ($methodMeta['public'] == '0') {
                $this->forceAccess();
            }
        }
    }

    /**
     * Check the number of parameters
     *
     * @param array $methodMeta data about the method
     * @param array $args
     * @throws RemoteException if wrong parameter count
     */
    private function checkArgumentLength($methodMeta, $args)
    {
        if (count($methodMeta['args']) < count($args)) {
            throw new RemoteException('Method does not exist - wrong parameter count.', -32603);
        }
    }

    /**
     * Determine the name of the real method
     *
     * @param array $methodMeta list of data of the methods
     * @param string $method name of method
     * @return string
     */
    private function getMethodName($methodMeta, $method)
    {
        if (isset($methodMeta[$method]['name'])) {
            return $methodMeta[$method]['name'];
        }
        $method = explode('.', $method);
        return $method[count($method) - 1];
    }

    /**
     * Perform access check for current user
     *
     * @return bool true if the current user has access to remote api.
     * @throws AccessDeniedException If remote access disabled
     */
    public function hasAccess()
    {
        global $conf;
        global $USERINFO;
        /** @var \dokuwiki\Input\Input $INPUT */
        global $INPUT;

        if (!$conf['remote']) {
            throw new AccessDeniedException('server error. RPC server not enabled.', -32604);
        }
        if (trim($conf['remoteuser']) == '!!not set!!') {
            return false;
        }
        if (!$conf['useacl']) {
            return true;
        }
        if (trim($conf['remoteuser']) == '') {
            return true;
        }

        return auth_isMember($conf['remoteuser'], $INPUT->server->str('REMOTE_USER'), (array) $USERINFO['grps']);
    }

    /**
     * Requests access
     *
     * @return void
     * @throws AccessDeniedException On denied access.
     */
    public function forceAccess()
    {
        if (!$this->hasAccess()) {
            throw new AccessDeniedException('server error. not authorized to call method', -32604);
        }
    }

    /**
     * Collects all the methods of the enabled Remote Plugins
     *
     * @return array all plugin methods.
     * @throws RemoteException if not implemented
     */
    public function getPluginMethods()
    {
        if ($this->pluginMethods === null) {
            $this->pluginMethods = array();
            $plugins = plugin_list('remote');

            foreach ($plugins as $pluginName) {
                /** @var RemotePlugin $plugin */
                $plugin = plugin_load('remote', $pluginName);
                if (!is_subclass_of($plugin, 'dokuwiki\Extension\RemotePlugin')) {
                    throw new RemoteException(
                        "Plugin $pluginName does not implement dokuwiki\Plugin\DokuWiki_Remote_Plugin"
                    );
                }

                try {
                    $methods = $plugin->_getMethods();
                } catch (\ReflectionException $e) {
                    throw new RemoteException('Automatic aggregation of available remote methods failed', 0, $e);
                }

                foreach ($methods as $method => $meta) {
                    $this->pluginMethods["plugin.$pluginName.$method"] = $meta;
                }
            }
        }
        return $this->pluginMethods;
    }

    /**
     * Collects all the core methods
     *
     * @param ApiCore $apiCore this parameter is used for testing. Here you can pass a non-default RemoteAPICore
     *                         instance. (for mocking)
     * @return array all core methods.
     */
    public function getCoreMethods($apiCore = null)
    {
        if ($this->coreMethods === null) {
            if ($apiCore === null) {
                $this->coreMethods = new ApiCore($this);
            } else {
                $this->coreMethods = $apiCore;
            }
        }
        return $this->coreMethods->getRemoteInfo();
    }

    /**
     * Transform file to xml
     *
     * @param mixed $data
     * @return mixed
     */
    public function toFile($data)
    {
        return call_user_func($this->fileTransformation, $data);
    }

    /**
     * Transform date to xml
     *
     * @param mixed $data
     * @return mixed
     */
    public function toDate($data)
    {
        return call_user_func($this->dateTransformation, $data);
    }

    /**
     * A simple transformation
     *
     * @param mixed $data
     * @return mixed
     */
    public function dummyTransformation($data)
    {
        return $data;
    }

    /**
     * Set the transformer function
     *
     * @param callback $dateTransformation
     */
    public function setDateTransformation($dateTransformation)
    {
        $this->dateTransformation = $dateTransformation;
    }

    /**
     * Set the transformer function
     *
     * @param callback $fileTransformation
     */
    public function setFileTransformation($fileTransformation)
    {
        $this->fileTransformation = $fileTransformation;
    }

    /**
     * The error handler that catches argument-related warnings
     */
    public function argumentWarningHandler($errno, $errstr)
    {
        if (substr($errstr, 0, 17) == 'Missing argument ') {
            throw new RemoteException('Method does not exist - wrong parameter count.', -32603);
        }
    }
}
