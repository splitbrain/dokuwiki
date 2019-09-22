<?php

namespace dokuwiki\Action;

use dokuwiki\Action\Exception\ActionAbort;
use dokuwiki\Action\Exception\ActionDisabledException;

/**
 * Class Profile
 *
 * Handle the profile form
 *
 * @package dokuwiki\Action
 */
class Profile extends AbstractUserAction {

    /** @inheritdoc */
    public function minimumPermission() {
        return AUTH_NONE;
    }

    /** @inheritdoc */
    public function checkPreconditions() {
        parent::checkPreconditions();

        /** @var \DokuWiki_Auth_Plugin $auth */
        global $auth;
        if(!$auth->canDo('Profile')) throw new ActionDisabledException();
    }

    /** @inheritdoc */
    public function preProcess() {
        global $lang;
        if(updateprofile()) {
            msg($lang['profchanged'], 1);
            throw new ActionAbort('show');
        }
    }

    /** @inheritdoc */
    public function tplContent() {
        $ui = new \dokuwiki\Ui\Profile();
        $ui->show();
    }

}
