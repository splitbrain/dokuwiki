<?php

namespace dokuwiki\Ui;

use dokuwiki\Extension\Event;
use dokuwiki\Form\Form;

/**
 * DokuWiki Page Editor
 *
 * @package dokuwiki\Ui
 */
class Editor extends Ui
{
    /**
     * Display the Edit Window
     * preprocess edit form data
     *
     * @author   Andreas Gohr <andi@splitbrain.org>
     *
     * @triggers EDIT_FORM_ADDTEXTAREA
     * @return void
     */
    public function show()
    {
        global $INPUT;
        global $ID;
        global $REV;
        global $DATE;
        global $PRE;
        global $SUF;
        global $INFO;
        global $SUM;
        global $lang;
        global $conf;
        global $TEXT;

        global $license;

        if ($INPUT->has('changecheck')) {
            $check = $INPUT->str('changecheck');
        } elseif (!$INFO['exists']) {
            // $TEXT has been loaded from page template
            $check = md5('');
        } else {
            $check = md5($TEXT);
        }
        $mod = (md5($TEXT) !== $check);

        $wr = $INFO['writable'] && !$INFO['locked'];

        // intro locale text (edit, rditrev, or read)
        if ($wr) {
            $intro = ($REV) ? 'editrev' : 'edit';
        } else {
            // check pseudo action 'source'
            if (!actionOK('source')) {
                msg('Command disabled: source', -1);
                return;
            }
            $intro = 'read';
        }

        // create the Editor form
        $form = new Form(['id' => 'dw__editform']);
        $form->setHiddenField('id', $ID);
        $form->setHiddenField('rev', $REV);
        $form->setHiddenField('date', $DATE);
        $form->setHiddenField('prefix', $PRE .'.');
        $form->setHiddenField('suffix', $SUF);
        $form->setHiddenField('changecheck', $check);

        // prepare data for EDIT_FORM_ALTERNATE event
        $data = array(
            'form' => $form,
            'wr'   => $wr,
            'media_manager' => true,
            'target' => ($INPUT->has('target') && $wr) ? $INPUT->str('target') : 'section',
            'intro_locale' => $intro,
        );

        $this->addToolbar($data, $wr);

        if ($data['target'] !== 'section') {
            // Only emit event if page is writable, section edit data is valid and
            // edit target is not section.
            Event::createAndTrigger('EDIT_FORM_ADDTEXTAREA', $data, [$this,'addTextarea'], true);
        } else {
            $this->addTextarea($data);
        }

        $form->setHiddenField('target', $data['target']);

        if ($INPUT->has('hid')) {
            $form->setHiddenField('hid', $INPUT->str('hid'));
        }
        if ($INPUT->has('codeblockOffset')) {
            $form->setHiddenField('codeblockOffset', $INPUT->str('codeblockOffset'));
        }

        $form->addTagOpen('div')->id('wiki__editbar')->addClass('editBar');

        $form->addTagOpen('div')->id('size__ctl');
        $form->addTagClose('div');

        if ($wr) {
            // add edit buttons: save, preview, cancel
            $form->addTagOpen('div')->addClass('editButtons');
            $form->addButton('do[save]', $lang['btn_save'])->attr('type', 'submit')
                ->attrs(['accesskey' => 's', 'tabindex' => '4'])
                ->id('edbtn__save');
            $form->addButton('do[preview]', $lang['btn_preview'])->attr('type', 'submit')
                ->attrs(['accesskey' => 'p', 'tabindex' => '5'])
                ->id('edbtn__preview');
            $form->addButton('do[cancel]', $lang['btn_cancel'])->attr('type', 'submit')
                ->attrs(['tabindex' => '6']);
            $form->addTagClose('div'); // close div editButtons class

            // add a textbox for edit summary
            $form->addTagOpen('div')->addClass('summary');
            $input = $form->addTextInput('summary', $lang['summary'])
                ->attrs(['size' => '50', 'tabindex' => '2'])
                ->id('edit__summary')->addClass('edit')
                ->val($SUM);
            $input->getLabel()->attr('class', 'nowrap');

            // adds a checkbox for minor edits for logged in users
            if ($conf['useacl'] && $INPUT->server->str('REMOTE_USER')) {
                $form->addHTML(' ');
                $form->addCheckbox('minor', $lang['minoredit'])->id('edit__minoredit')->addClass('nowrap')->val('1');
            }
            $form->addTagClose('div'); // close div summary class
        }

        $form->addTagClose('div'); // close div editBar class

        // license note
        if ($wr && $conf['license']) {
            $attr = array(
                'href'   => $license[$conf['license']]['url'],
                'rel'    => 'license',
                'class'  => 'urlextern',
                'target' => $conf['target']['extern'] ? $conf['target']['extern'] : '',
            );
            $form->addTagOpen('div')->addClass('license');
            $form->addHTML($lang['licenseok']
                .' <a '.buildAttributes($attr, true).'>'.$license[$conf['license']]['name'].'</a>'
            );
            $form->addTagClose('div');
        }

        // start editor html output
        if ($wr) {
            // sets changed to true when previewed
            echo '<script>/*<![CDATA[*/'.'textChanged = '. ($mod ? 'true' : 'false') .'/*!]]>*/</script>';
        }

        // print intro locale text (edit, rditrev, or read.txt)
        if (isset($data['intro_locale'])) {
            echo p_locale_xhtml($data['intro_locale']);
        }

        echo '<div class="editBox" role="application">';

        echo '<div id="draft__status" class="draft__status">';
        $draft = new \dokuwiki\Draft($ID, $INFO['client']);
        if ($draft->isDraftAvailable()) {
            echo $draft->getDraftMessage();
        }
        echo '</div>';

        echo $form->toHTML('Edit');

        echo '</div>'; // close div editBox class
    }

    /**
     * Display the default edit form (textarea)
     *
     * the default action for EDIT_FORM_ADDTEXTAREA
     *
     * @param mixed[] $data
     */
    public function addTextarea(&$data)
    {
        global $TEXT;

        if ($data['target'] !== 'section') {
            msg('No editor for edit target '. hsc($data['target']) .' found.', -1);
        }

        // set textarea attributes
        $attr = array('tabindex' => '1');
        if (!$data['wr']) $attr['readonly'] = 'readonly';
        $attr['dir']  = 'auto';
        $attr['cols'] = '80';
        $attr['rows'] = '10';

        /** @var dokuwiki\Form\Form $data['form'] */
        $data['form']->addTextarea('wikitext','')->attrs($attr)->val($TEXT)
                ->id('wiki__text')->addClass('edit');
    }

    /**
     * Display the toolbar
     *
     * @param mixed[] $data
     * @param bool $wr
     */
    public function addToolbar(&$data, $wr)
    {
        global $INFO;

        $html = '<div class="toolbar group">';
        $html .= '<div id="tool__bar" class="tool__bar">';
        if ($wr && $data['media_manager']) {
            $html .= '<a href="'.DOKU_BASE.'lib/exe/mediamanager.php?ns='.$INFO['namespace'].'" target="_blank">';
            $html .= $lang['mediaselect'];
            $html .= '</a>';
        }
        $html .= '</div>';
        $html .= '</div>';

        /** @var dokuwiki\Form\Form $data['form'] */
        $data['form']->addHTML($html);
    }
}
