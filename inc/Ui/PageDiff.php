<?php

namespace dokuwiki\Ui;

use dokuwiki\ChangeLog\PageChangeLog;
use dokuwiki\Ui\PageRevisions;
use dokuwiki\Form\Form;

/**
 * DokuWiki PageDiff Interface
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 * @author Satoshi Sahara <sahara.satoshi@gmail.com>
 * @package dokuwiki\Ui
 */
class PageDiff extends Diff
{
    /* @var PageChangeLog */
    protected $changelog;

    /* @var string */
    protected $text;

    /**
     * PageDiff Ui constructor
     *
     * @param string $id  page id
     */
    public function __construct($id = null)
    {
        global $INFO;
        if (!isset($id)) $id = $INFO['id'];
        $this->item = 'page';

        // init preference
        $this->preference['showIntro'] = true;
        $this->preference['difftype'] = 'sidebyside'; // diff view type: inline or sidebyside

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
     * Set text to be compared with most current version
     * exclusively use of the compare($old, $new) method
     *
     * @param string $text
     * @return $this
     */
    public function compareWith($text = null)
    {
        if (isset($text)) {
            $this->text = $text;
            $this->oldRev = '';
            $this->newRev = null;
        }
        return $this;
    }

    /** @inheritdoc */
    protected function preProcess()
    {
        parent::preProcess();
        if (!isset($this->oldRev, $this->newRev)) {
            // no revision was given, compare previous to current
            $this->oldRev = $this->changelog->getRevisions(0, 1)[0];
            $this->newRev = '';

            global $INFO, $REV;
            if ($this->id == $INFO['id'])
               $REV = $this->oldRev; // store revision back in $REV
        }
    }

    /**
     * Show diff
     * between current page version and provided $text
     * or between the revisions provided via GET or POST
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     *
     * @return void
     */
    public function show()
    {
        global $INFO, $lang;

       // determine left and right revision
        if (!isset($this->oldRev)) $this->preProcess();

        // create difference engine object
        if (isset($this->text)) { // compare text to the most current revision
            $oldText = rawWiki($this->id, '');
            $newText = cleanText($this->text);
        } else {
            // when both revisions are empty then the page was created just now
            $oldText = (!$this->oldRev && !$this->newRev) ? '' : rawWiki($this->id, $this->oldRev);
            $newText = rawWiki($this->id, $this->newRev); // empty when removed page
        }
        $Difference = new \Diff(explode("\n", $oldText), explode("\n", $newText));

        // revison info of older page (left side)
        $oldRevInfo = $this->getExtendedRevisionInfo($this->oldRev);

        // revison info of newer page (right side)
        if (isset($this->text)) {
            $newRevInfo = array('date' => null);
        } else {
            $newRevInfo = $this->getExtendedRevisionInfo($this->newRev);
        }

        // determin exact revision identifiers, even for current page
        $oldRev = $oldRevInfo['date'];
        $newRev = $newRevInfo['date'];

        // build paired navigation
        $navOlderRevisions = '';
        $navNewerRevisions = '';
        if (!isset($this->text)) {
            list(
                $navOlderRevisions,
                $navNewerRevisions,
            ) = $this->buildRevisionsNavigation($oldRev, $newRev);
        }

        // display intro
        if ($this->preference['showIntro']) echo p_locale_xhtml('diff');

        // print form to choose diff view type, and exact url reference to the view
        if (!isset($this->text)) {
            $this->showDiffViewSelector($oldRev, $newRev);
        }

        // assign minor edit checker to the variable
        $classEditType = function ($info) {
            return ($info['type'] === DOKU_CHANGE_TYPE_MINOR_EDIT) ? ' class="minor"' : '';
        };

        // display diff view table
        echo '<div class="table">';
        echo '<table class="diff diff_'.$this->preference['difftype'] .'">';

        //navigation and header
        switch ($this->preference['difftype']) {
            case 'inline':
                if (!isset($this->text)) {
                    echo '<tr>'
                        .'<td class="diff-lineheader">-</td>'
                        .'<td class="diffnav">'. $navOlderRevisions .'</td>'
                        .'</tr>';
                    echo '<tr>'
                        .'<th class="diff-lineheader">-</th>'
                        .'<th'.$classEditType($oldRevInfo).'>'.$this->revisionTitle($oldRevInfo).'</th>'
                        .'</tr>';
                }
                echo '<tr>'
                    .'<td class="diff-lineheader">+</td>'
                    .'<td class="diffnav">'. $navNewerRevisions .'</td>'
                    .'</tr>';
                echo '<tr>'
                    .'<th class="diff-lineheader">+</th>'
                    .'<th'.$classEditType($newRevInfo).'>'.$this->revisionTitle($newRevInfo).'</th>'
                    .'</tr>';
                // create formatter object
                $DiffFormatter = new \InlineDiffFormatter();
                break;

            case 'sidebyside':
            default:
                if (!isset($this->text)) {
                    echo '<tr>'
                        .'<td colspan="2" class="diffnav">'. $navOlderRevisions .'</td>'
                        .'<td colspan="2" class="diffnav">'. $navNewerRevisions .'</td>'
                        .'</tr>';
                }
                echo '<tr>'
                    .'<th colspan="2"'.$classEditType($oldRevInfo).'>'.$this->revisionTitle($oldRevInfo).'</th>'
                    .'<th colspan="2"'.$classEditType($newRevInfo).'>'.$this->revisionTitle($newRevInfo).'</th>'
                    .'</tr>';
                // create formatter object
                $DiffFormatter = new \TableDiffFormatter();
                break;
        }

        // output formatted difference
        echo $this->insertSoftbreaks($DiffFormatter->format($Difference));

        echo '</table>';
        echo '</div>';
    }

    /**
     * Revision Title for PageDiff table headline
     *
     * @param array $info  Revision info structure of a page
     * @return string
     */
    protected function revisionTitle(array $info)
    {
        global $lang, $INFO;

        // use designated title when compare current page source with given text
        if (array_key_exists('date', $info) && is_null($info['date'])) {
            return $lang['yours'];
        }

        if (isset($info['date'])) {
            $rev = $info['date'];
            $title = '<bdi><a class="wikilink1" href="'.wl($this->id, ['rev' => $rev]).'">'
                   . $this->id.' ['.dformat($rev).']'.'</a></bdi>';
        } else {
            $rev = false;
            $title = '&mdash;';
        }
        if (isset($info['current']) || ($rev && $rev == $INFO['currentrev'])) {
            $title .= '&nbsp;('.$lang['current'].')';
        }

        // append separator
        $title .= ($this->preference['difftype'] === 'inline') ? ' ' : '<br />';

        // supplement
        if (isset($info['date'])) {
            $objRevInfo = (new PageRevisions($this->id))->getObjRevInfo($info);
            $title .= $objRevInfo->editSummary().' '.$objRevInfo->editor();
        }
        return $title;
    }

    /**
     * Print form to choose diff view type, and exact url reference to the view
     *
     * @param int $oldRev  timestamp of older revision, left side
     * @param int $newRev  timestamp of newer revision, right side
     */
    protected function showDiffViewSelector($oldRev, $newRev)
    {
        global $lang;

        echo '<div class="diffoptions group">';

        // create the form to select difftype
        $form = new Form(['action' => wl()]);
        $form->setHiddenField('id', $this->id);
        $form->setHiddenField('rev2[0]', $this->oldRev ?: 'current');
        $form->setHiddenField('rev2[1]', $this->newRev ?: 'current');
        $form->setHiddenField('do', 'diff');
        $options = array(
                     'sidebyside' => $lang['diff_side'],
                     'inline' => $lang['diff_inline'],
        );
        $input = $form->addDropdown('difftype', $options, $lang['diff_type'])
            ->val($this->preference['difftype'])
            ->addClass('quickselect');
        $input->useInput(false); // inhibit prefillInput() during toHTML() process
        $form->addButton('do[diff]', 'Go')->attr('type','submit');
        echo $form->toHTML();

        // show exact url reference to the view when it is meaningful
        echo '<p>';
        if (!isset($this->text) && $oldRev && $newRev) {
            // link to exactly this view FS#2835
            $viewUrl = $this->diffViewlink('difflink', $oldRev, $newRev);
        }
        echo $viewUrl ?? '<br />';
        echo '</p>';

        echo '</div>'; // .diffoptions
    }

    /**
     * Create html for revision navigation
     *
     * The navigation consists of older and newer revisions selectors, each
     * state mutually depends on the selected revision of opposite side.
     *
     * @param int $oldRev  timestamp of older revision, older side
     * @param int $newRev  timestamp of newer revision, newer side
     * @return string[] html of navigation for both older and newer sides
     */
    protected function buildRevisionsNavigation($oldRev, $newRev)
    {
        global $INFO;

        $changelog =& $this->changelog;

       // determine the last revision, which is usually the timestamp of current page,
       // however which might be the last revision if the page had removed.
        if (!$newRev) {
            if ($this->id == $INFO['id']) {
                // note: when page is removed, the metadata timestamp is zero
                $lastRev = $INFO['currentrev'] ?? $INFO['meta']['last_change']['date'] ?? 0;
            } else {
                $lastRevs = $changelog->getRevisions(-1, 1)  // empty array for removed page
                          ?: $changelog->getRevisions(0, 1); // last entry of changelog
                $lastRev = count($lastRevs) > 0 ? $lastRevs[0] : 0;
            }
            $newRev = $lastRev;
        }

        // retrieve revisions with additional info
        list($oldRevs, $newRevs) = $changelog->getRevisionsAround($oldRev, $newRev);

        // build options for dropdown selector 
        $olderRevisions = $this->buildRevisionOptions('older', $oldRevs, $oldRev, $newRev);
        $newerRevisions = $this->buildRevisionOptions('newer', $newRevs, $oldRev, $newRev);

        //determine previous/next revisions
        $index = array_search($oldRev, $oldRevs);
        $oldPrevRev = $oldRevs[$index + 1];
        $oldNextRev = $oldRevs[$index - 1];
        if ($newRev) {
            $index = array_search($newRev, $newRevs);
            $newPrevRev = $newRevs[$index + 1];
            $newNextRev = $newRevs[$index - 1];
        } else {
            //removed page
            $newPrevRev = ($oldNextRev) ? $newRevs[0] : null;
            $newNextRev = null;
        }

        /*
         * navigation UI for older revisions / Left side:
         */
        $navOlderRevs = '';
        //move back
        if ($oldPrevRev) {
            $navOlderRevs .= $this->diffViewlink('diffbothprevrev', $oldPrevRev, $newPrevRev);
            $navOlderRevs .= $this->diffViewlink('diffprevrev', $oldPrevRev, $newRev);
        }
        //dropdown
        $navOlderRevs .= $this->buildDropdownSelector('older', $olderRevisions, $oldRev, $newRev);
        //move forward
        if ($oldNextRev && ($oldNextRev < $newRev || !$newRev)) {
            $navOlderRevs .= $this->diffViewlink('diffnextrev', $oldNextRev, $newRev);
        }

        /*
         * navigation UI for newer revisions / Right side:
         */
        $navNewerRevs = '';
        //move back
        if ($oldRev < $newPrevRev) {
            $navNewerRevs .= $this->diffViewlink('diffprevrev', $oldRev, $newPrevRev);
        }
        //dropdown
        $navNewerRevs .= $this->buildDropdownSelector('newer', $newerRevisions, $oldRev, $newRev);
        //move forward
        if ($newNextRev) {
            if ($changelog->isCurrentRevision($newNextRev)) {
                //last revision is diff with current page
                $navNewerRevs .= $this->diffViewlink('difflastrev', $oldRev);
            } else {
                $navNewerRevs .= $this->diffViewlink('diffnextrev', $oldRev, $newNextRev);
            }
            $navNewerRevs .= $this->diffViewlink('diffbothnextrev', $oldNextRev, $newNextRev);
        }
        return array($navOlderRevs, $navNewerRevs);
    }

    /**
     * prepare options for dropdwon selector
     *
     * @params string $side  "older" or "newer"
     * @params array $revs  list of revsion
     * @param int $oldRev  timestamp of older revision, left side
     * @param int $newRev  timestamp of newer revision, right side
     * @return array
     */
    protected function buildRevisionOptions($side, $revs, $oldRev, $newRev)
    {
        $changelog =& $this->changelog;
        $revisions = array();

        if (($side == 'older' && !$oldRev) // NOTE: this case should not happen!
          ||($side == 'newer' && (!$newRev || !page_exists($this->id)))
        ) {
            //no revision given, likely removed page, add dummy entry
            $revisions['current'] = array(
                'label' => '—', // U+2014 &mdash;
                'attrs' => [],
            );
        }

        foreach ($revs as $rev) {
            $info = $changelog->getRevisionInfo($rev);
            $revisions[$rev] = array(
                'label' => implode(' ', [
                            dformat($info['date']),
                            editorinfo($info['user'], true),
                            $info['sum'],
                           ]),
                'attrs' => ['title' => $rev],
            );
            if (($side == 'older' && ($newRev ? $rev >= $newRev : false))
              ||($side == 'newer' && ($rev <= $oldRev))
            ) {
                $revisions[$rev]['attrs']['disabled'] = 'disabled';
            }
        }
        return $revisions;
    }

    /**
     * build Dropdown form for revisions navigation
     *
     * @params string $side  "older" or "newer"
     * @params array $options  dropdown options
     * @param int $oldRev  timestamp of older revision, left side
     * @param int $newRev  timestamp of newer revision, right side
     * @return sting
     */
    protected function buildDropdownSelector($side, $options, $oldRev, $newRev)
    {
        $form = new Form(['action' => wl($this->id)]);
        $form->setHiddenField('id', $this->id);
        $form->setHiddenField('do', 'diff');
        $form->setHiddenField('difftype', $this->preference['difftype']);

        switch ($side) {
            case 'older': // left side
                $form->setHiddenField('rev2[1]', $newRev ?: 'current');
                $input = $form->addDropdown('rev2[0]', $options)
                    ->val($oldRev ?: 'current')->addClass('quickselect');
                $input->useInput(false); // inhibit prefillInput() during toHTML() process
                break;
            case 'newer': // right side
                $form->setHiddenField('rev2[0]', $oldRev ?: 'current');
                $input = $form->addDropdown('rev2[1]', $options)
                    ->val($newRev ?: 'current')->addClass('quickselect');
                $input->useInput(false); // inhibit prefillInput() during toHTML() process
                break;
        }
        $form->addButton('do[diff]', 'Go')->attr('type','submit');
        return $form->toHTML();
    }

    /**
     * Create html link to a diff view defined by two revisions
     *
     * @param string $linktype
     * @param int $oldRev older revision
     * @param int $newRev newer revision or null for diff with current revision
     * @return string html of link to a diff view
     */
    protected function diffViewlink($linktype, $oldRev, $newRev = null)
    {
        global $lang;
        if ($newRev === null) {
            $urlparam = array(
                'do' => 'diff',
                'rev' => $oldRev,
                'difftype' => $this->preference['difftype'],
            );
        } else {
            $urlparam = array(
                'do' => 'diff',
                'rev2[0]' => $oldRev,
                'rev2[1]' => $newRev,
                'difftype' => $this->preference['difftype'],
            );
        }
        $attr = array(
            'class' => $linktype,
            'href'  => wl($this->id, $urlparam, true, '&'),
            'title' => $lang[$linktype],
        );
        return '<a '. buildAttributes($attr) .'><span>'. $lang[$linktype] .'</span></a>';
    }


    /**
     * Insert soft breaks in diff html
     *
     * @param string $diffhtml
     * @return string
     */
    public function insertSoftbreaks($diffhtml)
    {
        // search the diff html string for both:
        // - html tags, so these can be ignored
        // - long strings of characters without breaking characters
        return preg_replace_callback('/<[^>]*>|[^<> ]{12,}/', function ($match) {
            // if match is an html tag, return it intact
            if ($match[0][0] == '<') return $match[0];
            // its a long string without a breaking character,
            // make certain characters into breaking characters by inserting a
            // word break opportunity (<wbr> tag) in front of them.
            $regex = <<< REGEX
(?(?=              # start a conditional expression with a positive look ahead ...
&\#?\\w{1,6};)     # ... for html entities - we don't want to split them (ok to catch some invalid combinations)
&\#?\\w{1,6};      # yes pattern - a quicker match for the html entity, since we know we have one
|
[?/,&\#;:]         # no pattern - any other group of 'special' characters to insert a breaking character after
)+                 # end conditional expression
REGEX;
            return preg_replace('<'.$regex.'>xu', '\0<wbr>', $match[0]);
        }, $diffhtml);
    }

}
