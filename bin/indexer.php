#!/usr/bin/env php
<?php

use splitbrain\phpcli\CLI;
use splitbrain\phpcli\Options;
use dokuwiki\Search\Indexer;

if (!defined('DOKU_INC')) define('DOKU_INC', realpath(dirname(__FILE__) . '/../') . '/');
define('NOSESSION', 1);
require_once(DOKU_INC . 'inc/init.php');

/**
 * Update the Search Index from command line
 */
class IndexerCLI extends CLI
{
    private $quiet = false;
    private $clear = false;

    /**
     * Register options and arguments on the given $options object
     *
     * @param Options $options
     * @return void
     */
    protected function setup(Options $options)
    {
        $options->setHelp(
            'Updates the searchindex by indexing all new or changed pages. When the -c option is ' .
            'given the index is cleared first.'
        );

        $options->registerOption(
            'clear',
            'clear the index before updating',
            'c'
        );
        $options->registerOption(
            'quiet',
            'don\'t produce any output',
            'q'
        );
    }

    /**
     * Your main program
     *
     * Arguments and options have been parsed when this is run
     *
     * @param Options $options
     * @return void
     */
    protected function main(Options $options)
    {
        $this->clear = $options->getOpt('clear');
        $this->quiet = $options->getOpt('quiet');

        if($this->clear) $this->clearindex();

        $this->update();
    }

    /**
     * Update the index
     */
    protected function update()
    {
        global $conf;
        $data = array();
        $this->quietecho("Searching pages... ");
        search($data, $conf['datadir'], 'search_allpages', array('skipacl' => true));
        $this->quietecho(count($data) . " pages found.\n");

        foreach($data as $val) {
            $this->index($val['id']);
        }
    }

    /**
     * Index the given page
     *
     * @param string $id
     */
    protected function index($id)
    {
        $this->quietecho("$id... ");
        (new Indexer($id))->addPage(!$this->quiet, $this->clear);
        $this->quietecho("done.\n");
    }

    /**
     * Clear all index files
     */
    protected function clearindex()
    {
        $this->quietecho("Clearing index... ");
        (new Indexer())->clear();
        $this->quietecho("done.\n");
    }

    /**
     * Print message if not supressed
     *
     * @param string $msg
     */
    protected function quietecho($msg)
    {
        if(!$this->quiet) echo $msg;
    }
}

// Main
$cli = new IndexerCLI();
$cli->run();
