<?php

namespace dokuwiki\Parsing\Handler;

/**
 * Handler for paragraphs
 *
 * @author Harry Fuecks <hfuecks@gmail.com>
 */
class Block
{
    protected $calls = array();
    protected $skipEol = false;
    protected $inParagraph = false;

    // Blocks these should not be inside paragraphs
    protected $blockOpen = array(
        'header',
        'listu_open','listo_open','listitem_open','listcontent_open',
        'table_open','tablerow_open','tablecell_open','tableheader_open','tablethead_open',
        'quote_open',
        'code','file','hr','preformatted','rss',
        'htmlblock','phpblock',
        'footnote_open',
    );

    protected $blockClose = array(
        'header',
        'listu_close','listo_close','listitem_close','listcontent_close',
        'table_close','tablerow_close','tablecell_close','tableheader_close','tablethead_close',
        'quote_close',
        'code','file','hr','preformatted','rss',
        'htmlblock','phpblock',
        'footnote_close',
    );

    // Stacks can contain paragraphs
    protected $stackOpen = array(
        'section_open',
    );

    protected $stackClose = array(
        'section_close',
    );

    /**
     * Constructor. Adds loaded syntax plugins to the block and stack
     * arrays
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     */
    public function __construct()
    {
        global $DOKU_PLUGINS;
        //check if syntax plugins were loaded
        if (empty($DOKU_PLUGINS['syntax'])) return;
        foreach ($DOKU_PLUGINS['syntax'] as $n => $p) {
            $ptype = $p->getPType();
            if ($ptype == 'block') {
                $this->blockOpen[]  = 'plugin_'.$n;
                $this->blockClose[] = 'plugin_'.$n;
            } elseif ($ptype == 'stack') {
                $this->stackOpen[]  = 'plugin_'.$n;
                $this->stackClose[] = 'plugin_'.$n;
            }
        }
    }

    protected function openParagraph($pos)
    {
        if ($this->inParagraph) return;
        $this->calls[] = array('p_open',array(), $pos);
        $this->inParagraph = true;
        $this->skipEol = true;
    }

    /**
     * Close a paragraph if needed
     *
     * This function makes sure there are no empty paragraphs on the stack
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     *
     * @param string|integer $pos
     */
    protected function closeParagraph($pos)
    {
        if (!$this->inParagraph) return;
        // look back if there was any content - we don't want empty paragraphs
        $content = '';
        $ccount = count($this->calls);
        for ($i=$ccount-1; $i>=0; $i--) {
            if ($this->calls[$i][0] == 'p_open') {
                break;
            } elseif ($this->calls[$i][0] == 'cdata') {
                $content .= $this->calls[$i][1][0];
            } else {
                $content = 'found markup';
                break;
            }
        }

        if (trim($content)=='') {
            //remove the whole paragraph
            //array_splice($this->calls,$i); // <- this is much slower than the loop below
            for ($x=$ccount; $x>$i;
            $x--) array_pop($this->calls);
        } else {
            // remove ending linebreaks in the paragraph
            $i=count($this->calls)-1;
            if ($this->calls[$i][0] == 'cdata') $this->calls[$i][1][0] = rtrim($this->calls[$i][1][0], "\n");
            $this->calls[] = array('p_close',array(), $pos);
        }

        $this->inParagraph = false;
        $this->skipEol = true;
    }

    protected function addCall($call)
    {
        $key = count($this->calls);
        if ($key and ($call[0] == 'cdata') and ($this->calls[$key-1][0] == 'cdata')) {
            $this->calls[$key-1][1][0] .= $call[1][0];
        } else {
            $this->calls[] = $call;
        }
    }

    // simple version of addCall, without checking cdata
    protected function storeCall($call)
    {
        $this->calls[] = $call;
    }

    /**
     * Processes the whole instruction stack to open and close paragraphs
     *
     * @author Harry Fuecks <hfuecks@gmail.com>
     * @author Andreas Gohr <andi@splitbrain.org>
     *
     * @param array $calls
     *
     * @return array
     */
    public function process($calls)
    {
        // open first paragraph
        $this->openParagraph(0);
        foreach ($calls as $key => $call) {
            //print_r($call);
            //echo '<br>';
            $cname = $call[0];

            $plugin_open = false;
            $plugin_close = false;

            if ($cname == 'plugin') {
                $cname='plugin_'.$call[1][0];
                $plugin = true;
                $plugin_open = (($call[1][2] == DOKU_LEXER_ENTER) || ($call[1][2] == DOKU_LEXER_SPECIAL));
                $plugin_close = (($call[1][2] == DOKU_LEXER_EXIT) || ($call[1][2] == DOKU_LEXER_SPECIAL));
            } else {
                $plugin = false;
            }

            /* cdata */
            // Force opening of paragraph when in cdata and not between p_open or p_close
            // and in between quote_open and quote_close
            if (($cname === 'cdata' && rtrim($call[0], "\n\r\t") !== '' && $calls[$key][0] != 'p_open')
                || (isset($calls[$key-1][0]) && $calls[$key-1][0] != 'p_open'
                && $calls[$key-1][0] != 'p_close')) {

                if ($calls[$key-1][0] == 'quote_open' || $calls[$key-1][0] == 'quote_close') {
                    if (!$this->inParagraph) $this->openParagraph($call[2]);
                } elseif ($this->inParagraph && ($calls[$key-1][0] == 'quote_close' ||
                    $calls[$key+1][0] == 'p_close'))  {
                    $this->closeParagraph($call[2]);
                }
            }

            /* stack */
            if (in_array($cname, $this->stackClose) && (!$plugin || $plugin_close)) {
                $this->closeParagraph($call[2]);
                $this->storeCall($call);
                $this->openParagraph($call[2]);
                continue;
            }
            if (in_array($cname, $this->stackOpen) && (!$plugin || $plugin_open)) {
                $this->closeParagraph($call[2]);
                $this->storeCall($call);
                $this->openParagraph($call[2]);
                continue;
            }
            /* block */
            // If it's a substition it opens and closes at the same call.
            // To make sure next paragraph is correctly started, let close go first.
            if (in_array($cname, $this->blockClose) && (!$plugin || $plugin_close)) {
                $this->closeParagraph($call[2]);
                $this->storeCall($call);
                $this->openParagraph($call[2]);
                continue;
            }
            if (in_array($cname, $this->blockOpen) && (!$plugin || $plugin_open)) {
                $this->closeParagraph($call[2]);
                $this->storeCall($call);
                continue;
            }

            /* eol */
            if ($cname == 'eol') {
                // Check this isn't an eol instruction to skip...
                if (!$this->skipEol) {
                    // Next is EOL => double eol => mark as paragraph
                    if (isset($calls[$key+1]) && $calls[$key+1][0] == 'eol') {
                        $this->closeParagraph($call[2]);
                        $this->openParagraph($call[2]);
                    } else {
                        //if this is just a single eol make a space from it
                        $this->addCall(array('cdata',array("\n"), $call[2]));
                    }
                }
                continue;
            }
            /* normal */
            $this->addCall($call);
            $this->skipEol = false;
        }
        // close last paragraph
        $call = end($this->calls);
        $this->closeParagraph($call[2]);
        return $this->calls;
    }
}
