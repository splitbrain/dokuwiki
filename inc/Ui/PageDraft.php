<?php

namespace dokuwiki\Ui;

use dokuwiki\Form\Form;

/**
 * DokuWiki Page Draft Interface
 *
 * @package dokuwiki\Ui
 */
class PageDraft extends Ui
{
    /**
     * Display the Page Draft Form
     * ask the user about how to handle an exisiting draft
     *
     * @author   Andreas Gohr <andi@splitbrain.org>
     *
     * @return void
     */
    public function show()
    {
        global $INFO;
        global $lang;

        $draft = new \dokuwiki\Draft($INFO['id'], $INFO['client']);
        $text  = $draft->getDraftText();

        // print intro
        print p_locale_xhtml('draft');

        // print difference
        (new PageDiff($INFO['id']))->compareWith($text)->preference('showIntro', false)->show();

        // create the draft form
        $form = new Form(['id' => 'dw__editform']);
        $form->addTagOpen('div')->addClass('no');
        $form->setHiddenField('id', $INFO['id']);
        $form->setHiddenField('date', $draft->getDraftDate());
        $form->setHiddenField('wikitext', $text);

        $form->addTagOpen('div')->id('draft__status');
        $form->addHTML($draft->getDraftMessage());
        $form->addTagClose('div');
        $form->addButton('do[recover]',  $lang['btn_recover'] )->attrs(['type' => 'submit', 'tabindex' => '1']);
        $form->addButton('do[draftdel]', $lang['btn_draftdel'])->attrs(['type' => 'submit', 'tabindex' => '2']);
        $form->addButton('do[show]',     $lang['btn_cancel']  )->attrs(['type' => 'submit', 'tabindex' => '3']);
        $form->addTagClose('div');

        print $form->toHTML('Draft');
    }

}
