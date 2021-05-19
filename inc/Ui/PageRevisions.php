<?php

namespace dokuwiki\Ui;

use dokuwiki\ChangeLog\PageChangeLog;
use dokuwiki\Form\Form;

/**
 * DokuWiki PageRevisions Interface
 *
 * @package dokuwiki\Ui
 */
class PageRevisions extends Revisions
{
    /* @var PageChangeLog */
    protected $changelog;

    /** 
     * PageRevisions Ui constructor
     *
     * @param string $id  id of page
     */
    public function __construct($id = null)
    {
        global $INFO;
        if (!isset($id)) $id = $INFO['id'];
        $this->item = 'page';
        parent::__construct($id);
    }

    /** @inheritdoc */
    protected function setChangeLog()
    {
        $this->changelog = new PageChangeLog($this->id);
    }

    /** @inheritdoc */
    protected function itemFN($id, $rev = '')
    {
        return wikiFN($id, $rev);
    }

    /**
     * Display list of old revisions of the page
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
        global $lang, $REV;

        // get revisions, and set correct pagenation parameters (first, hasNext)
        if ($first === null) $first = 0;
        $hasNext = false;
        $revisions = $this->getRevisions($first, $hasNext);

        // print intro
        print p_locale_xhtml('revisions');

        // create the form
        $form = new Form([
                'id' => 'page__revisions',
                'class' => 'changes',
        ]);
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
            } elseif ($rev == $REV) {
                $form->addCheckbox('rev2[]')->val($rev)->attr('checked','checked');
            } elseif (page_exists($this->id, $rev)) {
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
                $objRevInfo->editSummary(),       // edit summary
                $objRevInfo->editor(),            // editor info
                $objRevInfo->sizechange(),        // size change indicator
                $objRevInfo->currentIndicator(),  // current indicator (only when k=1)
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
            return array('do' => 'revisions', 'first' => $n);
        });
    }

    /**
     * Get revisions, and set correct pagenation parameters (first, hasNext)
     *
     * @param int  $first
     * @param bool $hasNext
     * @return array  revisions to be shown in a pagenated list
     * @see also https://www.dokuwiki.org/devel:changelog
     */
    protected function getRevisions(&$first, &$hasNext)
    {
        global $INFO, $conf;

        if ($this->id != $INFO['id']) {
            return parent::getRevisions($first, $hasNext);
        }

        $changelog =& $this->changelog;

        $revisions = [];

        /* we need to get one additional log entry to be able to
         * decide if this is the last page or is there another one.
         * see also Ui\Recent::getRecents()
         */
        $revlist = $changelog->getRevisions($first, $conf['recent'] +1);
        if (count($revlist) == 0 && $first != 0) {
            $first = 0;
            $revlist = $changelog->getRevisions($first, $conf['recent'] +1);
        }
        $exists = $INFO['exists'];
        if ($first == 0 && $exists) {
            // add current page as revision[0]
            $revisions[] = array(
                'date' => $INFO['lastmod'],
                'ip'   => null,
                'type' => $INFO['meta']['last_change']['type'],
                'id'   => $INFO['id'],
                'user' => $INFO['editor'],
                'sum'  => $INFO['sum'],
                'extra' => null,
                'sizechange' => $INFO['meta']['last_change']['sizechange'],
                'item' => $this->item,
                'current' => true,
            );
        }

        // decide if this is the last page or is there another one
        $hasNext = false;
        if (count($revlist) > $conf['recent']) {
            $hasNext = true;
            array_pop($revlist); // remove one additional log entry
        }

        // append each revison info array to the revisions
        foreach ($revlist as $rev) {
            $revisions[] = $changelog->getRevisionInfo($rev) + array('item' => $this->item);
        }
        return $revisions;
    }

}
