<?php

namespace dokuwiki\Utils;

/**
 * Creates an absolute media ID from a relative one
 */
class MediaResolver extends Resolver {

    /** @inheritDoc */
    public function resolveId($id, $rev = '', $isDateAt = false)
    {
        return cleanID(parent::resolveId($id, $rev, $isDateAt));
    }
}
