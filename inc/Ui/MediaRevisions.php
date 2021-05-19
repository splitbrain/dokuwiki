<?php

namespace dokuwiki\Ui;

use dokuwiki\ChangeLog\MediaChangeLog;
use dokuwiki\Form\Form;

/**
 * DokuWiki MediaRevisions Interface
 *
 * @package dokuwiki\Ui
 */
class MediaRevisions extends Revisions
{
    /* @var MediaChangeLog */
    protected $changelog;

    /** 
     * MediaRevisions Ui constructor
     *
     * @param string $id  id of media
     */
    public function __construct($id)
    {
        if (!$id) {
            throw new \InvalidArgumentException('media id should not be empty!');
        }
        $this->item = 'media';
        parent::__construct($id);
    }

    /** @inheritdoc */
    protected function setChangeLog()
    {
        $this->changelog = new MediaChangeLog($this->id);
    }

    /** @inheritdoc */
    protected function itemFN($id, $rev = '')
    {
        return mediaFN($id, $rev);
    }

    /**
     * Display a list of Media Revisions in the MediaManager
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     * @author Ben Coburn <btcoburn@silicodon.net>
     * @author Kate Arzamastseva <pshns@ukr.net>
     * @author Satoshi Sahara <sahara.satoshi@gmail.com>
     *
     * @param int $first  skip the first n changelog lines
     * @return void
     */
    public function show($first = 0)
    {
        global $lang;

        // get revisions, and set correct pagenation parameters (first, hasNext)
        if ($first === null) $first = 0;
        $hasNext = false;
        $revisions = $this->getRevisions($first, $hasNext);

        // create the form
        $form = new Form([
                'id' => 'page__revisions', // must not be "media__revisions"
                'action' => media_managerURL(['image' => $this->id], '&'),
                'class'  => 'changes',
        ]);
        $form->setHiddenField('mediado', 'diff'); // required for media revisions
        $form->addTagOpen('div')->addClass('no');

        // start listing
        $form->addTagOpen('ul');
        foreach ($revisions as $info) {
            $rev = $info['date'];
            $class = ($info['type'] === DOKU_CHANGE_TYPE_MINOR_EDIT) ? 'minor' : '';
            $form->addTagOpen('li')->addClass($class);
            $form->addTagOpen('div')->addClass('li');

            if (isset($info['current'])) {
               $form->addCheckbox('rev2[]')->val('current');
            } elseif (file_exists(mediaFN($this->id, $rev))) {
                $form->addCheckbox('rev2[]')->val($rev);
            } else {
                $form->addCheckbox('')->val($rev)->attr('disabled','disabled');
            }
            $form->addHTML(' ');

            $objRevInfo = $this->getObjRevInfo($info);
            $html = implode(' ', [
                $objRevInfo->editDate(),          // edit date and time
                $objRevInfo->difflink(),          // link to diffview icon
                $objRevInfo->itemName(),          // name of page or media
                '<div>',
                $objRevInfo->editSummary(),       // edit summary
                $objRevInfo->editor(),            // editor info
                $objRevInfo->sizechange(),        // size change indicator
                $objRevInfo->currentIndicator(),  // current indicator (only when k=1)
                '</div>',
            ]);
            $form->addHTML($html);

            $form->addTagClose('div');
            $form->addTagClose('li');
        }
        $form->addTagClose('ul');  // end of revision list

        // show button for diff view
        $form->addButton('do[diff]', $lang['diff2'])->attr('type', 'submit');

        $form->addTagClose('div'); // close div class=no

        print $form->toHTML('Revisions');

        // provide navigation for pagenated revision list (of pages and/or media files)
        print $this->navigation($first, $hasNext, function ($n) {
            return media_managerURL(['first' => $n], '&', false, true);
        });
    }

}
