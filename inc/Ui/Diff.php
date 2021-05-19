<?php

namespace dokuwiki\Ui;

/**
 * DokuWiki Diff Interface
 * parent class of PageDiff and MediaDiff
 *
 * @package dokuwiki\Ui
 */
abstract class Diff extends Ui
{
    /* @var string */
    protected $id;   // page id or media id
    protected $item; // page or media

    /* @var int|string */
    protected $oldRev;  // timestamp of older revision, '' means current one
    protected $newRev;  // timestamp of newer revision, '' means current one

    /* @var array */
    protected $preference = [];

    /* @var ChangeLog */
    protected $changelog; // PageChangeLog or MediaChangeLog object

    /**
     * Diff Ui constructor
     *
     * @param string $id  page id or media id
     */
    public function __construct($id)
    {
        $this->id = $id;
        $this->setChangeLog();
    }

    /**
     * set class property changelog
     */
    abstract protected function setChangeLog();

    /**
     * item filename resolver
     *
     * @param string $id  page id or media id
     * @param int|string $rev revision timestamp, or empty string for current one
     * @return string full path
     */
    abstract protected function itemFN($id, $rev = '');

    /**
     * Set a pair of revisions to be compared
     *
     * @param int $oldRev
     * @param int $newRev
     * @return $this
     */
    public function compare($oldRev, $newRev)
    {
        $this->oldRev = $oldRev;
        $this->newRev = $newRev;
        return $this;
    }

    /**
     * Gets or Sets preference of the Ui\Diff object
     *
     * @param string|array $prefs  a key name or key-value pair(s)
     * @param mixed $value         value used when the first args is string
     * @return array|$this
     */
    public function preference($prefs = null, $value = null)
    {
        // set
        if (is_string($prefs) && isset($value)) {
            $this->preference[$prefs] = $value;
            return $this;
        } elseif (is_array($prefs)) {
            foreach ($prefs as $name => $value) {
                $this->preference[$name] = $value;
            }
            return $this;
        }
        // get
        return $this->preference;
    }

    /**
     * Retrieve requested revision(s) and difftype from Ui\Revisions
     *
     * @return void
     */
    protected function preProcess()
    {
        global $INPUT;

        // difflink icon click, eg. ?rev=123456789&do=diff
        if ($INPUT->has('rev')) {
            $this->oldRev = $INPUT->int('rev');
            $this->newRev = ''; // current revision
        }

        // submit button with two checked boxes
        $rev2 = $INPUT->arr('rev2', []);
        if (count($rev2) > 1) {
            if ($rev2[0] == 'current') {
                [$this->oldRev, $this->newRev] = [$rev2[1], ''];
            } elseif ($rev2[1] == 'current') {
                [$this->oldRev, $this->newRev] = [$rev2[0], ''];
            } elseif ($rev2[0] < $rev2[1]) {
                [$this->oldRev, $this->newRev] = [$rev2[0], $rev2[1]];
            } else {
                [$this->oldRev, $this->newRev] = [$rev2[1], $rev2[0]];
            }
        }

        // diff view type
        if ($INPUT->has('difftype')) {
            // retrieve requested $difftype
            $this->preference['difftype'] = $INPUT->str('difftype');
        } else {
            // read preference from DokuWiki cookie. PageDiff only
            $mode = get_doku_pref('difftype', $mode = null);
            if (isset($mode)) $this->preference['difftype'] = $mode;
        }
    }

    /**
     * get extended revision info
     *
     * @param int|string $rev  revision identifier, '' means current one
     * @return array  revision info structure of a page or media file
     */
    protected function getExtendedRevisionInfo($rev)
    {
        $changelog =& $this->changelog;

        if ($rev) {
            $info = $changelog->getRevisionInfo($rev);
        } elseif (file_exists($filename = $this->itemFN($this->id))) {
            $rev = filemtime(fullpath($filename));
            $info = $changelog->getRevisionInfo($rev) + array(
                'current' => true,
            );
        } else { // once exists, but now removed
            $info = array(
                'current' => true,
            );
        }
        return array('item' => $this->item) + $info;
    }



    /**
     * Build header of diff HTML
     *
     * @param string $l_rev   Left revisions
     * @param string $r_rev   Right revision
     * @return string[] HTML snippets for diff header
     * @deprecated 2020-12-31
     */
    public function buildDiffHead($l_rev, $r_rev)
    {
        dbg_deprecated('not used see '. \dokuwiki\Ui\PageDiff::class .'::show()');
    }

}
