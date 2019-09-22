<?php

namespace dokuwiki\Ui;
use dokuwiki\AuthenticationToken;
use dokuwiki\Form\Form;

/**
 * @author Christopher Smith <chris@jalakai.co.uk>
 * @author Andreas Gohr <andi@splitbrain.org>
 */
class Profile extends Ui {

    /** @inheritdoc */
    public function show() {
        /** @var \DokuWiki_Auth_Plugin $auth */
        global $auth;
        global $INFO;
        global $INPUT;

        $userinfo = [
            'user' => $_SERVER['REMOTE_USER'],
            'name' => $INPUT->post->str('fullname', $INFO['userinfo']['name'], true),
            'mail' => $INPUT->post->str('email', $INFO['userinfo']['mail'], true),

        ];

        print p_locale_xhtml('updateprofile');
        print '<div class="centeralign">' . NL;

        html_form('updateprofile', $this->profileForm($userinfo));
        echo $this->tokenForm($userinfo['user'])->toHTML();
        if($auth->canDo('delUser') && actionOK('profile_delete')) {
            html_form('profiledelete', $this->deletionForm());
        }

        print '</div>' . NL;
    }

    /**
     * Get the profile form
     *
     * @param array $userinfo
     * @return \Doku_Form
     */
    protected function profileForm($userinfo) {
        /** @var \DokuWiki_Auth_Plugin $auth */
        global $auth;
        global $conf;
        global $lang;

        $form = new \Doku_Form(array('id' => 'dw__register'));
        $form->startFieldset($lang['profile']);
        $form->addHidden('do', 'profile');
        $form->addHidden('save', '1');
        $form->addElement(form_makeTextField('login', $userinfo['user'], $lang['user'], '', 'block', array('size' => '50', 'disabled' => 'disabled')));
        $attr = array('size' => '50');
        if(!$auth->canDo('modName')) $attr['disabled'] = 'disabled';
        $form->addElement(form_makeTextField('fullname', $userinfo['name'], $lang['fullname'], '', 'block', $attr));
        $attr = array('size' => '50', 'class' => 'edit');
        if(!$auth->canDo('modMail')) $attr['disabled'] = 'disabled';
        $form->addElement(form_makeField('email', 'email', $userinfo['mail'], $lang['email'], '', 'block', $attr));
        $form->addElement(form_makeTag('br'));
        if($auth->canDo('modPass')) {
            $form->addElement(form_makePasswordField('newpass', $lang['newpass'], '', 'block', array('size' => '50')));
            $form->addElement(form_makePasswordField('passchk', $lang['passchk'], '', 'block', array('size' => '50')));
        }
        if($conf['profileconfirm']) {
            $form->addElement(form_makeTag('br'));
            $form->addElement(form_makePasswordField('oldpass', $lang['oldpass'], '', 'block', array('size' => '50', 'required' => 'required')));
        }
        $form->addElement(form_makeButton('submit', '', $lang['btn_save']));
        $form->addElement(form_makeButton('reset', '', $lang['btn_reset']));

        $form->endFieldset();
        return $form;
    }

    /**
     * Get the authentication token form
     * 
     * @param string $user
     * @return Form
     */
    protected function tokenForm($user) {
        global $lang;
        global $ID;

        $token = AuthenticationToken::fromUser($user);

        $form = new Form(['id' => 'dw__profiletoken', 'action'=>wl(), 'method'=>'POST']);
        $form->setHiddenField('do', 'authtoken');
        $form->setHiddenField('id', 'ID');
        $form->addFieldsetOpen($lang['proftokenlegend']);
        $form->addHTML('<p>'.$lang['proftokeninfo'].'</p>');
        $form->addHTML('<pre>'.$token->getToken().'</pre>');
        $form->addButton('regen', $lang['proftokengenerate']);
        $form->addFieldsetClose();

        return $form;
    }

    /**
     * Get the user deletion form
     *
     * @return \Doku_Form
     */
    protected function deletionForm() {
        /** @var \DokuWiki_Auth_Plugin $auth */
        global $auth;
        global $lang;
        global $conf;

        $form = new \Doku_Form(array('id' => 'dw__profiledelete'));
        $form->startFieldset($lang['profdeleteuser']);
        $form->addHidden('do', 'profile_delete');
        $form->addHidden('delete', '1');
        $form->addElement(form_makeCheckboxField('confirm_delete', '1', $lang['profconfdelete'], 'dw__confirmdelete', '', array('required' => 'required')));
        if($conf['profileconfirm']) {
            $form->addElement(form_makeTag('br'));
            $form->addElement(form_makePasswordField('oldpass', $lang['oldpass'], '', 'block', array('size' => '50', 'required' => 'required')));
        }
        $form->addElement(form_makeButton('submit', '', $lang['btn_deleteuser']));
        $form->endFieldset();

        return $form;
    }
}
