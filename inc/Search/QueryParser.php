<?php

namespace dokuwiki\Search;

use dokuwiki\Search\Tokenizer;
use dokuwiki\Utf8;

/**
 * DokuWuki QueryParser class
 */
class QueryParser
{
    /**
     * Transforms given search term into intermediate representation
     *
     * This function is used in QueryParser::convert() and not for general purpose use.
     *
     * @author Kazutaka Miyasaka <kazmiya@gmail.com>
     *
     * @param string       $term
     * @param bool         $consider_asian
     * @param bool         $phrase_mode
     * @return string
     */
    public function termParser($term, $consider_asian = true, $phrase_mode = false)
    {
        $parsed = '';
        if ($consider_asian) {
            // successive asian characters need to be searched as a phrase
            $words = Utf8\Asian::splitAsianWords($term);
            foreach ($words as $word) {
                $phrase_mode = $phrase_mode ? true : Utf8\Asian::isAsianWords($word);
                $parsed .= $this->termParser($word, false, $phrase_mode);
            }
        } else {
            $term_noparen = str_replace(['(',')'], ' ', $term);
            $words = Tokenizer::getWords($term_noparen, true);

            // W_: no need to highlight
            if (empty($words)) {
                $parsed = '()'; // important: do not remove
            } elseif ($words[0] === $term) {
                $parsed = '(W+:'.$words[0].')';
            } elseif ($phrase_mode) {
                $term_encoded = str_replace(['(',')'], ['OP','CP'], $term);
                $parsed = '((W_:'.implode(')(W_:', $words).')(P+:'.$term_encoded.'))';
            } else {
                $parsed = '((W+:'.implode(')(W+:', $words).'))';
            }
        }
        return $parsed;
    }

