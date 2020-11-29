<?php

namespace dokuwiki\Search;

use dokuwiki\Extension\Event;
use dokuwiki\Utf8;

// set the minimum token length to use in the index
// (note, this doesn't apply to numeric tokens)
const MINWORDLENGTH = 2;

/**
 * DokuWiki Tokenizer class
 */
class Tokenizer
{
    /** @var array $Stopwords Words that tokenizer ignores */
    protected static $Stopwords;

    /** @var int $MinWordLength minimum token length */
    protected static $MinWordLength;

    /**
     * Returns words that will be ignored
     *
     * @return array  list of stop words
     *
     * @author Tom N Harris <tnharris@whoopdedo.org>
     */
    public static function getStopwords()
    {
        if (!isset(static::$Stopwords)) {
            global $conf;
            $swFile = DOKU_INC.'inc/lang/'.$conf['lang'].'/stopwords.txt';
            if (file_exists($swFile)) {
                static::$Stopwords = file($swFile, FILE_IGNORE_NEW_LINES);
            } else {
                static::$Stopwords = array();
            }
        }
        return static::$Stopwords;
    }

    /**
     * Returns minimum word length to be used in the index
     *
     * @return int
     */
    public static function getMinWordLength()
    {
        if (!isset(static::$MinWordLength)) {
            // set the minimum token length to use in the index
            // (note, this doesn't apply to numeric tokens)
            static::$MinWordLength = (defined('IDX_MINWORDLENGTH'))
                ? IDX_MINWORDLENGTH
                : MINWORDLENGTH;
        }
        return static::$MinWordLength;
    }

    /**
     * Split the text into words for fulltext search
     *
     * @triggers INDEXER_TEXT_PREPARE
     * This event allows plugins to modify the text before it gets tokenized.
     * Plugins intercepting this event should also intercept INDEX_VERSION_GET
     *
     * @param string $text plain text
     * @param bool $wc are wildcards allowed?
     * @return array  list of words in the text
     *
     * @author Tom N Harris <tnharris@whoopdedo.org>
     * @author Andreas Gohr <andi@splitbrain.org>
     */
    public static function getWords($text, $wc = false)
    {
        $wc = ($wc) ? '' : '\*';

        // prepare the text to be tokenized
        $event = new Event('INDEXER_TEXT_PREPARE', $text);
        if ($event->advise_before(true)) {
            if (preg_match('/[^0-9A-Za-z ]/u', $text)) {
                $text = Utf8\Asian::separateAsianWords($text);
            }
        }
        $event->advise_after();
        unset($event);

        $text = strtr($text, array(
                "\r" => ' ',
                "\n" => ' ',
                "\t" => ' ',
                "\xC2\xAD" => '', //soft-hyphen
        ));
        if (preg_match('/[^0-9A-Za-z ]/u', $text)) {
            $text = Utf8\Clean::stripspecials($text, ' ', '\._\-:'.$wc);
        }

        $wordlist = explode(' ', $text);
        foreach ($wordlist as $i => $word) {
            $wordlist[$i] = (preg_match('/[^0-9A-Za-z]/u', $word)) ?
                Utf8\PhpString::strtolower($word) : strtolower($word);
        }

        foreach ($wordlist as $i => $word) {
            if ((!is_numeric($word) && strlen($word) < static::getMinWordLength())
              || array_search($word, static::getStopwords(), true) !== false) {
                unset($wordlist[$i]);
            }
        }
        return array_values($wordlist);
    }
}
