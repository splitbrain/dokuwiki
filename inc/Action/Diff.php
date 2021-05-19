<?php

namespace dokuwiki\Action;

use dokuwiki\Ui;

/**
 * Class Diff
 *
 * Show the differences between two revisions
 *
 * @package dokuwiki\Action
 */
class Diff extends AbstractAction
{
    /** @inheritdoc */
    public function minimumPermission()
    {
        return AUTH_READ;
    }

    /** @inheritdoc */
    public function preProcess()
    {
        global $INPUT;

        // store the selected diff type in cookie
        $difftype = $INPUT->str('difftype');
        if (!empty($difftype)) {
            set_doku_pref('difftype', $difftype);
        }
    }

    /** @inheritdoc */
    public function tplContent()
    {
        global $INFO;
        (new Ui\PageDiff($INFO['id']))->preference('showIntro', true)->show();
    }

}
