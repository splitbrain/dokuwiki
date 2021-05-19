<?php

namespace dokuwiki\Action;

use dokuwiki\Ui;

/**
 * Class Revisions
 *
 * Show the list of old revisions of the current page
 *
 * @package dokuwiki\Action
 */
class Revisions extends AbstractAction
{
    /** @inheritdoc */
    public function minimumPermission()
    {
        return AUTH_READ;
    }

    /** @inheritdoc */
    public function tplContent()
    {
        global $INFO, $INPUT;
        (new Ui\PageRevisions($INFO['id']))->show($INPUT->int('first'));
    }
}
