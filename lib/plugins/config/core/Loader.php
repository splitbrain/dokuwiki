<?php

namespace dokuwiki\plugin\config\core;

use dokuwiki\Extension\Event;

/**
 * Configuration loader
 *
 * Loads configuration meta data and settings from the various files. Honors the
 * configuration cascade and installed plugins.
 */
class Loader {
    /** @var ConfigParser */
    protected $parser;

    /** @var string[] list of enabled plugins */
    protected $plugins;
    /** @var string current template */
    protected $template;

    /**
     * Loader constructor.
     * @param ConfigParser $parser
     * @triggers PLUGIN_CONFIG_PLUGINLIST
     */
    public function __construct(ConfigParser $parser) {
        global $conf;
        $this->parser = $parser;
        $this->plugins = plugin_list();
        $this->template = $conf['template'];
        // allow plugins to remove configurable plugins
        Event::createAndTrigger('PLUGIN_CONFIG_PLUGINLIST', $this->plugins);
    }

    /**
     * Read the settings meta data
     *
     * Reads the main file, plugins and template settings meta data
     *
     * @return array
     */
    public function loadMeta() {
        // load main file
        $meta = array();
        include DOKU_PLUGIN . 'config/settings/config.metadata.php';

        // plugins
        foreach($this->plugins as $plugin) {
            $meta = array_merge(
                $meta,
                $this->loadExtensionMeta(
                    DOKU_PLUGIN . $plugin . '/conf/metadata.php',
                    'plugin',
                    $plugin
                )
            );
        }

        // current template
        $meta = array_merge(
            $meta,
            $this->loadExtensionMeta(
                tpl_incdir() . '/conf/metadata.php',
                'tpl',
                $this->template
            )
        );

        return $meta;
    }

    /**
     * Read the default values
     *
     * Reads the main file, plugins and template defaults
     *
     * @return array
     */
    public function loadDefaults() {

        // initialize array
        $conf = array();

        // plugins
        foreach($this->plugins as $plugin) {
            $conf = array_merge(
                $conf,
                $this->loadExtensionConf(
                    DOKU_PLUGIN . $plugin . '/conf/default.php',
                    'plugin',
                    $plugin
                )
            );
        }

        // current template
        $conf = array_merge(
            $conf,
            $this->loadExtensionConf(
                tpl_incdir() . '/conf/default.php',
                'tpl',
                $this->template
            )
        );
		
		// load main files
		global $config_cascade;
		$conf = array_merge(
			$conf,
			$this->loadConfigs($config_cascade['main']['default'])
		);
        
        return $conf;
    }

    /**
     * Reads the language strings
     *
     * Only reads extensions, main one is loaded the usual way
     *
     * @return array
     */
    public function loadLangs() {
        $lang = array();

        // plugins
        foreach($this->plugins as $plugin) {
            $lang = array_merge(
                $lang,
                $this->loadExtensionLang(
                    DOKU_PLUGIN . $plugin . '/',
                    'plugin',
                    $plugin
                )
            );
        }

        // current template
        $lang = array_merge(
            $lang,
            $this->loadExtensionLang(
                tpl_incdir() . '/',
                'tpl',
                $this->template
            )
        );

        return $lang;
    }

    /**
     * Read the local settings
     *
     * @return array
     */
    public function loadLocal() {
        global $config_cascade;
        return $this->loadConfigs($config_cascade['main']['local']);
    }

    /**
     * Read the protected settings
     *
     * @return array
     */
    public function loadProtected() {
        global $config_cascade;
        return $this->loadConfigs($config_cascade['main']['protected']);
    }

    /**
     * Read the config values from the given files
     *
     * @param string[] $files paths to config php's
     * @return array
     */
    protected function loadConfigs($files) {
        $conf = array();
        foreach($files as $file) {
            $conf = array_merge($conf, $this->parser->parse($file));
        }
        return $conf;
    }

    /**
     * Read settings file from an extension
     *
     * This is used to read the settings.php files of plugins and templates
     *
     * @param string $file php file to read
     * @param string $type should be 'plugin' or 'tpl'
     * @param string $extname name of the extension
     * @return array
     */
    protected function loadExtensionMeta($file, $type, $extname) {
        if(!file_exists($file)) return array();
        $prefix = $type . Configuration::KEYMARKER . $extname . Configuration::KEYMARKER;

        // include file
        $meta = array();
        include $file;
        if(empty($meta)) return array();

        // read data
        $data = array();
        $data[$prefix . $type . '_settings_name'] = ['fieldset'];
        foreach($meta as $key => $value) {
            if($value[0] == 'fieldset') continue; //plugins only get one fieldset
            $data[$prefix . $key] = $value;
        }

        return $data;
    }

    /**
     * Read a default file from an extension
     *
     * This is used to read the default.php files of plugins and templates
     *
     * @param string $file php file to read
     * @param string $type should be 'plugin' or 'tpl'
     * @param string $extname name of the extension
     * @return array
     */
    protected function loadExtensionConf($file, $type, $extname) {
        if(!file_exists($file)) return array();
        $prefix = $type . Configuration::KEYMARKER . $extname . Configuration::KEYMARKER;

        // parse file
        $conf = $this->parser->parse($file);
        if(empty($conf)) return array();

        // read data
        $data = array();
        foreach($conf as $key => $value) {
            $data[$prefix . $key] = $value;
        }

        return $data;
    }

    /**
     * Read the language file of an extension
     *
     * @param string $dir directory of the extension
     * @param string $type should be 'plugin' or 'tpl'
     * @param string $extname name of the extension
     * @return array
     */
    protected function loadExtensionLang($dir, $type, $extname) {
        global $conf;
        $ll = $conf['lang'];
        $prefix = $type . Configuration::KEYMARKER . $extname . Configuration::KEYMARKER;

        // include files
        $lang = array();
        if(file_exists($dir . 'lang/en/settings.php')) {
            include $dir . 'lang/en/settings.php';
        }
        if($ll != 'en' && file_exists($dir . 'lang/' . $ll . '/settings.php')) {
            include $dir . 'lang/' . $ll . '/settings.php';
        }

        // set up correct keys
        $strings = array();
        foreach($lang as $key => $val) {
            $strings[$prefix . $key] = $val;
        }

        // add fieldset key
        $strings[$prefix . $type . '_settings_name'] = ucwords(str_replace('_', ' ', $extname));

        return $strings;
    }
}