    /**
     * Parses a search query and builds an array of search formulas
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     * @author Kazutaka Miyasaka <kazmiya@gmail.com>
     *
     * @param string $query search query
     * @return array of search formulas
     */
    public function convert($query)
    {
        /**
         * parse a search query and transform it into intermediate representation
         *
         * in a search query, you can use the following expressions:
         *
         *   words:
         *     include
         *     -exclude
         *   phrases:
         *     "phrase to be included"
         *     -"phrase you want to exclude"
         *   namespaces:
         *     @include:namespace (or ns:include:namespace)
         *     ^exclude:namespace (or -ns:exclude:namespace)
         *   groups:
         *     ()
         *     -()
         *   operators:
         *     and ('and' is the default operator: you can always omit this)
         *     or  (or pipe symbol '|', lower precedence than 'and')
         *
         * e.g. a query [ aa "bb cc" @dd:ee ] means "search pages which contain
         *      a word 'aa', a phrase 'bb cc' and are within a namespace 'dd:ee'".
         *      this query is equivalent to [ -(-aa or -"bb cc" or -ns:dd:ee) ]
         *      as long as you don't mind hit counts.
         *
         * intermediate representation consists of the following parts:
         *
         *   ( )           - group
         *   AND           - logical and
         *   OR            - logical or
         *   NOT           - logical not
         *   W+:, W-:, W_: - word      (underscore: no need to highlight)
         *   P+:, P-:      - phrase    (minus sign: logically in NOT group)
         *   N+:, N-:      - namespace
         */
        $parsed_query = '';
        $parens_level = 0;
        $terms = preg_split('/(-?".*?")/u', Utf8\PhpString::strtolower($query),
                    -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
        );

        foreach ($terms as $term) {
            $parsed = '';
            if (preg_match('/^(-?)"(.+)"$/u', $term, $matches)) {
                // phrase-include and phrase-exclude
                $not = $matches[1] ? 'NOT' : '';
                $parsed = $not . $this->termParser($matches[2], false, true);
            } else {
                // fix incomplete phrase
                $term = str_replace('"', ' ', $term);

                // fix parentheses
                $term = str_replace(')'  , ' ) ', $term);
                $term = str_replace('('  , ' ( ', $term);
                $term = str_replace('- (', ' -(', $term);

                // treat pipe symbols as 'OR' operators
                $term = str_replace('|', ' or ', $term);

                // treat ideographic spaces (U+3000) as search term separators
                // FIXME: some more separators?
                $term = preg_replace('/[ \x{3000}]+/u', ' ',  $term);
                $term = trim($term);
                if ($term === '') continue;

                $tokens = explode(' ', $term);
                foreach ($tokens as $token) {
                    if ($token === '(') {
                        // parenthesis-include-open
                        $parsed .= '(';
                        ++$parens_level;
                    } elseif ($token === '-(') {
                        // parenthesis-exclude-open
                        $parsed .= 'NOT(';
                        ++$parens_level;
                    } elseif ($token === ')') {
                        // parenthesis-any-close
                        if ($parens_level === 0) continue;
                        $parsed .= ')';
                        $parens_level--;
                    } elseif ($token === 'and') {
                        // logical-and (do nothing)
                    } elseif ($token === 'or') {
                        // logical-or
                        $parsed .= 'OR';
                    } elseif (preg_match('/^(?:\^|-ns:)(.+)$/u', $token, $matches)) {
                        // namespace-exclude
                        $parsed .= 'NOT(N+:'.$matches[1].')';
                    } elseif (preg_match('/^(?:@|ns:)(.+)$/u', $token, $matches)) {
                        // namespace-include
                        $parsed .= '(N+:'.$matches[1].')';
                    } elseif (preg_match('/^-(.+)$/', $token, $matches)) {
                        // word-exclude
                        $parsed .= 'NOT('.$this->termParser($matches[1]).')';
                    } else {
                        // word-include
                        $parsed .= $this->termParser($token);
                    }
                }
            }
            $parsed_query .= $parsed;
        }

        // cleanup (very sensitive)
        $parsed_query .= str_repeat(')', $parens_level);
        do {
            $parsed_query_old = $parsed_query;
            $parsed_query = preg_replace('/(NOT)?\(\)/u', '', $parsed_query);
        } while ($parsed_query !== $parsed_query_old);
        $parsed_query = preg_replace('/(NOT|OR)+\)/u', ')'      , $parsed_query);
        $parsed_query = preg_replace('/(OR)+/u'      , 'OR'     , $parsed_query);
        $parsed_query = preg_replace('/\(OR/u'       , '('      , $parsed_query);
        $parsed_query = preg_replace('/^OR|OR$/u'    , ''       , $parsed_query);
        $parsed_query = preg_replace('/\)(NOT)?\(/u' , ')AND$1(', $parsed_query);

        // adjustment: make highlightings right
        $parens_level     = 0;
        $notgrp_levels    = array();
        $parsed_query_new = '';
        $tokens = preg_split('/(NOT\(|[()])/u', $parsed_query,
                    -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
        );
        foreach ($tokens as $token) {
            if ($token === 'NOT(') {
                $notgrp_levels[] = ++$parens_level;
            } elseif ($token === '(') {
                ++$parens_level;
            } elseif ($token === ')') {
                if ($parens_level-- === end($notgrp_levels)) array_pop($notgrp_levels);
            } elseif (count($notgrp_levels) % 2 === 1) {
                // turn highlight-flag off if terms are logically in "NOT" group
                $token = preg_replace('/([WPN])\+\:/u', '$1-:', $token);
            }
            $parsed_query_new .= $token;
        }
        $parsed_query = $parsed_query_new;

        /**
         * convert infix notation string into postfix (Reverse Polish notation) array
         * by Shunting-yard algorithm
         *
         * see: http://en.wikipedia.org/wiki/Reverse_Polish_notation
         * see: http://en.wikipedia.org/wiki/Shunting-yard_algorithm
         */
        $parsed_ary     = array();
        $ope_stack      = array();
        $ope_precedence = array(')' => 1, 'OR' => 2, 'AND' => 3, 'NOT' => 4, '(' => 5);
        $ope_regex      = '/([()]|OR|AND|NOT)/u';

        $tokens = preg_split($ope_regex, $parsed_query,
                    -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
        );
        foreach ($tokens as $token) {
            if (preg_match($ope_regex, $token)) {
                // operator
                $last_ope = end($ope_stack);
                while ($last_ope !== false
                    && $ope_precedence[$token] <= $ope_precedence[$last_ope]
                    && $last_ope != '('
                ) {
                    $parsed_ary[] = array_pop($ope_stack);
                    $last_ope = end($ope_stack);
                }
                if ($token == ')') {
                    array_pop($ope_stack); // this array_pop always deletes '('
                } else {
                    $ope_stack[] = $token;
                }
            } else {
                // operand
                $token_decoded = str_replace(['OP','CP'], ['(',')'], $token);
                $parsed_ary[] = $token_decoded;
            }
        }
        $parsed_ary = array_values(array_merge($parsed_ary, array_reverse($ope_stack)));

        // cleanup: each double "NOT" in RPN array actually does nothing
        $parsed_ary_count = count($parsed_ary);
        for ($i = 1; $i < $parsed_ary_count; ++$i) {
            if ($parsed_ary[$i] === 'NOT' && $parsed_ary[$i - 1] === 'NOT') {
                unset($parsed_ary[$i], $parsed_ary[$i - 1]);
            }
        }
        $parsed_ary = array_values($parsed_ary);

        // build return value
        $q = array();
        $q['query']      = $query;
        $q['parsed_str'] = $parsed_query;
        $q['parsed_ary'] = $parsed_ary;

        foreach ($q['parsed_ary'] as $token) {
            if ($token[2] !== ':') continue;
            $body = substr($token, 3);

            switch (substr($token, 0, 3)) {
                case 'N+:':
                     $q['ns'][]        = $body; // for backward compatibility
                     break;
                case 'N-:':
                     $q['notns'][]     = $body; // for backward compatibility
                     break;
                case 'W_:':
                     $q['words'][]     = $body;
                     break;
                case 'W-:':
                     $q['words'][]     = $body;
                     $q['not'][]       = $body; // for backward compatibility
                     break;
                case 'W+:':
                     $q['words'][]     = $body;
                     $q['highlight'][] = $body;
                     $q['and'][]       = $body; // for backward compatibility
                     break;
                case 'P-:':
                     $q['phrases'][]   = $body;
                     break;
                case 'P+:':
                     $q['phrases'][]   = $body;
                     $q['highlight'][] = $body;
                     break;
            }
        }
        foreach (['words', 'phrases', 'highlight', 'ns', 'notns', 'and', 'not'] as $key) {
            $q[$key] = empty($q[$key]) ? array() : array_values(array_unique($q[$key]));
        }

        return $q;
    }

    /**
     * Recreate a search query string based on parsed parts,
     * doesn't support negated phrases and `OR` searches
     *
     * @param array $and
     * @param array $not
     * @param array $phrases
     * @param array $ns
     * @param array $notns
     *
     * @return string
     */
    public function revert(array $and, array $not, array $phrases, array $ns, array $notns)
    {
        $query = implode(' ', $and);

        if (!empty($not)) {
            $query .= ' -' . implode(' -', $not);
        }
        if (!empty($phrases)) {
            $query .= ' "' . implode('" "', $phrases) . '"';
        }
        if (!empty($ns)) {
            $query .= ' @' . implode(' @', $ns);
        }
        if (!empty($notns)) {
            $query .= ' ^' . implode(' ^', $notns);
        }
        return $query;
    }
}
