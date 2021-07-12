<?php

namespace dokuwiki\Utils;

/**
 * Creates an absolute page ID from a relative one
 */
class PageResolver extends Resolver
{

    /**
     * Resolves a given ID to be absolute
     *
     * This handles all kinds of relative shortcuts, startpages and autoplurals
     * @inheritDoc
     */
    public function resolveId($id, $rev = '', $isDateAt = false)
    {
        global $conf;

        // pages may have a hash attached, we separate it on resolving
        if (strpos($id, '#') !== false) {
            list($id, $hash) = explode('#', $id, 2);
            $hash = cleanID($hash);
        } else {
            $hash = '';
        }

        if ($id !== '') {
            $id = parent::resolveId($id, $rev, $isDateAt);
            $id = $this->resolveStartPage($id, $rev, $isDateAt);
            if ($conf['autoplural']) {
                $id = $this->resolveAutoPlural($id, $rev, $isDateAt);
            }
        } else {
            $id = $this->contextID;
        }

        $id = cleanID($id); // FIXME always? or support parameter
        // readd hash if any
        if ($hash !== '') $id .= "#$hash";
        return $id;
    }

    /**
     * IDs ending in :
     *
     * @param string $id
     * @param int $rev
     * @param bool $isDateAt
     * @return string
     */
    protected function resolveStartPage($id, $rev, $isDateAt)
    {
        global $conf;

        if ($id[-1] !== ':') return $id;

        if (page_exists($id . $conf['start'], $rev, true, $isDateAt)) {
            // start page inside namespace
            $id = $id . $conf['start'];
        } elseif (page_exists($id . noNS(cleanID($id)), $rev, true, $isDateAt)) {
            // page named like the NS inside the NS
            $id = $id . noNS(cleanID($id));
        } elseif (!page_exists($id, $rev, true, $isDateAt)) { #FIXME is this correct?
            // page like namespace does not exist, fall back to default
            $id = $id . $conf['start'];
        }

        return $id;
    }

    /**
     * Try alternative plural/singular form
     *
     * @param string $id
     * @param int $rev
     * @param bool $isDateAt
     * @return string
     */
    protected function resolveAutoPlural($id, $rev, $isDateAt)
    {
        if (page_exists($id, $rev, $isDateAt)) return $id;

        if ($id[-1] === 's') {
            $try = substr($id, 0, -1);
        } else {
            $try = $id . 's';
        }

        if (page_exists($try, $rev, true, $isDateAt)) {
            return $try;
        }
        return $id;
    }

}
