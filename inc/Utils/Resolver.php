<?php

namespace dokuwiki\Utils;

/**
 * Resolving relative IDs to absolute ones
 */
abstract class Resolver
{

    /** @var string context page ID */
    protected $contextID;
    /** @var string namespace of context page ID */
    protected $contextNS;

    /**
     * @param string $contextID the current pageID that's the context to resolve relative IDs to
     */
    public function __construct($contextID)
    {
        $this->contextID = $contextID;
        $this->contextNS = (string)getNS($contextID);
    }

    /**
     * Resolves a given ID to be absolute
     *
     * @param string $id The ID to resolve
     * @param string|int|false $rev The revision time to use when resolving
     * @param bool $isDateAt Is the given revision only a datetime hint not an exact revision?
     * @return string
     */
    public function resolveId($id, $rev = '', $isDateAt = false)
    {
        global $conf;

        // some pre cleaning for useslash:
        if ($conf['useslash']) $id = str_replace('/', ':', $id);
        // on some systems, semicolons might be used instead of colons:
        $id = str_replace(';', ':', $id);

        $id = $this->resolvePrefix($id);
        $id = $this->resolveRelatives($id);

        return $id;
    }

    /**
     * Handle IDs starting with . or ~ and prepend the proper prefix
     *
     * @param string $id
     * @return string
     */
    protected function resolvePrefix($id)
    {
        // relative to current page (makes the current page a start page)
        if ($id[0] === '~') {
            $id = $this->contextID . ':' . substr($id, 1);
        }

        // relative to current namespace
        if ($id[0] === '.') {
            // normalize initial dots without a colon
            $id = preg_replace('/^((\.+:)*)(\.+)(?=[^:\.])/', '\1\3:', $id);
            $id = $this->contextNS . ':' . $id;
        }

        // auto-relative, because there is a context namespace but no namespace in the ID
        if ($this->contextID !== '' && strpos($id, ':') === false) {
            $id = $this->contextNS . ':' . $id;
        }

        return $id;
    }

    /**
     * Handle . and .. within IDs
     *
     * @param string $id
     * @return string
     */
    protected function resolveRelatives($id)
    {
        // cleanup relatives
        $result = array();
        $parts = explode(':', $id);
        if (!$parts[0]) $result[] = ''; // FIXME is this necessary? what's it for? should it be type checked?
        foreach ($parts AS $key => $dir) {
            if ($dir == '..') {
                if (end($result) == '..') {
                    $result[] = '..';
                } elseif (!array_pop($result)) {
                    $result[] = '..';
                }
            } elseif ($dir && $dir != '.') {
                $result[] = $dir;
            }
        }
        if (!end($parts)) $result[] = '';
        $id = implode(':', $result);

        return $id;
    }

}
