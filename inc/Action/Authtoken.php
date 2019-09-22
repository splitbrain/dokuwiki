<?php

namespace dokuwiki\Action;

use dokuwiki\Action\Exception\ActionAbort;
use dokuwiki\Action\Exception\ActionException;
use dokuwiki\AuthenticationToken;

class Authtoken extends AbstractUserAction {

    /** @inheritdoc */
    public function minimumPermission() {
        return AUTH_NONE;
    }

    /** @inheritdoc */
    public function checkPreconditions() {
        parent::checkPreconditions();

        if(!checkSecurityToken()) throw new ActionException('profile');
    }

    /** @inheritdoc */
    public function preProcess() {
        global $INPUT;
        parent::preProcess();
        $token = AuthenticationToken::fromUser($INPUT->server->str('REMOTE_USER'));
        $token->reset();
        throw new ActionAbort('profile');
    }
}
